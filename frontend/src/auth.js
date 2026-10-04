import {defineStore} from 'pinia'
import {api,csrf} from './api'
export const useAuth=defineStore('auth',{state:()=>({user:null,ready:false}),actions:{async load(){try{this.user=(await api.get('/auth/me')).data}catch{this.user=null}this.ready=true},async login(email,password){await csrf();this.user=(await api.post('/auth/login',{email,password})).data},async logout(){await api.post('/auth/logout');this.user=null}}})
