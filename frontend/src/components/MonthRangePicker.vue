<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { monthAt, monthCount, monthIndex, orderedRange, trailingRange } from '../monthRange'

const props = defineProps({
  from: { type: String, required: true },
  to: { type: String, required: true },
  min: { type: String, required: true },
  max: { type: String, required: true },
  firstAvailable: String,
  lastAvailable: String,
  disabled: Boolean,
})
const emit = defineEmits(['apply'])
const { t, locale } = useI18n()
const id = useId()
const root = ref(null)
const trigger = ref(null)
const panel = ref(null)
const yearSelect = ref(null)
const open = ref(false)
const draftFrom = ref('')
const draftTo = ref('')
const choosingEnd = ref(false)
const hovered = ref('')
const leftYear = ref(0)
const focusedMonth = ref('')
let restorePending = false
const firstYear = computed(() => Number(props.min.slice(0, 4)))
const lastYear = computed(() => Number(props.max.slice(0, 4)))
const lastLeftYear = computed(() => Math.max(firstYear.value, lastYear.value - 1))
const years = computed(() => Array.from({ length: lastLeftYear.value - firstYear.value + 1 }, (_, i) => firstYear.value + i))
const visibleYears = computed(() => firstYear.value === lastYear.value ? [leftYear.value] : [leftYear.value, leftYear.value + 1])
const preview = computed(() => choosingEnd.value
  ? orderedRange(draftFrom.value, hovered.value || draftFrom.value)
  : { from: draftFrom.value, to: draftTo.value })
const selectionCount = computed(() => draftTo.value ? monthCount(draftFrom.value, draftTo.value) : 0)
const presets = [3, 6, 12]
const history = computed(() => ({ from: props.firstAvailable || props.min, to: props.lastAvailable || props.max }))

function date(period) {
  const [year, month] = period.split('-').map(Number)
  return new Date(year, month - 1, 15)
}
function format(period, long = false) {
  return period ? new Intl.DateTimeFormat(locale.value, { month: long ? 'long' : 'short', year: 'numeric' }).format(date(period)) : '—'
}
function months(year) {
  return Array.from({ length: 12 }, (_, i) => {
    const period = `${year}-${String(i + 1).padStart(2, '0')}`
    return { period, label: new Intl.DateTimeFormat(locale.value, { month: 'short' }).format(date(period)) }
  })
}
function available(period) {
  return period >= props.min && period <= props.max
}
function focusPeriod(period) {
  focusedMonth.value = period
  nextTick(() => panel.value?.querySelector(`[data-month="${period}"]`)?.focus())
}
function show() {
  restorePending = false
  draftFrom.value = props.from
  draftTo.value = props.to
  choosingEnd.value = false
  hovered.value = ''
  leftYear.value = Math.max(firstYear.value, Math.min(lastLeftYear.value, Number(props.to.slice(0, 4)) - 1))
  focusedMonth.value = props.to
  open.value = true
  nextTick(() => yearSelect.value?.focus())
}
function close(restoreFocus = true) {
  open.value = false
  restorePending = false
  if (restoreFocus) nextTick(() => {
    if (props.disabled) restorePending = true
    else trigger.value?.focus()
  })
}
function choose(period) {
  focusedMonth.value = period
  hovered.value = ''
  if (!choosingEnd.value) {
    draftFrom.value = period
    draftTo.value = ''
    choosingEnd.value = true
  } else {
    const range = orderedRange(draftFrom.value, period)
    draftFrom.value = range.from
    draftTo.value = range.to
    choosingEnd.value = false
  }
}
function preset(count) {
  const range = count ? trailingRange(count, history.value.from, history.value.to) : history.value
  draftFrom.value = range.from
  draftTo.value = range.to
  choosingEnd.value = false
  hovered.value = ''
  leftYear.value = Math.max(firstYear.value, Math.min(lastLeftYear.value, Number(range.to.slice(0, 4)) - 1))
  focusedMonth.value = range.to
}
function isPreset(count) {
  const range = count ? trailingRange(count, history.value.from, history.value.to) : history.value
  return draftFrom.value === range.from && draftTo.value === range.to
}
function navigate(year) {
  leftYear.value = Number(year)
  focusedMonth.value = monthAt(Math.max(monthIndex(props.min), leftYear.value * 12))
}
function apply() {
  if (!draftTo.value) return
  const range = { from: draftFrom.value, to: draftTo.value }
  close()
  emit('apply', range)
}
function monthKey(event, period) {
  const columns = getComputedStyle(event.currentTarget.parentElement).gridTemplateColumns.split(' ').length
  const offsets = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -columns, ArrowDown: columns }
  let target
  if (event.key in offsets) target = monthAt(monthIndex(period) + offsets[event.key])
  if (event.key === 'Home') target = `${period.slice(0, 4)}-01`
  if (event.key === 'End') target = `${period.slice(0, 4)}-12`
  if (!target) return
  event.preventDefault()
  if (!available(target)) return
  const year = Number(target.slice(0, 4))
  if (!visibleYears.value.includes(year)) navigate(Math.max(firstYear.value, Math.min(lastLeftYear.value, year)))
  if (choosingEnd.value) hovered.value = target
  focusPeriod(target)
}
function panelKey(event) {
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    close()
  }
  if (event.key === 'Tab') {
    const controls = [...panel.value.querySelectorAll('button:not(:disabled), select')].filter(el => el.tabIndex >= 0)
    const first = controls[0]
    const last = controls.at(-1)
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
  }
}
function outside(event) {
  if (open.value && !root.value?.contains(event.target)) close(false)
}
onMounted(() => document.addEventListener('pointerdown', outside))
onUnmounted(() => document.removeEventListener('pointerdown', outside))
watch(() => props.disabled, disabled => {
  if (!disabled && restorePending) {
    restorePending = false
    nextTick(() => trigger.value?.focus())
  }
})
</script>

<template>
  <div ref="root" class="month-range-picker">
    <button ref="trigger" type="button" class="range-trigger" :disabled="disabled" aria-haspopup="dialog" :aria-expanded="open" :aria-controls="`${id}-panel`" @click="open ? close() : show()">
      <svg class="range-calendar-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-13 5h2m4 0h2"/></svg>
      <span class="range-trigger-text"><span class="range-caption">{{ t('reportingPeriod') }}</span><strong>{{ format(from) }} <span class="range-dash">—</span> {{ format(to) }}</strong></span>
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg>
    </button>

    <div v-if="open" :id="`${id}-panel`" ref="panel" class="range-popover" role="dialog" :aria-labelledby="`${id}-title`" :aria-describedby="`${id}-hint`" @keydown="panelKey">
      <div class="range-heading"><h2 :id="`${id}-title`">{{ t('selectMonthRange') }}</h2><button type="button" class="range-close" :aria-label="t('closePicker')" @click="close">×</button></div>
      <p :id="`${id}-hint`" class="range-hint" role="status">{{ choosingEnd ? t('chooseLastMonth') : t('chooseMonthRangeHint') }}</p>
      <div class="range-presets">
        <button v-for="count in presets" :key="count" type="button" :class="{ active: isPreset(count) }" :aria-pressed="isPreset(count)" @click="preset(count)">{{ t('latestMonths', { count }) }}</button>
        <button type="button" :class="{ active: isPreset(0) }" :aria-pressed="isPreset(0)" @click="preset(0)">{{ t('allHistory') }}</button>
      </div>
      <div class="range-selection">
        <div :class="{ pending: !choosingEnd }"><span class="range-caption">{{ t('from') }}</span><strong>{{ format(draftFrom) }}</strong></div>
        <span class="range-selection-arrow" aria-hidden="true">→</span>
        <div :class="{ pending: choosingEnd }"><span class="range-caption">{{ t('to') }}</span><strong>{{ draftTo ? format(draftTo) : t('chooseMonth') }}</strong></div>
      </div>
      <div class="range-navigation">
        <button type="button" :aria-label="t('previousYear')" :disabled="leftYear <= firstYear" @click="navigate(leftYear - 1)">←</button>
        <label class="range-year-select"><span class="range-caption">{{ t('jumpToYear') }}</span><select ref="yearSelect" :value="leftYear" :aria-label="t('jumpToYear')" @change="navigate($event.target.value)"><option v-for="year in years" :key="year" :value="year">{{ year }}</option></select></label>
        <button type="button" :aria-label="t('nextYear')" :disabled="leftYear >= lastLeftYear" @click="navigate(leftYear + 1)">→</button>
      </div>
      <div class="range-years" :class="{ 'single-year': visibleYears.length === 1 }">
        <section v-for="year in visibleYears" :key="year" class="range-year" :aria-label="String(year)">
          <h3>{{ year }}</h3>
          <div class="range-months" @mouseleave="hovered = ''">
            <button v-for="month in months(year)" :key="month.period" type="button" :data-month="month.period" :aria-label="format(month.period, true)" :aria-pressed="month.period >= preview.from && month.period <= preview.to" :disabled="!available(month.period)" :tabindex="focusedMonth === month.period ? 0 : -1" :class="{ 'in-range': month.period >= preview.from && month.period <= preview.to, endpoint: month.period === preview.from || month.period === preview.to }" @click="choose(month.period)" @mouseenter="hovered = choosingEnd && available(month.period) ? month.period : ''" @focus="focusedMonth = month.period" @keydown="monthKey($event, month.period)">{{ month.label }}</button>
          </div>
        </section>
      </div>
      <div class="range-footer">
        <span class="range-count" role="status">{{ selectionCount ? (selectionCount === 1 ? t('oneMonthSelected') : t('monthsSelected', { count: selectionCount })) : t('chooseLastMonth') }}</span>
        <div class="actions"><button type="button" @click="close">{{ t('cancel') }}</button><button type="button" class="primary" :disabled="!draftTo" @click="apply">{{ t('applyRange') }}</button></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.month-range-picker { position: relative; }
.range-trigger { display: flex; align-items: center; gap: .85rem; padding: .65rem 1rem; text-align: left; min-width: 300px; box-shadow: 0 2px 4px #20342f04; }
.range-trigger[aria-expanded="true"] { border-color: #1c624d; box-shadow: 0 0 0 3px #1c624d12; }
.range-calendar-icon { color: #477060; flex-shrink: 0; }
.range-trigger-text { flex: 1; }
.range-trigger strong { display: block; font-size: .95rem; font-weight: 600; }
.range-caption { display: block; font-size: .73rem; color: #64786b; }
.range-dash { color: #758176; padding: 0 .2rem; }
.range-popover { position: absolute; top: calc(100% + .65rem); right: 0; z-index: 20; width: 600px; max-width: calc(100vw - 32px); padding: 1.25rem; border: 1px solid #dce3d9; border-radius: 16px; background: #fff; box-shadow: 0 12px 50px #20342f24; }
.range-heading { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
.range-heading h2 { margin: 0; font-size: 1.1rem; }
.range-close { border-color: transparent; font-size: 1.35rem; padding: .1rem .6rem; }
.range-hint { font-size: .85rem; color: #64786b; margin: .3rem 0 1rem; min-height: 1.3em; }
.range-presets { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
.range-presets button { font-size: .78rem; padding: .4rem .65rem; border-radius: 20px; }
.range-presets button.active { background: #e8f2ec; border-color: #bdd4c5; color: #165940; }
.range-selection { display: flex; align-items: center; gap: .8rem; padding: .8rem 1rem; background: #f5f7f2; border-radius: 9px; }
.range-selection > div { flex: 1; border-bottom: 2px solid transparent; padding-bottom: .2rem; }
.range-selection > div.pending { border-color: #1c624d; }
.range-selection strong { display: block; font-size: .9rem; }
.range-selection-arrow { color: #758176; }
.range-navigation { display: flex; align-items: center; justify-content: space-between; margin: 1rem 0 .2rem; }
.range-navigation > button { padding: .4rem .75rem; }
.range-year-select { display: flex; flex-direction: row; align-items: center; gap: .6rem; margin: 0; }
.range-year-select select { padding: .3rem .55rem; }
.range-years { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.range-years.single-year { grid-template-columns: 1fr; }
.range-year h3 { margin: .5rem 0 .65rem; text-align: center; font-size: 1rem; font-weight: 600; }
.range-months { display: grid; grid-template-columns: repeat(3, 1fr); gap: .35rem; }
.range-months button { min-height: 42px; padding: .5rem .2rem; border-color: transparent; font-size: .85rem; }
.range-months button:hover:not(:disabled) { border-color: #8bb39e; background: #f1f6f0; }
.range-months button.in-range { background: #e8f2ec; color: #165940; }
.range-months button.endpoint, .range-months button.endpoint:hover { background: #1c624d; color: #fff; }
.range-months button:disabled { opacity: .35; cursor: not-allowed; }
.range-footer { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #e3e7df; }
.range-count { font-size: .8rem; color: #64786b; }
.range-footer button { font-size: .85rem; }
@media (max-width: 640px) {
  .month-range-picker, .range-trigger { width: 100%; }
  .range-trigger { min-width: 0; }
  .range-popover { position: fixed; inset: 12px 12px auto; width: auto; max-width: none; max-height: calc(100dvh - 24px); overflow-y: auto; padding: 1rem; }
  .range-years { grid-template-columns: 1fr; gap: .75rem; }
  .range-year h3 { text-align: left; }
  .range-months { grid-template-columns: repeat(4, 1fr); }
  .range-months button { min-height: 44px; }
  .range-footer { position: sticky; bottom: -1rem; background: #fff; padding-bottom: 1rem; flex-wrap: wrap; }
  .range-footer .actions { margin-left: auto; }
}
</style>
