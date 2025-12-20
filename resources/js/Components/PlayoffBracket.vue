<script setup>
import { computed } from 'vue'

const props = defineProps({
  // просто передай сюда объект турнира целиком
  tournament: { type: Object, default: () => ({}) },
})

/* --- НОРМАЛИЗАЦИЯ ДАННЫХ ПЛЕЙ-ОФФ --- 
   Поддерживает:
   - tournament.playoff.rounds
   - tournament.playoff_stages
   - tournament.stages (фильтруем только плей-офф по названию/типу)
   - плоские массивы матчей с полем round/stage
*/
const rounds = computed(() => {
  const t = props.tournament || {}

  const KNOWN_ROUNDS = ['1/16','1/8','1/4','1/2','Финал','Final']
  const isPlayoffStageName = (name='') =>
    KNOWN_ROUNDS.includes(name.trim()) ||
    /финал|плей-офф|плейофф/i.test(name)

  const src =
    t?.playoff?.rounds ? { type:'rounds', data: t.playoff.rounds } :
    Array.isArray(t?.playoff_stages) ? { type:'stages', data: t.playoff_stages } :
    Array.isArray(t?.stages) ? { type:'stages', data: t.stages } :
    null

  const toAB = (m) => ({
    a: {
      name: m?.a?.name ?? m?.home?.name ?? m?.home_name ?? m?.player1 ?? m?.playerA ?? m?.first ?? '',
      score: m?.a?.score ?? m?.home_score ?? m?.score1 ?? m?.score_a ?? 0,
      logo: m?.a?.logo ?? m?.home?.logo ?? m?.home_logo ?? null,
    },
    b: {
      name: m?.b?.name ?? m?.away?.name ?? m?.away_name ?? m?.player2 ?? m?.playerB ?? m?.second ?? '',
      score: m?.b?.score ?? m?.away_score ?? m?.score2 ?? m?.score_b ?? 0,
      logo: m?.b?.logo ?? m?.away?.logo ?? m?.away_logo ?? null,
    },
  })

  if (!src) return []

  // 1) уже «rounds»
  if (src.type === 'rounds') {
    return (src.data || []).map(r => ({
      name: r?.name || null,
      bestOf: r?.bestOf || r?.best_of || null,
      matches: Array.isArray(r?.matches) ? r.matches.map(toAB) : [],
    }))
  }

  // 2) массив стадий: берём только плей-офф (исключаем «Регулярка» и пр.)
  if (src.type === 'stages') {
    const onlyPlayoff = (src.data || []).filter(s => {
      const n = s?.name || s?.title || s?.round || ''
      const typ = s?.type || s?.kind || ''
      return isPlayoffStageName(String(n)) || /playoff|knockout/i.test(String(typ))
    })

    // если внутри уже есть matches — используем как rounds
    if (onlyPlayoff.some(s => Array.isArray(s?.matches))) {
      return onlyPlayoff
        .map(s => ({
          name: s?.name || s?.title || null,
          bestOf: s?.bestOf || s?.best_of || null,
          matches: (s?.matches || []).map(toAB),
        }))
        // сортировка по известному порядку раундов (от 1/4 к финалу)
        .sort((a,b) => KNOWN_ROUNDS.indexOf(a.name) - KNOWN_ROUNDS.indexOf(b.name))
    }

    // иначе — возможно плоский список матчей; группируем по round/stage
    const by = m => (m?.round_name ?? m?.round ?? m?.stage_name ?? m?.stage ?? m?.title ?? '').trim()
    const map = new Map()
    for (const s of onlyPlayoff) {
      for (const m of (s?.games || s?.matches || [])) {
        const k = isPlayoffStageName(by(m)) ? by(m) : (s?.name || s?.title || 'Раунд')
        if (!map.has(k)) map.set(k, [])
        map.get(k).push(toAB(m))
      }
    }
    return Array.from(map.entries())
      .map(([name, matches]) => ({ name, matches }))
      .sort((a,b) => KNOWN_ROUNDS.indexOf(a.name) - KNOWN_ROUNDS.indexOf(b.name))
  }

  return []
})

/* Заголовки по умолчанию */
const titles = computed(() => {
  const n = rounds.value.length
  const defaults = ['Финал','1/2','1/4','1/8','1/16']
  return rounds.value.map((r,i) => r?.name || defaults[n - 1 - i] || `Раунд ${i+1}`)
})

/* Для «ступеньки»: чем правее, тем больше span */
const rowSpan = (rIndex) => Math.max(1, 2 ** rIndex)
</script>

<template>
  <div v-if="rounds.length" class="bracket-grid" :style="{ gridTemplateColumns: `repeat(${rounds.length}, minmax(260px,1fr))` }">
    <div v-for="(round, rIdx) in rounds" :key="rIdx" class="round-col">
      <div class="round-title">{{ titles[rIdx] }}</div>

      <div class="round-grid">
        <div
          v-for="(m, i) in round.matches"
          :key="i"
          class="match-card"
          :style="{ gridRowEnd: `span ${rowSpan(rIdx)}` }"
        >
          <div class="side">
            <div class="team">
              <img v-if="m?.a?.logo" :src="m.a.logo" class="logo" alt="">
              <span class="name" :title="m?.a?.name">{{ m?.a?.name || '—' }}</span>
            </div>
            <div class="score">{{ m?.a?.score ?? 0 }}</div>
          </div>
          <div class="divider"></div>
          <div class="side">
            <div class="team">
              <img v-if="m?.b?.logo" :src="m.b.logo" class="logo" alt="">
              <span class="name" :title="m?.b?.name">{{ m?.b?.name || '—' }}</span>
            </div>
            <div class="score">{{ m?.b?.score ?? 0 }}</div>
          </div>
        </div>
      </div>

      <div v-if="round?.bestOf" class="series-note">Серия: до {{ round.bestOf }}</div>
    </div>
  </div>

  <p v-else class="muted">Данные плей-офф пока отсутствуют.</p>
</template>

<style scoped>
:root {
  --card-h: 74px;
  --gap-x: 24px;
  --gap-y: 16px;
  --score-w: 40px;
}
/* сетка колонок */
.bracket-grid { display:grid; gap:var(--gap-x); align-items:start; width:100%; }

/* колонка раунда */
.round-col { display:flex; flex-direction:column; gap:var(--gap-y); }

/* заголовок 1/4, 1/2, Финал */
.round-title {
  background:#0f172a; color:#fff; font-weight:700; text-align:center;
  padding:8px 12px; border-radius:10px;
}

/* «ступеньки» */
.round-grid { display:grid; grid-auto-rows:var(--card-h); row-gap:var(--gap-y); }

/* карточка матча */
.match-card {
  display:grid; grid-template-rows:1fr auto 1fr;
  background:#fff; border:1px solid #e5e7eb; border-radius:14px;
  box-shadow:0 1px 2px rgba(0,0,0,.04); overflow:hidden;
}
.side {
  display:grid; grid-template-columns:1fr var(--score-w); align-items:center;
  gap:10px; padding:10px 12px;
}
.team { display:inline-flex; align-items:center; gap:10px; min-width:0; }
.logo { width:22px; height:22px; border-radius:50%; object-fit:cover; }
.name { font-weight:600; font-size:14px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.score {
  justify-self:end; width:var(--score-w); text-align:center; font-weight:800;
  background:#f5e51d; border-left:1px solid #e5e7eb; padding:6px 0; border-radius:8px;
}
.divider { height:1px; background:#f1f5f9; margin:0 12px; }
.series-note { margin-top:2px; color:#64748b; font-size:12px; }

.muted { color:#64748b; }
@media (max-width:1024px){ :root { --card-h:68px; } }
</style>
