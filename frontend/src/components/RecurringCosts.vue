<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip } from 'chart.js'
import { api, errorText } from '../api'
import { monthlyCostStatistics } from '../recurringCosts'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip)
const props = defineProps({
  months: { type: Array, required: true },
  categories: { type: Array, required: true },
  first: { type: String, default: '' },
  last: { type: String, default: '' },
})
const { t, locale } = useI18n()
const history = ref(null)
const loading = ref(false)
const error = ref('')
const scope = ref('history')
const category = ref('')
const availableCategories = computed(() => history.value?.categories || [])
const selectedStatistics = computed(() => monthlyCostStatistics(props.months, category.value))
const statistics = computed(() => scope.value === 'history'
  ? (history.value?.statistics?.[category.value]?.months || monthlyCostStatistics([], category.value)).map(month => ({ ...month, estimatedCount: month.estimated_count || 0 }))
  : selectedStatistics.value)
const entries = computed(() => selectedStatistics.value.flatMap(month => month.entries))
const sampleCount = computed(() => scope.value === 'history' ? history.value?.coverage?.published || 0 : entries.value.length)
const summary = computed(() => {
  if (scope.value === 'history') return history.value?.statistics?.[category.value]?.summary || null
  const amounts = entries.value.map(entry => entry.amount)
  return amounts.length ? {
    average: amounts.reduce((sum, amount) => sum + amount, 0) / amounts.length,
    min: Math.min(...amounts),
    max: Math.max(...amounts),
  } : null
})
const estimatedCount = computed(() => scope.value === 'history' ? history.value?.coverage?.estimated || 0 : entries.value.filter(entry => entry.estimated).length)
const missingCount = computed(() => scope.value === 'history' ? (history.value?.coverage?.expected || 0) - sampleCount.value : props.months.filter(month => !month.available).length)
const money = value => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR' }).format(value)
const monthLabel = (month, format = 'short') => new Intl.DateTimeFormat(locale.value, { month: format }).format(new Date(2024, month - 1, 1))
const categoryName = item => locale.value === 'el' ? item.name_el : item.name_en
const rangeLabel = computed(() => scope.value === 'history' ? `${history.value?.from || ''} – ${history.value?.to || ''}` : props.months.length ? `${props.months[0].period} – ${props.months.at(-1).period}` : '')
const chartData = computed(() => ({
  labels: statistics.value.map(month => monthLabel(month.month)),
  datasets: [
    { label: t('maximumCost'), data: statistics.value.map(month => month.max), borderColor: '#a1bdad', backgroundColor: 'rgba(36,108,84,.13)', borderWidth: 1, pointRadius: 0, fill: { target: 1 }, order: 2 },
    { label: t('minimumCost'), data: statistics.value.map(month => month.min), borderColor: '#a1bdad', borderWidth: 1, pointRadius: 0, order: 2 },
    { label: t('averageCost'), data: statistics.value.map(month => month.average), borderColor: '#246c54', backgroundColor: '#246c54', borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6, order: 1 },
  ],
}))
const options = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: false,
  interaction: { mode: 'index', intersect: false },
  scales: { y: { beginAtZero: true, ticks: { callback: value => money(value) } }, x: { grid: { display: false } } },
  plugins: {
    legend: { display: false },
    tooltip: {
      filter: item => item.datasetIndex === 2,
      callbacks: {
        title: items => monthLabel(statistics.value[items[0].dataIndex].month, 'long'),
        label: item => {
          const month = statistics.value[item.dataIndex]
          return [
            `${t('averageCost')}: ${money(month.average)}`,
            `${t('minimumCost')}: ${money(month.min)}`,
            `${t('maximumCost')}: ${money(month.max)}`,
            t('monthlySamples', { count: month.count }),
            ...(month.estimatedCount ? [t('estimatedSamples', { count: month.estimatedCount })] : []),
          ]
        },
      },
    },
  },
}))

async function loadHistory() {
  if (!props.first || !props.last || !props.categories.length) return
  loading.value = true
  error.value = ''
  try {
    history.value = (await api.get('/reports/recurring', { params: { from: props.first, to: props.last } })).data
  } catch (failure) {
    error.value = errorText(failure)
  } finally {
    loading.value = false
  }
}
watch(() => [props.first, props.last], loadHistory, { immediate: true })
watch(availableCategories, items => {
  if (!items.some(item => String(item.id) === category.value)) category.value = items.length ? String(items[0].id) : ''
}, { immediate: true })
</script>

<template>
  <section class="card recurring-costs" :aria-label="t('recurringCosts')" :aria-busy="loading">
    <div class="toolbar recurring-toolbar">
      <div>
        <h2>{{ t('recurringCosts') }}</h2>
        <p class="muted recurring-intro">{{ t('recurringCostsHint') }}</p>
      </div>
      <div v-if="availableCategories.length" class="recurring-selectors">
        <label>{{ t('recurringExpense') }}
          <select v-model="category" :aria-label="t('recurringExpense')">
            <option v-for="item in availableCategories" :key="item.id" :value="String(item.id)">{{ categoryName(item) }}</option>
          </select>
        </label>
        <label>{{ t('statisticsPeriod') }}
          <select v-model="scope" :aria-label="t('statisticsPeriod')">
            <option value="history">{{ t('allHistory') }}</option>
            <option value="selected">{{ t('selectedReportingPeriod') }}</option>
          </select>
        </label>
      </div>
    </div>
    <p v-if="loading" class="muted" role="status">{{ t('loading') }}</p>
    <div v-else-if="error" class="error" role="alert">{{ error }} <button type="button" @click="loadHistory">{{ t('refresh') }}</button></div>
    <p v-else-if="!availableCategories.length" class="muted">{{ t('noRecurringCosts') }}</p>
    <template v-else>
      <p class="muted recurring-coverage">{{ rangeLabel }} · {{ t('monthlySamples', { count: sampleCount }) }}<span v-if="missingCount"> · {{ t('excludedMonths', { count: missingCount }) }}</span></p>
      <template v-if="summary">
        <dl class="recurring-summary">
          <div v-for="metric in ['average', 'min', 'max']" :key="metric">
            <dt>{{ t({ average: 'averageMonthlyCost', min: 'minimumMonthlyCost', max: 'maximumMonthlyCost' }[metric]) }}</dt>
            <dd>{{ money(summary[metric]) }}</dd>
          </div>
        </dl>
        <div class="recurring-legend" aria-hidden="true">
          <span><i class="average-key" />{{ t('averageCost') }}</span>
          <span><i class="range-key" />{{ t('costRange') }}</span>
        </div>
        <div class="recurring-chart">
          <Line :data="chartData" :options="options" :aria-label="t('recurringChartLabel')" role="img" />
        </div>
        <p v-if="estimatedCount" class="muted">{{ t('estimatedSamples', { count: estimatedCount }) }}</p>
        <details class="recurring-details">
          <summary>{{ t('monthlyFigures') }}</summary>
          <div class="scroll" tabindex="0" :aria-label="t('monthlyFigures')">
            <table>
              <thead><tr><th>{{ t('period') }}</th><th class="money">{{ t('averageCost') }}</th><th class="money">{{ t('minimumCost') }}</th><th class="money">{{ t('maximumCost') }}</th><th>{{ t('samples') }}</th></tr></thead>
              <tbody>
                <tr v-for="month in statistics" :key="month.month">
                  <th scope="row">{{ monthLabel(month.month, 'long') }}</th>
                  <template v-if="month.count"><td class="money">{{ money(month.average) }}</td><td class="money">{{ money(month.min) }}</td><td class="money">{{ money(month.max) }}</td><td>{{ month.count }}<small v-if="month.estimatedCount" class="sample-estimate">{{ t('estimatedSamples', { count: month.estimatedCount }) }}</small></td></template>
                  <td v-else colspan="4" class="muted">{{ t('missing') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </details>
      </template>
      <p v-else class="muted">{{ t('empty') }}</p>
      <details class="recurring-method"><summary>{{ t('calculationMethod') }}</summary><p class="muted">{{ t('recurringMethodHint') }}</p></details>
    </template>
  </section>
</template>

<style scoped>
.recurring-toolbar { align-items: flex-start; margin-bottom: .8rem; }
.recurring-toolbar h2 { margin: 0 0 .4rem; }
.recurring-intro { margin: 0; max-width: 36rem; }
.recurring-selectors { display: flex; gap: .75rem; flex-wrap: wrap; }
.recurring-selectors label { margin: 0; }
.recurring-selectors select { width: 100%; max-width: 22rem; }
.recurring-coverage { margin: .6rem 0 1rem; }
.recurring-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin: 0 0 1.2rem; }
.recurring-summary div { background: #f6f8f3; border-radius: 8px; padding: .85rem 1rem; }
.recurring-summary dt { color: #64786b; font-size: .85rem; }
.recurring-summary dd { margin: .3rem 0 0; font-size: 1.5rem; font-weight: 600; font-variant-numeric: tabular-nums; }
.recurring-legend { display: flex; gap: 1.2rem; flex-wrap: wrap; font-size: .85rem; margin-bottom: .75rem; }
.recurring-legend span { display: flex; align-items: center; gap: .5rem; }
.recurring-legend i { display: inline-block; width: 1.4rem; }
.average-key { border-top: 3px solid #246c54; }
.range-key { height: .85rem; background: rgba(36,108,84,.13); border: 1px solid #a1bdad; border-radius: 2px; }
.recurring-chart { height: 300px; }
.recurring-details { margin-top: 1rem; }
.recurring-method { margin-top: .75rem; }
summary { cursor: pointer; font-size: .85rem; color: #477060; }
.recurring-details .scroll { margin-top: .75rem; }
.sample-estimate { display: block; color: #735a22; font-size: .75rem; white-space: normal; }
@media (max-width: 600px) {
  .recurring-selectors { width: 100%; }
  .recurring-selectors label { width: 100%; }
  .recurring-selectors select { max-width: none; }
  .recurring-summary { gap: .4rem; }
  .recurring-summary div { padding: .65rem .5rem; }
  .recurring-summary dt { font-size: .75rem; }
  .recurring-summary dd { font-size: 1.05rem; overflow-wrap: anywhere; }
  .recurring-chart { height: 260px; }
}
</style>
