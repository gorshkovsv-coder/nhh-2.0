const TZ = import.meta.env.VITE_APP_TIMEZONE || 'Australia/Brisbane'
const TZ_LABEL = import.meta.env.VITE_APP_TIMEZONE_LABEL || 'UTC+10'

// Нормализуем строки дат, чтобы "2025-12-16 04:32:20" трактовалось как UTC
function normalize(value) {
  if (!value) return null
  if (value instanceof Date) return value

  if (typeof value === 'string') {
    // Если уже ISO со смещением/ Z — оставляем как есть
    const hasTz = /[zZ]$|[+-]\d{2}:\d{2}$/.test(value)
    if (hasTz) return new Date(value)

    // Laravel иногда отдаёт "YYYY-MM-DD HH:mm:ss" — считаем это UTC
    const iso = value.replace(' ', 'T') + 'Z'
    return new Date(iso)
  }

  return new Date(value)
}

export function formatDateTime(value) {
  const d = normalize(value)
  if (!d || Number.isNaN(d.getTime())) return value ?? '—'

  const s = new Intl.DateTimeFormat('ru-RU', {
    timeZone: TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(d)

  return `${s} (${TZ_LABEL})`
}

export function formatDate(value) {
  const d = normalize(value)
  if (!d || Number.isNaN(d.getTime())) return value ?? '—'

  const s = new Intl.DateTimeFormat('ru-RU', {
    timeZone: TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(d)

  return `${s} (${TZ_LABEL})`
}
