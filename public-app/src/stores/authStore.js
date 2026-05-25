// Файл: public-app/src/stores/authStore.js

import { defineStore } from 'pinia';
import apiClient from '@/api';
import router from '@/router'; 
import { useCartStore } from '@/stores/cartStore';
import { useFavoriteStore } from '@/stores/favoriteStore';
import { useFavoriteSellerStore } from '@/stores/favoriteSellerStore';

export const useAuthStore = defineStore('publicAuth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    redirectAfterLogin: null,

    postLoginAction: null,
    returnToRoute: null,

    ui: {
      loginDialogOpen: false,
      registerDialogOpen: false,
      forgotPasswordDialogOpen: false,
    },

  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    hasRole: (state) => (roleSlug) => {
      return state.user?.roles?.some(role => role.slug === roleSlug) || false;
    }
  },

  actions: {

    openLoginDialog() {
      // запомнить страницу, с которой открыли диалог
      const r = router.currentRoute.value;
      this.setReturnToRoute({
        name: r.name,
        params: r.params,
        query: r.query,
      });

      this.ui.forgotPasswordDialogOpen = false;
      this.ui.registerDialogOpen = false;
      this.ui.loginDialogOpen = true;
    },

    openRegisterDialog() {
      const r = router.currentRoute.value;
      this.setReturnToRoute({
        name: r.name,
        params: r.params,
        query: r.query,
      });

      this.ui.forgotPasswordDialogOpen = false;
      this.ui.loginDialogOpen = false;
      this.ui.registerDialogOpen = true;
    },

    openForgotPasswordDialog() {
      const r = router.currentRoute.value;
      this.setReturnToRoute({
        name: r.name,
        params: r.params,
        query: r.query,
      });

      this.ui.loginDialogOpen = false;
      this.ui.registerDialogOpen = false;
      this.ui.forgotPasswordDialogOpen = true;
    },

    closeAllAuthDialogs() {
      this.ui.loginDialogOpen = false;
      this.ui.registerDialogOpen = false;
      this.ui.forgotPasswordDialogOpen = false;
    },

    setPostLoginAction(action) {
      this.postLoginAction = action;
    },
    clearPostLoginAction() {
      this.postLoginAction = null;
    },

    setReturnToRoute(route) {
      // route: { name, params, query } или { path }
      this.returnToRoute = route;
    },
    clearReturnToRoute() {
      this.returnToRoute = null;
    },

    async finalizeAuthFlow() {
      // закрываем модалки
      this.closeAllAuthDialogs();

      // 1) если есть postLoginAction — выполняем
      if (this.postLoginAction) {
        const action = this.postLoginAction;
        this.clearPostLoginAction();
        await action();
        // важно: returnToRoute можно оставить или очистить — я бы очищал
        this.clearReturnToRoute();
        return;
      }

      // 2) если есть returnToRoute — возвращаемся туда
      if (this.returnToRoute?.name) {
        const to = this.returnToRoute;
        this.clearReturnToRoute();
        await router.push(to);
        return;
      }

      // 3) fallback
      await router.push({ name: 'home' });
    },


    // Метод для входу користувача
    async login(credentials) {
      await apiClient.getCsrfCookie();
      await apiClient.post('/login', credentials);
      await this.getUser(); // Отримуємо і зберігаємо користувача

      // Синхронізація кошика після входу
      const cartStore = useCartStore();
      await cartStore.syncWithServer();

      // Синхронізація обраного після входу
      const favoriteStore = useFavoriteStore();
      await favoriteStore.syncWithServer();

      // Завантаження обраних продавців
      const favoriteSellerStore = useFavoriteSellerStore();
      await favoriteSellerStore.loadFromServer();

      const redirectUrl = sessionStorage.getItem('redirectAfterLogin');
      sessionStorage.removeItem('redirectAfterLogin');

      if (redirectUrl) {
        this.closeAllAuthDialogs();
        this.clearPostLoginAction();
        this.clearReturnToRoute();
        window.location.href = redirectUrl;
        return;
      }

      await this.finalizeAuthFlow();
    },


    // Метод для реєстрації нового користувача
    async register(userData) {
      await apiClient.getCsrfCookie();
      // Відправляємо запит на ендпоінт /register
      await apiClient.post('/register', userData);
      // Після успішної реєстрації, Breeze автоматично логінить користувача,
      // тому ми просто запитуємо його дані та зберігаємо їх у стані магазину
      await this.getUser();

      // Синхронизація кошика після реєстрації (якщо користувач був неавторизованим)
      const cartStore = useCartStore();
      await cartStore.syncWithServer();

      // Синхронізація обраного після реєстрації
      const favoriteStore = useFavoriteStore();
      await favoriteStore.syncWithServer();

      // Завантаження обраних продавців після реєстрації
      const favoriteSellerStore = useFavoriteSellerStore();
      await favoriteSellerStore.loadFromServer();

      await this.finalizeAuthFlow();
    },

    
    // Метод для оновлення даних користувача (наприклад, після зміни профілю)
    async revalidateUser() {
      try {
        const response = await apiClient.get('/user');
        // Якщо успішно - оновлюємо дані
        this.user = response.data;
        localStorage.setItem('user', JSON.stringify(response.data));

        // ПЕРЕЗАВАНТАЖУЄМО КОШИК З СЕРВЕРА ПІСЛЯ РЕВАЛІДАЦІЇ
        const cartStore = useCartStore();
        await cartStore.loadFromServer();

      } catch (error) {
        // Якщо помилка (наприклад, 401) - розлогінюємо користувача на фронтенді
        this.user = null;
        localStorage.removeItem('user');
      }
    },


    // метод для відправки запиту на скидання пароля (забули пароль)
    async forgotPassword(email) {
      await apiClient.getCsrfCookie();
      // Відправляємо запит на ендпоінт /forgot-password
      await apiClient.post('/forgot-password', { email });
    },


    // метод для встановлення нового пароля після отримання посилання зі скидання пароля
    async resetPassword(resetData) {
      await apiClient.getCsrfCookie();
      // Відправляємо запит на ендпоінт /reset-password
      await apiClient.post('/reset-password', resetData);
    },


    // Метод для отримання даних поточного користувача (використовується після входу та для перевірки сесії)
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


    // Метод для виходу користувача
    async logout() {
      try {
        await apiClient.post('/logout');
      } finally {
        this.user = null;
        localStorage.removeItem('user');
        
        // Після виходу очищуємо кошик на фронтенді (бо він більше не прив'язаний до користувача)
        const cartStore = useCartStore();
        cartStore.clear();

        this.closeAllAuthDialogs();

        // Перезавантажуємо сторінку, щоб застосувати зміни та скинути стан всіх сторінок
        window.location.reload();
      }
    },
  },
});