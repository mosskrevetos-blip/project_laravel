// Файл: public-app/src/stores/authStore.js

import { defineStore } from 'pinia';
import apiClient from '@/api';
import router from '@/router'; // Импортируем роутер для перенаправлений

export const useAuthStore = defineStore('publicAuth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    redirectAfterLogin: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    hasRole: (state) => (roleSlug) => {
      return state.user?.roles?.some(role => role.slug === roleSlug) || false;
    }
  },

  actions: {
    // 👇 НОВЫЙ МЕТОД ДЛЯ ВХОДА В СИСТЕМУ 👇
    // async login(credentials) {
    //   // Сначала получаем CSRF-cookie
    //   await apiClient.getCsrfCookie();
    //   // Отправляем запрос на эндпоинт /login, который создал Breeze
    //   await apiClient.post('/login', credentials);
    //   // После успешного входа, получаем данные пользователя
    //   await this.getUser();
      
    //   // --- 2. ОБНОВЛЁННАЯ ЛОГИКА ПЕРЕНАПРАВЛЕНИЯ ---
    //   // Проверяем, есть ли сохранённый путь
    //   const redirectPath = this.redirectAfterLogin;
    //   this.redirectAfterLogin = null; // Очищаем "память" после использования

    //   if (redirectPath) {
    //     // Если есть - идём туда
    //     await router.push(redirectPath);
    //   } else {
    //     // Если нет (обычный вход) - идём на главную
    //     await router.push({ name: 'home' });
    //   }
    // },

    async login(credentials) {
      await apiClient.getCsrfCookie();
      await apiClient.post('/login', credentials);
      await this.getUser(); // Получаем и сохраняем пользователя

      // --- ИЗМЕНЁННАЯ ЛОГИКА ПЕРЕНАПРАВЛЕНИЯ ---
      // 1. Читаем сохранённый путь из sessionStorage
      const redirectUrl = sessionStorage.getItem('redirectAfterLogin');
      
      // 2. Сразу удаляем его, чтобы он не сработал в следующий раз
      sessionStorage.removeItem('redirectAfterLogin');

      if (redirectUrl) {
        // 3. Если путь был (например, в админку), делаем "жёсткий" редирект
        window.location.href = redirectUrl;
      } else {
        // 4. Если нет (обычный вход) - идём на главную
        await router.push({ name: 'home' });
      }
    },

    async register(userData) {
      await apiClient.getCsrfCookie();
      // Отправляем запрос на эндпоинт /register
      await apiClient.post('/register', userData);
      // После успешной регистрации, Breeze автоматически логинит пользователя,
      // поэтому мы просто запрашиваем его данные
      await this.getUser();
      // И перенаправляем на главную
      await router.push({ name: 'home' });
    },

    // 👇 НОВЫЙ МЕТОД ДЛЯ ПРИНУДИТЕЛЬНОЙ ПРОВЕРКИ 👇
    async revalidateUser() {
      try {
        const response = await apiClient.get('/user');
        // Если успешно - обновляем данные
        this.user = response.data;
        localStorage.setItem('user', JSON.stringify(response.data));
      } catch (error) {
        // Если ошибка (например, 401) - разлогиниваем пользователя на фронтенде
        this.user = null;
        localStorage.removeItem('user');
      }
    },

    // 👇 НОВЫЙ МЕТОД ДЛЯ ЗАПРОСА СБРОСА ПАРОЛЯ 👇
    async forgotPassword(email) {
      await apiClient.getCsrfCookie();
      // Отправляем запрос на эндпоинт /forgot-password
      await apiClient.post('/forgot-password', { email });
    },
    // 👇 НОВЫЙ МЕТОД ДЛЯ УСТАНОВКИ НОВОГО ПАРОЛЯ 👇
    async resetPassword(resetData) {
      await apiClient.getCsrfCookie();
      // Отправляем запрос на эндпоинт /reset-password
      await apiClient.post('/reset-password', resetData);
    },

    async getUser() {
      if (this.user) return;
      try {
        const response = await apiClient.get('/user');
        this.user = response.data;
        localStorage.setItem('user', JSON.stringify(response.data));
      } catch (error) {
        this.user = null;
        localStorage.removeItem('user');
      }
    },

    async logout() {
      try {
        await apiClient.post('/logout');
      } finally {
        this.user = null;
        localStorage.removeItem('user');
        // Перезагружаем страницу, чтобы применить изменения и сбросить состояние
        window.location.reload();
      }
    },
  },
});