import axios from 'axios'
export const appBase = import.meta.env.BASE_URL
export const apiUrl = path => `${appBase}api/v1${path}`
export const api = axios.create({baseURL:apiUrl(''),withCredentials:true,withXSRFToken:true,headers:{Accept:'application/json'}})
export async function csrf(){await axios.get(`${appBase}sanctum/csrf-cookie`,{withCredentials:true})}
export function errorText(e){return Object.values(e.response?.data?.errors||{}).flat().join(' ') || e.response?.data?.message || e.message}

api.interceptors.request.use(config=>{config.headers['Accept-Language']=localStorage.getItem('locale')==='en'?'en':'el';return config})
