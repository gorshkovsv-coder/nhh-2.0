<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
  <Head title="Главная — NHL26" />
  <div class="mx-auto max-w-5xl p-6 lg:p-8">
    <section class="rounded-2xl border p-6 shadow-sm bg-white">
      <h1 class="text-2xl font-bold">Добро пожаловать в NHL26 Tournaments</h1>
      <p class="mt-2 text-sm text-gray-600">Это домашняя страница для авторизованных пользователей.</p>
    </section>
  </div>
</template>
