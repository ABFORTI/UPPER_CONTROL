<script setup>
import { ref, computed } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const page = usePage();
const props = defineProps({
  areas: Array,
  centros: Array,
  can: Object
});

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
  props.areas.forEach(a => {
    const centroName = a.centro?.nombre || 'Sin Centro';
    if (!groups[centroName]) groups[centroName] = [];
    groups[centroName].push(a);
  });
  return groups;
});
</script>

<template>
  <AuthenticatedLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Áreas</h1>
        <button v-if="can?.create" @click="openCreateModal"
                class="px-4 py-2 rounded bg-black text-white hover:bg-gray-800">
          + Nueva Área
        </button>
      </div>

      <!-- Listado agrupado por centro -->
      <div v-for="(areasList, centroName) in groupedAreas" :key="centroName" class="mb-6">
        <h2 class="text-lg font-semibold mb-3 text-gray-700">{{ centroName }}</h2>
        <div class="bg-white rounded shadow overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="p-3 text-left">Nombre</th>
                <th class="p-3 text-left">Descripción</th>
                <th class="p-3 text-center">Estado</th>
                <th class="p-3 text-center" v-if="can?.edit">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="area in areasList" :key="area.id" class="border-t hover:bg-gray-50">
                <td class="p-3 font-medium">{{ area.nombre }}</td>
                <td class="p-3 text-gray-600">{{ area.descripcion || '—' }}</td>
                <td class="p-3 text-center">
                  <span :class="area.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                        class="px-2 py-1 rounded text-xs">
                    {{ area.activo ? 'Activa' : 'Inactiva' }}
                  </span>
                </td>
                <td class="p-3 text-center" v-if="can?.edit">
                  <button @click="openEditModal(area)"
                          class="text-blue-600 hover:underline mr-3">
                    Editar
                  </button>
                  <button @click="deleteArea(area)"
                          class="text-red-600 hover:underline">
                    Eliminar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="!areas || areas.length === 0" class="text-center text-gray-500 py-12">
        No hay áreas registradas.
      </div>

      <!-- Modal Crear -->
      <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
          <h2 class="text-xl font-bold mb-4">Nueva Área</h2>
          <form @submit.prevent="submitCreate">
            <div class="mb-4" v-if="centros.length > 1">
              <label class="block text-sm font-medium mb-1">Centro de Trabajo</label>
              <select v-model="createForm.id_centrotrabajo" required
                      class="w-full border rounded px-3 py-2">
                <option :value="null">— Selecciona —</option>
                <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
              </select>
              <p v-if="createForm.errors.id_centrotrabajo" class="text-red-600 text-sm mt-1">
                {{ createForm.errors.id_centrotrabajo }}
              </p>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium mb-1">Nombre</label>
              <input v-model="createForm.nombre" type="text" required
                     class="w-full border rounded px-3 py-2" />
              <p v-if="createForm.errors.nombre" class="text-red-600 text-sm mt-1">
                {{ createForm.errors.nombre }}
              </p>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium mb-1">Descripción (opcional)</label>
              <textarea v-model="createForm.descripcion" rows="3"
                        class="w-full border rounded px-3 py-2"></textarea>
            </div>

            <div class="mb-4">
              <label class="flex items-center">
                <input v-model="createForm.activo" type="checkbox" class="mr-2" />
                <span class="text-sm">Activa</span>
              </label>
            </div>

            <div class="flex justify-end gap-2">
              <button type="button" @click="closeCreateModal"
                      class="px-4 py-2 rounded border hover:bg-gray-50">
                Cancelar
              </button>
              <button type="submit" :disabled="createForm.processing"
                      class="px-4 py-2 rounded bg-black text-white hover:bg-gray-800 disabled:opacity-60">
                {{ createForm.processing ? 'Guardando...' : 'Guardar' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Editar -->
      <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
          <h2 class="text-xl font-bold mb-4">Editar Área</h2>
          <form @submit.prevent="submitEdit">
            <div class="mb-4">
              <label class="block text-sm font-medium mb-1">Nombre</label>
              <input v-model="editForm.nombre" type="text" required
                     class="w-full border rounded px-3 py-2" />
              <p v-if="editForm.errors.nombre" class="text-red-600 text-sm mt-1">
                {{ editForm.errors.nombre }}
              </p>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium mb-1">Descripción (opcional)</label>
              <textarea v-model="editForm.descripcion" rows="3"
                        class="w-full border rounded px-3 py-2"></textarea>
            </div>

            <div class="mb-4">
              <label class="flex items-center">
                <input v-model="editForm.activo" type="checkbox" class="mr-2" />
                <span class="text-sm">Activa</span>
              </label>
            </div>

            <div class="flex justify-end gap-2">
              <button type="button" @click="closeEditModal"
                      class="px-4 py-2 rounded border hover:bg-gray-50">
                Cancelar
              </button>
              <button type="submit" :disabled="editForm.processing"
                      class="px-4 py-2 rounded bg-black text-white hover:bg-gray-800 disabled:opacity-60">
                {{ editForm.processing ? 'Guardando...' : 'Guardar' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
