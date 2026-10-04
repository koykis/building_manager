<script setup>
import {ref,onMounted,watch} from 'vue'
import {useI18n} from 'vue-i18n'
import {useAuth} from './auth'
import {appBase,errorText} from './api'
import Statements from './views/Statements.vue'
import Dashboard from './views/Dashboard.vue'
import References from './views/References.vue'
import Accounts from './views/Accounts.vue'
import Projects from './views/Projects.vue'
const page=ref('dashboard')
const {t,locale}=useI18n(),auth=useAuth(),email=ref(''),password=ref(''),error=ref(''),busy=ref(false)
watch(locale,v=>{localStorage.setItem('locale',v);document.documentElement.lang=v},{immediate:true})
onMounted(()=>auth.load())
async function login(){busy.value=true;error.value='';try{await auth.login(email.value,password.value);password.value=''}catch(e){error.value=errorText(e)}finally{busy.value=false}}
</script>
<template><div class="shell"><header><a class="brand" :href="appBase">◫ <strong>{{t('app')}}</strong><small>{{t('subtitle')}}</small></a><div class="actions"><select v-model="locale" aria-label="Language / Γλώσσα"><option value="el">Ελληνικά</option><option value="en">English</option></select><button v-if="auth.user" @click="auth.logout">{{t('logout')}}</button></div></header><main v-if="!auth.ready">{{t('loading')}}</main><main v-else-if="!auth.user" class="login"><div><span class="eyebrow">{{t('private')}}</span><h1>{{t('welcome')}}</h1><p>{{t('loginHint')}}</p></div><form @submit.prevent="login" class="card"><h2>{{t('login')}}</h2><label>{{t('email')}}<input v-model="email" type="email" autocomplete="username" required></label><label>{{t('password')}}<input v-model="password" type="password" autocomplete="current-password" required></label><p v-if="error" role="alert" class="error">{{error}}</p><button class="primary" :disabled="busy">{{t('login')}}</button></form></main><template v-else><nav class="nav" v-if="auth.user.role==='admin'"><button v-for="p in ['dashboard','statements','projects','references','accounts']" @click="page=p" :class="{selected:page===p}">{{t(p)}}</button></nav><main><Statements v-if="page==='statements'&&auth.user.role==='admin'"/><References v-else-if="page==='references'&&auth.user.role==='admin'"/><Accounts v-else-if="page==='accounts'&&auth.user.role==='admin'"/><Projects v-else-if="page==='projects'&&auth.user.role==='admin'"/><Dashboard v-else/></main></template></div></template>
