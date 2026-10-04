import {createApp} from 'vue'
import {createPinia} from 'pinia'
import {createRouter,createWebHistory} from 'vue-router'
import {i18n} from './i18n'
import App from './App.vue'
import './style.css'
const router=createRouter({history:createWebHistory(import.meta.env.BASE_URL),routes:[{path:'/:pathMatch(.*)*',component:{template:'<span />'}}]})
createApp(App).use(createPinia()).use(router).use(i18n).mount('#app')
