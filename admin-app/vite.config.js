import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  // 👇 Добавляем этот блок
  server: {
    host: true, // Позволяет Vite быть доступным из Docker
    port: 5173, // Явно указываем порт
    allowedHosts: [
      'admin.ecom.local' // <-- Возвращаем эту важную строку
    ]
  }
})