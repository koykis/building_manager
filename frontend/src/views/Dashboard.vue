<script setup>
import {ref,onMounted,computed} from 'vue'
import {useI18n} from 'vue-i18n'
import {Line} from 'vue-chartjs'
import CategoryPie from '../components/CategoryPie.vue'
import RecurringCosts from '../components/RecurringCosts.vue'
import MonthRangePicker from '../components/MonthRangePicker.vue'
import {Chart as ChartJS,CategoryScale,LinearScale,PointElement,LineElement,Tooltip,Legend} from 'chart.js'
import {api,errorText} from '../api'
import {useAuth} from '../auth'
import {yearToDate} from '../monthRange'
ChartJS.register(CategoryScale,LinearScale,PointElement,LineElement,Tooltip,Legend)
const defaultRange=yearToDate()
const {t,locale}=useI18n(),auth=useAuth(),from=ref(defaultRange.from),to=ref(defaultRange.to),data=ref(null),error=ref(''),units=ref([]),unit=ref(''),personal=ref([]),category=ref(''),ready=ref(false),loading=ref(false),coverage=ref({first:'',last:''})
const rangeBounds=computed(()=>({min:coverage.value.first && coverage.value.first < defaultRange.from ? coverage.value.first : defaultRange.from,max:coverage.value.last && coverage.value.last > defaultRange.to ? coverage.value.last : defaultRange.to}))
const money=v=>new Intl.NumberFormat(locale.value,{style:'currency',currency:'EUR'}).format(v??0)
const chart=computed(()=>({labels:data.value?.months.map(m=>m.period)||[],datasets:[{label:category.value?t('category'):t('operating'),data:data.value?.months.map(m=>m.available?Number(category.value?(m.categories[category.value]||0):m.totals.operating):null)||[],borderColor:'#246c54',backgroundColor:'#246c54',tension:.2,spanGaps:false},{label:category.value?t('priorYear'):t('reserve'),data:data.value?.months.map(m=>m.available?(category.value?(m.prior_categories?Number(m.prior_categories[category.value]||0):null):Number(m.totals.reserve)):null)||[],borderColor:'#b09a51',backgroundColor:'#b09a51',tension:.2,spanGaps:false}]}))
const categories = computed(() => {
  const sums = {}
  for (const month of data.value?.months || []) {
    if (month.available) for (const [id, amount] of Object.entries(month.categories)) {
      sums[id] = (sums[id] || 0) + Number(amount)
    }
  }
  return Object.entries(sums).map(([id, amount]) => {
    const item = data.value.categories.find(category => String(category.id) === id)
    return { id, amount, label: locale.value === 'el' ? item?.name_el : item?.name_en }
  })
})
async function load(){loading.value=true;try{error.value='';data.value=(await api.get('/reports/building',{params:{from:from.value,to:to.value}})).data;return true}catch(e){error.value=errorText(e);return false}finally{loading.value=false}}
async function applyRange(range){const previous={from:from.value,to:to.value};from.value=range.from;to.value=range.to;if(!await load()){from.value=previous.from;to.value=previous.to}else{await apartment()}}
async function apartment(){if(auth.user.role==='admin'&&!unit.value){personal.value=[];return}try{personal.value=(await api.get('/reports/my-apartment',{params:{from:from.value,to:to.value,...(auth.user.role==='admin'?{apartment_id:unit.value}:{})}})).data}catch(e){personal.value=[];error.value=errorText(e)}}
onMounted(async()=>{try{const [published,references]=await Promise.all([api.get('/reports/coverage'),auth.user.role==='admin'?api.get('/admin/references'):Promise.resolve(null)]);coverage.value=published.data;if(references){units.value=references.data.apartments;unit.value=units.value[0]?.id}await Promise.all([load(),apartment()])}catch(e){error.value=errorText(e)}finally{ready.value=true}})
</script>
<template><div class="toolbar"><div><span class="eyebrow">{{t('subtitle')}}</span><h1>{{t('dashboard')}}</h1></div><MonthRangePicker :from="from" :to="to" :min="rangeBounds.min" :max="rangeBounds.max" :first-available="coverage.first" :last-available="coverage.last" :disabled="!ready || loading || !coverage.first" @apply="applyRange"/></div><p v-if="loading && ready" class="muted" role="status">{{t('loading')}}</p><p v-if="error" class="error">{{error}}</p><template v-if="data"><p class="muted">{{t('coverage')}}: {{data.coverage.published}} / {{data.coverage.expected}} {{t('months')}}</p><div class="kpis"><div v-for="type in ['operating','capital','reserve','unclassified']" class="card kpi"><span>{{t(type)}}</span><strong>{{money(data.totals[type])}}</strong></div></div><div class="split"><div class="card"><div class="toolbar"><h2>{{t('statements')}}</h2><select v-model="category" :aria-label="t('category')"><option value="">{{t('allCategories')}}</option><option v-for="c in data.categories" :value="String(c.id)">{{locale==='el'?c.name_el:c.name_en}}</option></select></div><div class="chart"><Line :data="chart" :options="{responsive:true,maintainAspectRatio:false,scales:{y:{min:0}}}"/></div></div><div class="card"><h2>{{t('categories')}}</h2><CategoryPie :items="categories"/></div></div><RecurringCosts :months="data.months" :categories="data.categories" :first="coverage.first || ''" :last="coverage.last || ''"/><div class="card"><h2>{{t('rangeComparison')}}</h2><p v-if="data.comparison">{{money(data.comparison.current)}} / {{money(data.comparison.prior)}} · {{money(data.comparison.delta)}} {{data.comparison.percent===null?'':'('+data.comparison.percent+'%)'}}</p><p v-else class="muted">{{t('noComparison')}}</p></div><div class="card scroll"><table><thead><tr><th>{{t('period')}}</th><th>{{t('operating')}}</th><th>{{t('capital')}}</th><th>{{t('reserve')}}</th><th>{{t('reportedTotal')}}</th><th>{{t('yoy')}}</th></tr></thead><tbody><tr v-for="m in data.months"><td>{{m.period}} <small v-if="m.estimated" class="estimate-label" :title="t('estimatedMonthHint',{period:m.source_period})">{{t('estimatedMonth')}}</small></td><template v-if="m.available"><td>{{money(m.totals.operating)}}</td><td>{{money(m.totals.capital)}}</td><td>{{money(m.totals.reserve)}}</td><td>{{money(m.statement_total)}}</td><td>{{m.yoy?money(m.yoy.delta)+(m.yoy.percent!==null?' ('+m.yoy.percent+'%)':''):t('noComparison')}}<small v-if="m.yoy&&(m.estimated||m.prior_estimated)" class="estimate-label">{{t('estimatedComparison')}}</small></td></template><td v-else colspan="5" class="muted">{{t('missing')}}</td></tr></tbody></table></div></template><p v-if="!ready">{{t('loading')}}</p><div v-else class="card"><div class="toolbar"><h2>{{auth.user.role==='admin'?t('apartment'):t('myApartment')}}</h2><select v-if="auth.user.role==='admin'" v-model="unit" @change="apartment"><option v-for="u in units" :value="u.id">{{locale==='el'?u.label:u.alias}}</option></select></div><div class="scroll"><table><thead><tr><th>{{t('period')}}</th><th>{{t('reportedTotal')}}</th><th>{{t('boiler')}}</th><th>{{t('rows')}}</th></tr></thead><tbody><tr v-for="r in personal"><td>{{r.period}} <small v-if="r.estimated" class="estimate-label" :title="t('estimatedMonthHint',{period:r.source_period})">{{t('estimatedMonth')}}</small></td><td>{{money(r.printed_total)}} <small v-if="r.total_computed" class="estimate-label" :title="t('computedTotalHint')">{{t('computedTotal')}}</small></td><td>{{r.boiler_m3??'—'}}</td><td><details><summary>{{t('rows')}}</summary><p v-for="c in r.cells.filter(x=>x.amount!==null&&Number(x.amount)!==0)">{{t(c.column==='boiler'?'boilerCharge':c.column)}}: {{c.amount}} €</p></details></td></tr></tbody></table></div><p v-if="!personal.length">{{t('empty')}}</p></div></template>

<style scoped>
.split > .card { min-width: 0; }
.estimate-label { display: block; color: #735a22; font-size: .75rem; max-width: 17rem; white-space: normal; }
</style>
