import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useCategoryStore = defineStore('publicCategories', {
  state: () => ({
    categories: [],
  }),
  actions: {
    async fetchCategories() {
      try {
        const response = await apiClient.get('/categories');
        this.categories = response.data;
      } catch (error) {
        console.error('Ошибка при загрузке категорий:', error);
      }
    },
    // Методы fetchAttributesForCategory и fetchSuggestedCategories удалены
  },
});