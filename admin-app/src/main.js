// Файл: admin-app/src/main.js

import { createApp } from 'vue'
import { createPinia } from 'pinia'

// Vuetify
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/authStore' // 1. Импортируем наше хранилище

const app = createApp(App)

// Сначала подключаем Pinia, чтобы хранилища были доступны
const pinia = createPinia()
app.use(pinia)

app.use(router)

const vuetify = createVuetify({
  components,
  directives,
})
app.use(vuetify)

// 2. Инициализируем хранилище ПОСЛЕ подключения Pinia
const authStore = useAuthStore()

// 3. Пытаемся получить данные пользователя ПЕРЕД монтированием приложения
authStore.getUser().finally(() => {
  // 4. И ТОЛЬКО ПОСЛЕ того, как проверка завершена
  // (успешно или с ошибкой - неважно), мы монтируем приложение.
  // Это гарантирует, что роутер будет знать статус пользователя.
  app.mount('#app')
})