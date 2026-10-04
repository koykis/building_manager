<script setup>
import {ref,onMounted} from 'vue'
import {useI18n} from 'vue-i18n'
import {api,errorText} from '../api'
const {t,locale}=useI18n(),users=ref([]),apartments=ref([]),error=ref(''),message=ref('')
onMounted(async()=>{users.value=(await api.get('/admin/users')).data.filter(u=>u.role==='resident');apartments.value=(await api.get('/admin/references')).data.apartments})
async function save(u){try{const r=await api[u.id?'put':'post']('/admin/users'+(u.id?'/'+u.id:''),u);Object.assign(u,r.data);u.password='';message.value=t('saved')}catch(e){error.value=errorText(e)}}
</script>
<template><h1>{{t('accounts')}}</h1><p class="notice">{{t('accountHint')}}</p><p v-if="error" class="error">{{error}}</p><p v-if="message" role="status">{{message}}</p><button @click="users.push({name:'',email:'',password:'',apartment_id:apartments[0]?.id,active:false,locale:'el'})">+ {{t('add')}}</button><form class="card" v-for="u in users" @submit.prevent="save(u)"><div class="form-grid"><label>{{t('name')}}<input v-model="u.name" required></label><label>{{t('email')}}<input type="email" v-model="u.email" required></label><label>{{t('password')}}<input type="password" v-model="u.password" minlength="12" :required="!u.id" autocomplete="new-password"></label><label>{{t('apartment')}}<select v-model="u.apartment_id"><option v-for="a in apartments" :value="a.id">{{locale==='el'?a.label:a.alias}}</option></select></label></div><label class="check"><input type="checkbox" v-model="u.active">{{t('active')}}</label><button>{{t('save')}}</button></form></template>
