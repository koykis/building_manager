<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Pie } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js'

ChartJS.register(ArcElement, Tooltip)
const props = defineProps({ items: { type: Array, required: true } })
const { t, locale } = useI18n()
const hidden = ref(new Set())
const ascending = ref(false)
const sortedItems = computed(() => [...props.items].sort((a, b) => ascending.value ? Number(a.amount) - Number(b.amount) : Number(b.amount) - Number(a.amount)))
const money = value => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR' }).format(value)
const colors = ['#246c54', '#c18b32', '#527cb0', '#b86359', '#8572a9', '#5c9998', '#9b774e', '#9c5076', '#94a449', '#4a647b', '#d39c7e', '#738b65', '#b59cce', '#65a9c0', '#ccac50']
const color = id => colors[(Number(id) - 1) % colors.length]
const chartData = computed(() => ({
  labels: sortedItems.value.map(item => item.label),
  datasets: [{
    data: sortedItems.value.map(item => hidden.value.has(item.id) ? 0 : item.amount),
    backgroundColor: sortedItems.value.map(item => color(item.id)),
    borderColor: '#ffffff',
    borderWidth: 2,
  }],
}))
const options = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: context => `${context.label}: ${money(context.parsed)}` } },
  },
}))
function toggle(id) {
  const next = new Set(hidden.value)
  next.has(id) ? next.delete(id) : next.add(id)
  hidden.value = next
}
</script>

<template>
  <div class="category-pie">
    <div class="category-sort">
      <button type="button" :aria-label="t('sortByAmount')" :aria-pressed="ascending" @click="ascending = !ascending">
        <span aria-hidden="true">{{ ascending ? '↑' : '↓' }}</span>
        {{ t('sortByAmount') }}: {{ t(ascending ? 'ascending' : 'descending') }}
      </button>
    </div>
    <div class="category-pie-canvas">
      <Pie :data="chartData" :options="options" :aria-label="t('categories')" role="img" />
    </div>
    <p class="muted">{{ t('categoryLegendHint') }}</p>
    <ul class="category-legend" :aria-label="t('categories')">
      <li v-for="item in sortedItems" :key="item.id">
        <button type="button" :aria-pressed="!hidden.has(item.id)" @click="toggle(item.id)">
          <span class="category-swatch" :style="{ backgroundColor: color(item.id) }" aria-hidden="true" />
          <span class="category-name">{{ item.label }}</span>
          <span class="category-amount">{{ money(item.amount) }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.category-sort { display: flex; justify-content: flex-end; margin-bottom: .75rem; }
.category-pie-canvas { height: 260px; max-width: 340px; margin: 0 auto; }
.category-legend { list-style: none; padding: 0; margin: 0; display: grid; gap: .2rem; }
.category-legend button { display: flex; gap: .6rem; align-items: center; width: 100%; padding: .5rem; border-color: transparent; text-align: left; background: transparent; }
.category-legend button:hover { background: #f4f7f1; border-color: #cbd4ca; }
.category-swatch { width: .8rem; height: .8rem; border-radius: 3px; flex-shrink: 0; }
.category-name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.category-amount { white-space: nowrap; font-variant-numeric: tabular-nums; font-size: .85rem; }
.category-legend button[aria-pressed="false"] .category-name { text-decoration: line-through; color: #758176; }
.category-legend button[aria-pressed="false"] .category-swatch { opacity: .3; }
@media (min-width: 1200px) { .category-legend { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
