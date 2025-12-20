<script setup>
import { computed } from 'vue'

const props = defineProps({
  // rounds: [{ name?: '1/4', matches: [{ a:{name, score, logo?}, b:{...} }] }, ...]
  rounds: { type: Array, default: () => [] }
})

/** Заголовки колонок (если names не переданы) */
const titles = computed(() => {
  const n = props.rounds.length
  // от финала к 1/16
  const map = ['Финал', '1/2', '1/4', '1/8', '1/16', '1/32']
  return Array.from({ length: n }, (_, i) => props.rounds[i]?.name || map[n - 1 - i] || `Раунд ${i + 1}`)
})

/** Растяжение карточек по строкам, чтобы центрировались относительно пар */
const rowSpan = (roundIndex) => Math.max(1, 2 ** roundIndex)
</script>

<template>
  <section class="bracket">
    <div
      class="bracket-grid"
      :style="{ gridTemplateColumns: `repeat(${Math.max(1, rounds.length)}, minmax(260px, 1fr))` }"
    >
      <!-- Колонки раундов -->
      <div v-for="(round, rIdx) in rounds" :key="rIdx" class="round-col">
        <!-- Заголовок раунда -->
        <div class="round-title">
          {{ titles[rIdx] }}
        </div>

        <!-- Матчи раунда -->
        <div class="round-grid">
          <div
            v-for="(m, mIdx) in (round?.matches || [])"
            :key="mIdx"
            class="match-card"
            :style="{ gridRowEnd: `span ${rowSpan(rIdx)}` }"
          >
            <div class="side">
              <div class="team">
                <img v-if="m?.a?.logo" :src="m.a.logo" alt="" class="logo" />
                <span class="name" :title="m?.a?.name">{{ m?.a?.name || '—' }}</span>
              </div>
              <div class="score">{{ m?.a?.score ?? 0 }}</div>
            </div>

            <div class="divider"></div>

            <div class="side">
              <div class="team">
                <img v-if="m?.b?.logo" :src="m.b.logo" alt="" class="logo" />
                <span class="name" :title="m?.b?.name">{{ m?.b?.name || '—' }}</span>
              </div>
              <div class="score">{{ m?.b?.score ?? 0 }}</div>
            </div>
          </div>
        </div>

        <!-- подпись серии, если есть -->
        <div v-if="round?.bestOf" class="series-note">
          Серия: до {{ round.bestOf }}
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Геометрия */
:root {
  --card-h: 74px;      /* высота карточки матча (2 строки) */
  --gap-y: 16px;       /* вертикальный шаг */
  --gap-x: 24px;       /* горизонтальный шаг между колонками */
  --score-w: 40px;     /* ширина блока со счётом */
}
.bracket {
  width: 100%;
}
.bracket-grid {
  display: grid;
  gap: var(--gap-x);
  align-items: start;
}
.round-col {
  display: flex;
  flex-direction: column;
  gap: var(--gap-y);
}
.round-title {
  background: #0f172a;            /* slate-900 */
  color: #fff;
  font-weight: 700;
  text-align: center;
  padding: 8px 12px;
  border-radius: 10px;
}
.round-grid {
  /* сетка из «ячейка-ступенька» */
  display: grid;
  grid-auto-rows: var(--card-h);
  row-gap: var(--gap-y);
}
.match-card {
  display: grid;
  grid-template-rows: 1fr auto 1fr; /* верхняя команда, разделитель, нижняя команда */
  background: #fff;
  border: 1px solid #e5e7eb;  /* slate-200 */
  border-radius: 14px;
  box-shadow: 0 1px 2px rgba(0,0,0,.04);
  overflow: hidden;
}
.side {
  display: grid;
  grid-template-columns: 1fr var(--score-w);
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
}
.team {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.logo {
  width: 22px; height: 22px;
  border-radius: 50%;
  object-fit: cover;
  flex: 0 0 auto;
}
.name {
  font-weight: 600;
  font-size: 14px;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.score {
  justify-self: end;
  width: var(--score-w);
  text-align: center;
  font-weight: 800;
  background: #f5e51d;       /* жёлтый как в примере */
  border-left: 1px solid #e5e7eb;
  padding: 6px 0;
  border-radius: 8px;
}
.divider {
  height: 1px;
  background: #f1f5f9;        /* slate-100 */
  margin: 0 12px;
}
.series-note {
  margin-top: 2px;
  color: #64748b;             /* slate-500 */
  font-size: 12px;
  text-align: left;
}
@media (max-width: 1024px) {
  :root { --card-h: 68px; }
}
</style>
