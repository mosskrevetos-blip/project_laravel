// Файл: public-app/src/main.js

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/authStore' // <-- Импортируем хранилище

const app = createApp(App)
const pinia = createPinia() // <-- Создаем экземпляр Pinia

app.use(pinia) // <-- Подключаем Pinia
app.use(router)

const vuetify = createVuetify({ components, directives })
app.use(vuetify)

// После подключения Pinia, мы можем использовать хранилище
const authStore = useAuthStore()
// Принудительно перепроверяем статус пользователя с бэкендом при каждой загрузке
authStore.revalidateUser().then(() => {
  app.mount('#app') // Монтируем приложение только после проверки
})