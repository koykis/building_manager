import {defineConfig} from '@playwright/test'
export default defineConfig({testDir:'./e2e',timeout:30000,use:{baseURL:'http://127.0.0.1:5173',headless:true,launchOptions:{executablePath:'/opt/google/chrome/chrome',args:['--no-sandbox']}},reporter:'list'})
