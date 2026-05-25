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
import { useAuthStore } from './stores/authStore' // Імпорт сховища автентифікації  

const app = createApp(App)
const pinia = createPinia() // Створюємо екземпляр Pinia

app.use(pinia) // Підключаємо Pinia
app.use(router) // Підключаємо маршрутизатор

const vuetify = createVuetify({ components, directives })
app.use(vuetify)

// Після підключення Pinia, ми можемо використовувати сховище
const authStore = useAuthStore()
// Примусово перепровіряємо статус користувача з бекендом при кожному завантаженні
authStore.revalidateUser().then(() => {
  app.mount('#app') // Монтуємо додаток тільки після перевірки
})