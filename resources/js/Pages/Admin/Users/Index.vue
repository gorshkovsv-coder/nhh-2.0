<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import { formatDateTime } from '@/utils/datetime'

const props = defineProps({
  users: {
    type: Object,
    default: () => ({
      data: [],
      links: [],
      meta: {},
    }),
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
})

const search = ref(props.filters.search || '')

/**
 * Аккуратное приведение пропса к форме пагинатора:
 * { data: [], links: [], meta: {} }
 */
const users = computed(() => {
  const raw = props.users

  // Ожидаем объект-пагинатор
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    // Если meta есть (например, из API-ресурса) — берём его
    const metaFromResource = raw.meta ?? {}

    // Если meta нет, но есть "классические" поля пагинатора Laravel
    const metaFallback = {
      current_page: raw.current_page ?? 1,
      last_page: raw.last_page ?? 1,
      per_page: raw.per_page ?? (raw.data?.length ?? 0),
      total: raw.total ?? (raw.data?.length ?? 0),
      from: raw.from ?? 1,
      to: raw.to ?? (raw.data?.length ?? 0),
    }

    return {
      data: raw.data ?? [],
      links: raw.links ?? [],
      meta: Object.keys(metaFromResource).length ? metaFromResource : metaFallback,
    }
  }

  // На всякий случай, если вдруг придёт просто массив
  return {
    data: Array.isArray(raw) ? raw : [],
    links: [],
    meta: {
      current_page: 1,
      last_page: 1,
      total: Array.isArray(raw) ? raw.length : 0,
    },
  }
})


const usersItems = computed(() => users.value.data)
const usersLinks = computed(() => users.value.links)
const usersMeta = computed(() => users.value.meta ?? {})
const hasUsers = computed(() => usersItems.value.length > 0)

/* ---------- поиск ---------- */

const applySearch = () => {
  router.get(
    '/admin/users',
    {
      search: search.value || '',
    },
    {
      preserveState: true,
      replace: true,
      preserveScroll: true,
    },
  )
}

const resetSearch = () => {
  search.value = ''
  applySearch()
}

/* ---------- создание / удаление / верификация одного пользователя ---------- */

const newUser = reactive({
  name: '',
  email: '',
  psn: '',
  password: '',
})

const createUser = () => {
  if (!newUser.name || !newUser.email || !newUser.password) {
    alert('Имя, email и пароль обязательны')
    return
  }

  router.post(
    '/admin/users',
    {
      name: newUser.name,
      email: newUser.email,
      psn: newUser.psn,
      password: newUser.password,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        newUser.name = ''
        newUser.email = ''
        newUser.psn = ''
        newUser.password = ''
      },
    },
  )
}

const deleteUser = (user) => {
  if (!confirm(`Удалить пользователя #${user.id} (${user.email})?`)) return

  router.delete(`/admin/users/${user.id}`, {
    preserveScroll: true,
  })
}

const verifyUser = (user) => {
  if (!confirm(`Подтвердить email пользователя #${user.id} (${user.email})?`)) return

  router.post(
    `/admin/users/${user.id}/verify`,
    {},
    {
      preserveScroll: true,
    },
  )
}

const isVerified = (user) => !!user.email_verified_at

/* ---------- массовые действия ---------- */

const selectedIds = ref([])

const allOnPageSelected = computed(
  () =>
    usersItems.value.length > 0 &&
    selectedIds.value.length === usersItems.value.length,
)

const isIndeterminate = computed(
  () =>
    selectedIds.value.length > 0 &&
    selectedIds.value.length < usersItems.value.length,
)

const toggleSelectAll = (event) => {
  if (event.target.checked) {
    selectedIds.value = usersItems.value.map((u) => u.id)
  } else {
    selectedIds.value = []
  }
}

const toggleSelectUser = (id) => {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((x) => x !== id)
  } else {
    selectedIds.value.push(id)
  }
}

const selectedCount = computed(() => selectedIds.value.length)

const bulkVerify = () => {
  if (!selectedIds.value.length) {
    alert('Выберите хотя бы одного пользователя')
    return
  }

  if (
    !confirm(
      `Подтвердить email у ${selectedIds.value.length} выбранных пользователей?`,
    )
  ) {
    return
  }

  router.post(
    '/admin/users/bulk-verify',
    { ids: selectedIds.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        selectedIds.value = []
      },
    },
  )
}

const bulkDelete = () => {
  if (!selectedIds.value.length) {
    alert('Выберите хотя бы одного пользователя')
    return
  }

  if (
    !confirm(
      `Удалить ${selectedIds.value.length} выбранных пользователей? Это действие нельзя будет отменить.`,
    )
  ) {
    return
  }

  router.post(
    '/admin/users/bulk-delete',
    { ids: selectedIds.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        selectedIds.value = []
      },
    },
  )
}

/* ---------- подписи для кнопок пагинации ---------- */

const pageLabel = (link) => {
  if (!link || !link.label) return ''
  const label = String(link.label).toLowerCase()

  if (label.includes('pagination.previous') || label.includes('previous')) {
    return 'Предыдущая'
  }
  if (label.includes('pagination.next') || label.includes('next')) {
    return 'Следующая'
  }
  return link.label
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Админка — пользователи" />

    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Админка (пользователи)
      </h2>
    </template>

    <main class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Фильтр + форма добавления -->
        <div class="bg-white shadow-sm sm:rounded-xl p-4 sm:p-6 space-y-4">
          <div
            class="flex flex-col sm:flex-row gap-3 sm:items-end sm:justify-between"
          >
            <!-- Поиск -->
            <div class="space-y-1">
              <label class="text-xs font-medium text-gray-500 uppercase">
                Поиск
              </label>
              <div class="flex gap-2">
                <input
                  v-model="search"
                  type="text"
                  placeholder="Имя, email или PSN"
                  class="w-full sm:w-64 rounded-lg border-gray-300 text-sm"
                  @keyup.enter="applySearch"
                />
                <button
                  type="button"
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 text-white hover:bg-slate-900"
                  @click="applySearch"
                >
                  Применить
                </button>
                <button
                  type="button"
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-300 text-gray-700 hover:bg-gray-50"
                  @click="resetSearch"
                >
                  Сбросить
                </button>
              </div>
            </div>

            <div class="text-xs text-gray-500">
              Здесь администратор может создавать пользователей, подтверждать их
              email, а также массово подтверждать и удалять аккаунты.
            </div>
          </div>

          <!-- Форма добавления пользователя -->
          <div class="border-t border-gray-200 pt-4 mt-2">
            <h3 class="text-sm font-medium text-gray-800 mb-3">
              Добавить пользователя
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
              <div>
                <label
                  class="block text-xs font-medium text-gray-500 uppercase mb-1"
                >
                  Имя
                </label>
                <input
                  v-model="newUser.name"
                  type="text"
                  class="w-full rounded-lg border-gray-300 text-sm"
                />
              </div>
              <div>
                <label
                  class="block text-xs font-medium text-gray-500 uppercase mb-1"
                >
                  Email
                </label>
                <input
                  v-model="newUser.email"
                  type="email"
                  class="w-full rounded-lg border-gray-300 text-sm"
                />
              </div>
              <div>
                <label
                  class="block text-xs font-medium text-gray-500 uppercase mb-1"
                >
                  PSN (опционально)
                </label>
                <input
                  v-model="newUser.psn"
                  type="text"
                  class="w-full rounded-lg border-gray-300 text-sm"
                />
              </div>
              <div>
                <label
                  class="block text-xs font-medium text-gray-500 uppercase mb-1"
                >
                  Пароль
                </label>
                <input
                  v-model="newUser.password"
                  type="password"
                  class="w-full rounded-lg border-gray-300 text-sm"
                />
              </div>
            </div>
            <div class="mt-3">
              <button
                type="button"
                class="inline-flex items-center px-4 py-1.5 rounded-lg text-xs font-medium bg-emerald-600 text-white hover:bg-emerald-700"
                @click="createUser"
              >
                Создать пользователя
              </button>
            </div>
          </div>
        </div>

        <!-- Таблица пользователей -->
        <div class="bg-white shadow-sm sm:rounded-xl overflow-hidden">
          <!-- Панель массовых действий -->
          <div
            v-if="selectedCount > 0"
            class="border-b border-gray-200 px-4 py-2 bg-amber-50 flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between text-xs"
          >
            <div class="text-gray-700">
              Выбрано пользователей:
              <strong>{{ selectedCount }}</strong>
            </div>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-sky-600 text-white hover:bg-sky-700"
                @click="bulkVerify"
              >
                Подтвердить email выбранных
              </button>
              <button
                type="button"
                class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border border-red-200 text-red-700 hover:bg-red-50"
                @click="bulkDelete"
              >
                Удалить выбранных
              </button>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">
                    <input
                      type="checkbox"
                      class="rounded border-gray-300"
                      :checked="allOnPageSelected"
                      :indeterminate="isIndeterminate"
                      @change="toggleSelectAll"
                    />
                  </th>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    ID
                  </th>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Имя
                  </th>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Email
                  </th>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    PSN
                  </th>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Email подтверждён
                  </th>
                  <th
                    class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase"
                  >
                    Действия
                  </th>
                </tr>
              </thead>

              <tbody v-if="hasUsers" class="divide-y divide-gray-200 bg-white">
                <tr
                  v-for="user in usersItems"
                  :key="user.id"
                  class="hover:bg-gray-50"
                >
                  <td class="px-3 py-2">
                    <input
                      type="checkbox"
                      class="rounded border-gray-300"
                      :checked="selectedIds.includes(user.id)"
                      @change="toggleSelectUser(user.id)"
                    />
                  </td>
                  <td class="px-3 py-2 text-sm text-gray-500">
                    #{{ user.id }}
                  </td>
                  <td class="px-3 py-2 text-sm text-gray-700">
                    {{ user.name || '—' }}
                  </td>
                  <td class="px-3 py-2 text-sm text-gray-700">
                    {{ user.email }}
                  </td>
                  <td class="px-3 py-2 text-sm text-gray-700">
                    {{ user.psn || '—' }}
                  </td>
                  <td class="px-3 py-2 text-sm">
                    <span
                      v-if="isVerified(user)"
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100"
                    >
                      Да
                      <span class="ml-1 text-[11px] text-emerald-700/70">
                        ({{ formatDateTime(user.email_verified_at) }})
                      </span>
                    </span>
                    <span
                      v-else
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-100"
                    >
                      Нет
                    </span>
                  </td>
                  <td class="px-3 py-2 text-right text-sm">
                    <div class="inline-flex items-center gap-2">
                      <button
                        v-if="!isVerified(user)"
                        type="button"
                        class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium bg-sky-600 text-white hover:bg-sky-700"
                        @click="verifyUser(user)"
                      >
                        Подтвердить email
                      </button>
                      <button
                        type="button"
                        class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium border border-red-200 text-red-700 hover:bg-red-50"
                        @click="deleteUser(user)"
                      >
                        Удалить
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>

              <tbody v-else class="bg-white">
                <tr>
                  <td
                    colspan="7"
                    class="px-4 py-6 text-center text-sm text-gray-500"
                  >
                    Пользователей не найдено.
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Пагинация -->
            <div
              v-if="Array.isArray(usersLinks) && usersLinks.length > 1"
              class="border-t border-gray-200 px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gray-50"
            >
<div class="text-xs text-gray-500">
  Страница
  <strong>{{ usersMeta.current_page ?? 1 }}</strong>
  из
  <strong>{{ usersMeta.last_page ?? 1 }}</strong>
  <span v-if="usersMeta.total">
    · Всего пользователей: {{ usersMeta.total }}
  </span>
</div>

              <nav
                class="inline-flex -space-x-px rounded-md shadow-sm overflow-hidden"
              >
                <template v-for="(link, idx) in usersLinks" :key="idx">
                  <!-- Активные/кликабельные ссылки -->
                  <Link
                    v-if="link && link.url"
                    :href="link.url"
                    preserve-state
                    preserve-scroll
                    class="relative inline-flex items-center px-3 py-1.5 border text-xs font-medium"
                    :class="[
                      link.active
                        ? 'z-10 bg-slate-800 border-slate-800 text-white'
                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50',
                    ]"
                    v-html="pageLabel(link)"
                  />
                  <!-- Неактивные (без url) -->
                  <span
                    v-else-if="link"
                    class="relative inline-flex items-center px-3 py-1.5 border border-gray-200 bg-gray-50 text-xs font-medium text-gray-400"
                    v-html="pageLabel(link)"
                  />
                  <!-- Если вдруг link === undefined/null — просто пропускаем -->
                </template>
              </nav>
            </div>

          </div>
        </div>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
