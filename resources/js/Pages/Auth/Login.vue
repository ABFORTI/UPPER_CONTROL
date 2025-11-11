<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useAssetUrl } from '@/Support/useAssetUrl';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const asset = useAssetUrl();

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[#F5F7FA] font-roboto">
    <div class="bg-white rounded-2xl shadow-2xl flex w-full max-w-4xl overflow-hidden scale-105">
  <!-- Branding / Info (hidden on small screens) -->
  <div class="hidden md:flex md:w-1/2 bg-[#F5F7FA] flex-col items-center justify-center p-10">
        <div class="w-25 h-25 mb-6 flex items-center justify-center">
          <img :src="asset('img/upper_control.png')" alt="Upper Control" loading="lazy" class="w-full h-full object-contain" />
        </div>
        <div class="text-center">
          <div class="font-poppins font-bold text-2xl text-[#2E3A59] mb-1"></div>
          <div class="font-poppins text-sm text-[#1A73E8] mb-2">Plataforma de Gestión Logística</div>
          <div class="text-base text-[#2E3A59] opacity-80 font-roboto">Solicita, cotiza y realiza seguimiento de servicios logísticos de manera centralizada y transparente</div>
        </div>
      </div>
  <!-- Login Form -->
  <div class="w-full md:w-1/2 flex flex-col justify-center p-10">
        <h2 class="font-poppins text-3xl font-semibold mb-8 text-[#2E3A59]">Iniciar sesión</h2>
        <form @submit.prevent="submit">
          <div class="mb-5">
            <label class="block text-sm mb-1 font-poppins text-[#2E3A59]">Correo electrónico</label>
            <input v-model="form.email" type="email" class="w-full border border-[#E0E3EB] rounded px-3 py-2 font-roboto text-[#2E3A59] focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/20 outline-none" placeholder="usuario@empresa.com" required autofocus>
          </div>
          <div class="mb-5">
            <label class="block text-sm mb-1 font-poppins text-[#2E3A59]">Contraseña</label>
            <input v-model="form.password" type="password" class="w-full border border-[#E0E3EB] rounded px-3 py-2 font-roboto text-[#2E3A59] focus:border-[#1A73E8] focus:ring-2 focus:ring-[#1A73E8]/20 outline-none" required>
          </div>
          <div class="flex items-center mb-6">
            <input v-model="form.remember" type="checkbox" id="remember" class="mr-2 accent-[#1A73E8]">
            <label for="remember" class="text-sm font-roboto text-[#2E3A59]">Recuérdame</label>
          </div>
          <button type="submit" class="w-full bg-[#1A73E8] text-white py-2 rounded font-poppins font-semibold text-lg hover:bg-[#1765c1] transition">Entrar</button>
        </form>
      </div>
    </div>
  </div>
</template>
