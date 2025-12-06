<script setup>
import { ref, computed } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';

const page = usePage();
const props = defineProps({
  areas: Array,
  centros: Array,
  can: Object,
  urls: { type: Object, required: false }
});

// Selector de centro para filtrar
const centroSel = ref(page.props.filters?.centro ? Number(page.props.filters.centro) : (props.centros?.[0]?.id || page.props.auth?.user?.centro_trabajo_id || null))
function changeCentro(){
  const q = new URLSearchParams({ centro: String(centroSel.value) })
  const url = props.urls?.index ? `${props.urls.index}?${q.toString()}` : `${route('areas.index')}?${q.toString()}`
  router.get(url, {}, { preserveState: false, replace: true })
}

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingArea = ref(null);

const createForm = useForm({
  id_centrotrabajo: page.props.auth?.user?.centro_trabajo_id || null,
  nombre: '',
  descripcion: '',
  activo: true
});

const editForm = useForm({
  nombre: '',
  descripcion: '',
  activo: true
});

function openCreateModal() {
  createForm.reset();
  createForm.id_centrotrabajo = page.props.auth?.user?.centro_trabajo_id || null;
  showCreateModal.value = true;
}

function closeCreateModal() {
  showCreateModal.value = false;
  createForm.reset();
}

function submitCreate() {
  createForm.post(route('areas.store'), {
    onSuccess: () => {
      closeCreateModal();
    }
  });
}

function openEditModal(area) {
  editingArea.value = area;
  editForm.nombre = area.nombre;
  editForm.descripcion = area.descripcion || '';
  editForm.activo = area.activo;
  showEditModal.value = true;
}

function closeEditModal() {
  showEditModal.value = false;
  editingArea.value = null;
  editForm.reset();
}

function submitEdit() {
  editForm.put(route('areas.update', editingArea.value.id), {
    onSuccess: () => {
      closeEditModal();
    }
  });
}

function deleteArea(area) {
  if (!confirm(`¿Eliminar el área "${area.nombre}"?`)) return;
  router.delete(route('areas.destroy', area.id));
}

const groupedAreas = computed(() => {
  const groups = {};
  const list = centroSel.value ? props.areas.filter(a => Number(a.id_centrotrabajo) === Number(centroSel.value)) : props.areas
  list.forEach(a => {
    const centroName = a.centro?.nombre || 'Sin Centro';
    if (!groups[centroName]) groups[centroName] = [];
    groups[centroName].push(a);
  });
  return groups;
});
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-emerald-50 to-teal-50 py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        
        <!-- Header con gradiente -->
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-2xl shadow-xl p-6 sm:p-8 mb-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="w-full md:w-auto">
              <h1 class="text-3xl font-extrabold text-white mb-2">📍 Áreas</h1>
              <p class="text-emerald-100">Administra las áreas por centro de trabajo</p>
            </div>
            <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-xl p-4 border-2 border-white border-opacity-30 flex flex-col sm:flex-row sm:items-center gap-4 w-full md:w-auto">
              <div class="w-full sm:w-auto">
                <label class="block text-sm font-semibold text-white mb-2">Centro de trabajo</label>
                <select v-model.number="centroSel" @change="changeCentro"
                        class="px-4 py-2.5 rounded-lg border-2 border-emerald-200 min-w-[14rem] w-full bg-white font-semibold text-gray-700 focus:ring-4 focus:ring-emerald-200 transition-all duration-200">
                  <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
              </div>

              <button v-if="can?.create" @click="openCreateModal"
                      class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white text-emerald-600 font-bold rounded-xl hover:bg-emerald-50 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105 w-full sm:w-auto">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Nueva Área
              </button>
            </div>
          </div>
        </div>

        <!-- Listado agrupado por centro -->
        <div v-for="(areasList, centroName) in groupedAreas" :key="centroName" class="mb-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center text-white font-bold shadow-lg">
              🏢
            </div>
            <h2 class="text-2xl font-bold text-gray-800">{{ centroName }}</h2>
          </div>

          <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 overflow-hidden">
            <div class="hidden md:block overflow-x-auto">
              <table class="w-full text-[0.75rem] lg:text-sm divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100 text-gray-700 uppercase tracking-wider">
                  <tr>
                    <th class="px-4 lg:px-6 py-3 text-left font-bold">Nombre</th>
                    <th class="px-4 lg:px-6 py-3 text-left font-bold">Descripción</th>
                    <th class="px-4 lg:px-6 py-3 text-center font-bold">Estado</th>
                    <th v-if="can?.edit" class="px-4 lg:px-6 py-3 text-right font-bold">Acciones</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="area in areasList" :key="area.id" class="hover:bg-emerald-50 transition-colors duration-150">
                    <td class="px-4 lg:px-6 py-3 align-top">
                      <div class="font-semibold text-gray-900">{{ area.nombre }}</div>
                    </td>
                    <td class="px-4 lg:px-6 py-3 text-gray-600">{{ area.descripcion || '—' }}</td>
                    <td class="px-4 lg:px-6 py-3 text-center">
                      <span v-if="area.activo" class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full font-semibold">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Activa
                      </span>
                      <span v-else class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-700 rounded-full font-semibold">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        Inactiva
                      </span>
                    </td>
                    <td v-if="can?.edit" class="px-4 lg:px-6 py-3">
                      <div class="flex flex-wrap items-center justify-end gap-2">
                        <button @click="openEditModal(area)"
                                class="inline-flex items-center gap-1 px-3 sm:px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors duration-200 text-xs sm:text-sm">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                          </svg>
                          Editar
                        </button>
                        <button @click="deleteArea(area)"
                                class="inline-flex items-center gap-1 px-3 sm:px-4 py-2 bg-red-100 text-red-700 font-medium rounded-lg hover:bg-red-200 transition-colors duration-200 text-xs sm:text-sm">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                          </svg>
                          Eliminar
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="md:hidden p-4 space-y-4">
              <div v-for="area in areasList" :key="`m-${area.id}`" class="border border-gray-200 rounded-2xl p-4 shadow-sm bg-white">
                <div class="flex flex-col gap-2">
                  <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Área</div>
                    <div class="text-lg font-semibold text-gray-900">{{ area.nombre }}</div>
                  </div>
                  <span v-if="area.activo" class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200 inline-flex items-center gap-2 self-start">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Activa
                  </span>
                  <span v-else class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 inline-flex items-center gap-2 self-start">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    Inactiva
                  </span>
                </div>

                <div class="mt-4 space-y-2 text-sm text-gray-600">
                  <div class="text-xs font-semibold text-gray-500">Descripción</div>
                  <div>{{ area.descripcion || '—' }}</div>
                </div>

                <div v-if="can?.edit" class="mt-4 flex flex-col sm:flex-row gap-2">
                  <button @click="openEditModal(area)"
                          class="flex-1 px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition-colors duration-200 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Editar
                  </button>
                  <button @click="deleteArea(area)"
                          class="flex-1 px-4 py-2 rounded-lg bg-red-100 text-red-700 font-semibold hover:bg-red-200 transition-colors duration-200 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Eliminar
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="!areas || areas.length === 0" class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-12 text-center">
          <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
          <p class="text-xl font-medium text-gray-600">No hay áreas registradas</p>
          <p class="text-gray-500 mt-2">Comienza creando una nueva área</p>
        </div>

      </div>

      <!-- Modal Crear -->
      <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all">
          <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4 rounded-t-2xl">
            <h2 class="text-2xl font-bold text-white">Nueva Área</h2>
          </div>
          
          <form @submit.prevent="submitCreate" class="p-6">
            <div class="mb-5" v-if="centros.length > 1">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Centro de Trabajo</label>
              <select v-model="createForm.id_centrotrabajo" required
                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400 transition-all duration-200">
                <option :value="null">— Selecciona un centro —</option>
                <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
              </select>
              <p v-if="createForm.errors.id_centrotrabajo" class="text-red-600 text-sm mt-1 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ createForm.errors.id_centrotrabajo }}
              </p>
            </div>

            <div class="mb-5">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre del Área</label>
              <input v-model="createForm.nombre" type="text" required
                     placeholder="Ej: Producción, Almacén..."
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400 transition-all duration-200" />
              <p v-if="createForm.errors.nombre" class="text-red-600 text-sm mt-1 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ createForm.errors.nombre }}
              </p>
            </div>

            <div class="mb-5">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Descripción (opcional)</label>
              <textarea v-model="createForm.descripcion" rows="3"
                        placeholder="Describe el área..."
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400 transition-all duration-200 resize-none"></textarea>
            </div>

            <div class="mb-6">
              <label class="flex items-center gap-3 p-4 bg-emerald-50 rounded-xl border-2 border-emerald-200 cursor-pointer hover:bg-emerald-100 transition-colors duration-200">
                <input v-model="createForm.activo" type="checkbox" class="w-5 h-5 text-emerald-600 rounded focus:ring-2 focus:ring-emerald-500" />
                <div>
                  <span class="text-sm font-semibold text-gray-800">Área activa</span>
                  <p class="text-xs text-gray-600">El área estará disponible para asignación</p>
                </div>
              </label>
            </div>

            <div class="flex gap-3">
              <button type="button" @click="closeCreateModal"
                      class="flex-1 px-6 py-3 rounded-xl border-2 border-gray-300 font-semibold text-gray-700 hover:bg-gray-50 transition-all duration-200">
                Cancelar
              </button>
              <button type="submit" :disabled="createForm.processing"
                      class="flex-1 px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 font-bold text-white hover:shadow-xl transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed transform hover:scale-105">
                {{ createForm.processing ? 'Guardando...' : 'Guardar' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Editar -->
      <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all">
          <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4 rounded-t-2xl">
            <h2 class="text-2xl font-bold text-white">Editar Área</h2>
          </div>
          
          <form @submit.prevent="submitEdit" class="p-6">
            <div class="mb-5">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre del Área</label>
              <input v-model="editForm.nombre" type="text" required
                     placeholder="Ej: Producción, Almacén..."
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400 transition-all duration-200" />
              <p v-if="editForm.errors.nombre" class="text-red-600 text-sm mt-1 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ editForm.errors.nombre }}
              </p>
            </div>

            <div class="mb-5">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Descripción (opcional)</label>
              <textarea v-model="editForm.descripcion" rows="3"
                        placeholder="Describe el área..."
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400 transition-all duration-200 resize-none"></textarea>
            </div>

            <div class="mb-6">
              <label class="flex items-center gap-3 p-4 bg-emerald-50 rounded-xl border-2 border-emerald-200 cursor-pointer hover:bg-emerald-100 transition-colors duration-200">
                <input v-model="editForm.activo" type="checkbox" class="w-5 h-5 text-emerald-600 rounded focus:ring-2 focus:ring-emerald-500" />
                <div>
                  <span class="text-sm font-semibold text-gray-800">Área activa</span>
                  <p class="text-xs text-gray-600">El área estará disponible para asignación</p>
                </div>
              </label>
            </div>

            <div class="flex gap-3">
              <button type="button" @click="closeEditModal"
                      class="flex-1 px-6 py-3 rounded-xl border-2 border-gray-300 font-semibold text-gray-700 hover:bg-gray-50 transition-all duration-200">
                Cancelar
              </button>
              <button type="submit" :disabled="editForm.processing"
                      class="flex-1 px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 font-bold text-white hover:shadow-xl transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed transform hover:scale-105">
                {{ editForm.processing ? 'Guardando...' : 'Guardar' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
</template>
