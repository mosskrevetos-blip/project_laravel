import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useProductStore = defineStore('publicProducts', {
  state: () => ({
    popular: [],
    newest: [],
  }),
  actions: {
    // Метод addProduct() удалён, так как он больше не нужен
    async fetchPopular() {
      try {
        const response = await apiClient.get('/products/popular');
        this.popular = response.data;
      } catch (error) {
        console.error('Ошибка при загрузке популярных товаров:', error);
      }
    },
    async fetchNewest() {
      try {
        const response = await apiClient.get('/products/newest');
        this.newest = response.data;
      } catch (error) {
        console.error('Ошибка при загрузке новинок:', error);
      }
    },
  },
});