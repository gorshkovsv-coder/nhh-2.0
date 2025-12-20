<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const page = usePage()

const auth = computed(() => page.props?.auth ?? {})
const user = computed(() => auth.value?.user ?? null)
const isAuth = computed(() => !!user.value)

const isAdmin = computed(() => {
  const u = user.value || {}
  if (u.is_admin === true || u.isAdmin === true) return true
  if (Array.isArray(u.roles) && u.roles.includes('admin')) return true

  const ap = auth.value || {}
  if (ap.is_admin === true || ap.isAdmin === true) return true
  if (Array.isArray(ap.roles) && ap.roles.includes('admin')) return true

  return false
})

const userName = computed(
  () =>
    user.value?.name ||
    user.value?.username ||
    user.value?.email ||
    'Профиль',
)

const currentPath = computed(() => (page.url || '/').split('?')[0])

const navClass = (href) => {
  const isActive = currentPath.value === href
  return [
    'px-3 py-2 rounded-xl text-sm transition',
    isActive
      ? 'font-semibold text-gray-900 bg-gray-100'
      : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50',
  ].join(' ')
}

const adminMenuOpen = ref(false)
const toggleAdminMenu = () => {
  adminMenuOpen.value = !adminMenuOpen.value
}
const closeAdminMenu = () => {
  adminMenuOpen.value = false
}

const userMenuOpen = ref(false)

const toggleUserMenu = () => {
  userMenuOpen.value = !userMenuOpen.value
}

const closeUserMenu = () => {
  userMenuOpen.value = false
}
</script>

<template> 
  <nav class="border-b bg-white/80 backdrop-blur">
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
	  <div class="flex items-center justify-between gap-3 py-2">
	  
        <!-- Left side -->
		<div class="flex-1">
		   <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <template v-if="isAuth">
              <Link :href="'/'" :class="navClass('/')">Главная</Link>
              <Link
                :href="'/tournaments'"
                :class="navClass('/tournaments')"
              >
                Турниры
              </Link>
              <Link
                :href="'/rating'"
                :class="navClass('/rating')"
              >
                Рейтинг игроков
              </Link>
			  
			  <Link
              :href="'/my/matches'"
              :class="navClass('/my/matches')"
             >
               Мои матчи
              </Link>

              <!-- Админка -->
              <div v-if="isAdmin" class="relative">
                <button
                  type="button"
                  class="inline-flex items-center px-3 py-2 rounded-xl text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-50"
                  @click="toggleAdminMenu"
                >
                  Админка
                  <svg
                    class="ml-1 h-4 w-4"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M5.23 7.21a.75.75 0 011.06.02L10 11.178l3.71-3.947a.75.75 0 111.1 1.02l-4.25 4.52a.75.75 0 01-1.1 0l-4.25-4.52a.75.75 0 01.02-1.06z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </button>

                <!-- Выпадающее меню -->
                <div
                  v-if="adminMenuOpen"
				  class="absolute left-0 top-full mt-1 w-48 rounded-xl bg-white shadow-lg ring-1 ring-black/5 z-30"
                >
                  <div class="py-1">
                    <Link
                      href="/admin/tournaments"
                      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                      @click="closeAdminMenu"
                    >
                      Турниры
                    </Link>
                    <Link
                      href="/admin/nhl-teams"
                      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                      @click="closeAdminMenu"
                    >
                      Команды
                    </Link>
                    <Link
                      href="/admin/matches"
                      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                      @click="closeAdminMenu"
                    >
                      Матчи
                    </Link>
                    <Link
                      href="/admin/users"
                      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                      @click="closeAdminMenu"
                    >
                      Пользователи
                    </Link>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>


<!-- Right side -->
<div class="flex items-center gap-2 shrink-0">
  <!-- Авторизованный пользователь -->
  <template v-if="isAuth">
    <div class="relative">
      <button
        type="button"
        class="inline-flex items-center max-w-[160px] sm:max-w-none rounded-full bg-slate-900 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500"
        @click="toggleUserMenu"
      >
        <span class="truncate">
          {{ user.name }}
        </span>
        <svg
          class="ml-2 h-4 w-4 shrink-0"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M5.23 7.21a.75.75 0 011.06.02L10 11.178l3.71-3.947a.75.75 0 111.1 1.02l-4.25 4.52a.75.75 0 01-1.1 0l-4.25-4.52a.75.75 0 01.02-1.06z"
            clip-rule="evenodd"
          />
        </svg>
      </button>

      <!-- Выпадающее меню пользователя -->
      <div
        v-if="userMenuOpen"
        class="absolute right-0 mt-2 w-48 rounded-xl bg-white shadow-lg ring-1 ring-black/5 z-40"
      >
        <div class="px-4 py-2 text-sm font-semibold text-gray-900 border-b border-gray-100">
          {{ user.name }}
        </div>
        <div class="py-1">
          <Link
            href="/profile"
            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="closeUserMenu"
          >
            Профиль
          </Link>
          <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="block w-full text-left px-4 py-2 text-sm text-rose-600 hover:bg-rose-50"
            @click="closeUserMenu"
          >
            Выйти
          </Link>
        </div>
      </div>
    </div>
  </template>

  <!-- Не авторизован -->
  <template v-else>
    <Link
      href="/login"
      class="text-sm text-gray-600 hover:text-gray-900"
    >
      Войти
    </Link>
  </template>
</div>

      </div>
    </div>
  </nav>
</template>
