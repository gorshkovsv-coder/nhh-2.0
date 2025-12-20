<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import TopNav from '@/Components/TopNav.vue'
import { computed } from 'vue'

const page = usePage()
const user = computed(() => page.props?.auth?.user ?? null)

const logout = () => router.post('/logout')
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <TopNav />

    <header v-if="$slots.header" class="bg-white shadow">
      <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <slot name="header" />
      </div>
    </header>

    <main>
      <slot />
    </main>
  </div>
</template>
