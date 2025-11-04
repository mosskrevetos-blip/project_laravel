// Файл: admin-app/src/stores/authStore.js
import { defineStore } from 'pinia';
import apiClient from '@/api';
import router from '@/router';

// Получаем URL публичного сайта из переменных окружения
const publicSiteUrl = import.meta.env.VITE_PUBLIC_SITE_URL;

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    // Геттер для перевірки ролі
    hasRole: (state) => (roleSlug) => {
      return state.user?.roles?.some(role => role.slug === roleSlug) || false;
    }
  },

  actions: {
    async login(credentials) {
      try {
        await apiClient.getCsrfCookie(); // Получаем CSRF-cookie
        await apiClient.post('/login', credentials); // Отправляем запрос на вход
        await this.getUser(); // Получаем данные пользователя
        router.push({ name: 'products' }); // Перенаправляем на страницу товаров
      } catch (error) {
        console.error('Login failed:', error);
        throw error; // Пробрасываем ошибку, чтобы компонент мог её обработать
      }
    },

    // 👇 НОВЫЙ МЕТОД ДЛЯ ПРИНУДИТЕЛЬНОЙ ПРОВЕРКИ
    async revalidateUser() {
      try {
        const response = await apiClient.get('/user');
        this.user = response.data;
        localStorage.setItem('user', JSON.stringify(response.data));
      } catch (error) {
        // Если ошибка (401), значит сессия истекла
        this.user = null;
        localStorage.removeItem('user');
      }
    },

    async getUser() {

      // Если пользователь уже в состоянии, ничего не делаем
      if (this.user) return;

      // Если в состоянии нет, но есть в localStorage, загружаем
      const localUser = localStorage.getItem('user');
      if (localUser) {
        this.user = JSON.parse(localUser);
        return;
      }
      
      // Если нет нигде, делаем запрос (это сработает при бесшовном входе)
      await this.revalidateUser();
    },

    async logout() {
      try {
        await apiClient.post('/logout');
      } finally {
        this.user = null;
        localStorage.removeItem('user');
        window.location.href = publicSiteUrl; 
      }
    },

  },
});