# AGENTS.md

## Stack
Laravel + Inertia + Vue 3 + Tailwind + Vite.

## Rules
- All PRs must be small and focused.
- UI: keep existing styles/layouts for admin/tournament pages.
- Timezone: always show times in UTC+10 using shared datetime helpers (no raw toLocaleString/toLocaleDateString without timeZone).
- Do not delete participants; use is_active / soft-deactivate.

## Canonical file
- resources/js/Pages/Tournament/Show.vue is canonical; avoid breaking its layout/behavior.
