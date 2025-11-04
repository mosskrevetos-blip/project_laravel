import { defineStore } from 'pinia';
import apiClient from '@/api'; // Наш настроенный Axios

export const useCategoryStore = defineStore('categories', {
  // State: здесь хранятся наши данные
  state: () => ({
    categories: [], // Список всех категорий
    selectedCategoryAttributes: [],
    loading: false, // Флаг для отслеживания загрузки
    error: null, // Для хранения ошибок
  }),

  // Getters: вычисляемые свойства (как computed в компонентах)
  getters: {
    // Можно добавить геттеры, если понадобится, например, для фильтрации
  },

  // Actions: методы для изменения состояния (здесь вся логика)
  actions: {
    // 1. Получить все категории с сервера
    async fetchCategories() {
      this.loading = true;
      this.error = null;
      try {
        const response = await apiClient.get('/categories');
        this.categories = response.data; // Записываем полученные данные в state
      } catch (err) {
        this.error = 'Ошибка при загрузке категорий';
        console.error(err);
      } finally {
        this.loading = false;
      }
    },

    async fetchSuggestedCategories(searchTerm = '') {
      try {
        const response = await apiClient.get('/categories/suggest', {
          params: { search: searchTerm }
        });
        return response.data; // Возвращаем результат напрямую компоненту
      } catch (error) {
        console.error('Ошибка при загрузке предложенных категорий:', error);
        return [];
      }
    },
    
    async fetchAttributesForCategory(categoryId) {
      if (!categoryId) {
          this.selectedCategoryAttributes = [];
          return;
      }
      try {
          const response = await apiClient.get(`/categories/${categoryId}/attributes`);
          this.selectedCategoryAttributes = response.data;
      } catch (error) {
          console.error('Ошибка при загрузке атрибутов:', error);
          this.selectedCategoryAttributes = [];
      }
    },

    // 2. Додати нову категорію
    async addCategory(categoryData) {
      this.loading = true;
      this.error = null;
      try {
        await apiClient.getCsrfCookie();
        const response = await apiClient.post('/categories', categoryData);
        this.categories.unshift(response.data);
      } catch (err) {
        this.error = 'Помилка при додаванні категорії';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    // оновити категорію
    async updateCategory(categoryData) {
      this.loading = true;
      this.error = null;
      try {
        // 👇 И здесь тоже
        await apiClient.getCsrfCookie();
        const response = await apiClient.put(`/categories/${categoryData.id}`, categoryData);
        const index = this.categories.findIndex(p => p.id === categoryData.id);
        if (index !== -1) {
          this.categories[index] = response.data;
        }
      } catch (err) {
        this.error = 'Помилка при оновленні категорії';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    // видалити категорію
    async deleteCategory(categoryId) {
      this.loading = true;
      this.error = null;
      try {
        // 👇 И здесь
        await apiClient.getCsrfCookie();
        await apiClient.delete(`/categories/${categoryId}`);
        this.categories = this.categories.filter(p => p.id !== categoryId);
      } catch (err) {
        this.error = 'Помилка при видаленні категорії';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },
  },
});