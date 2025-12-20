<script setup>
import { Link } from '@inertiajs/vue3'
import TopNav from '@/Components/TopNav.vue'
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <TopNav />

    <main>
      <slot />
    </main>
  </div>
</template>
