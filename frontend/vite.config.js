import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
export default defineConfig({plugins:[vue()],test:{exclude:['e2e/**','node_modules/**']},server:{port:5173,proxy:{'/api':'http://127.0.0.1:8000','/sanctum':'http://127.0.0.1:8000'}}})
