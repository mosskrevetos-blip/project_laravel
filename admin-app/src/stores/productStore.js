// Файл: /src/stores/productStore.js

import { defineStore } from 'pinia';
import apiClient from '@/api'; // Наш настроенный Axios

// Вспомогательная функция для создания FormData
function createProductFormData(productData, newImages = []) {
  const formData = new FormData();

  // Добавляем все простые поля
  formData.append('title', productData.title || '');
  formData.append('description', productData.description || '');
  formData.append('price', productData.price || 0);
  formData.append('sku', productData.sku || '');
  formData.append('quantity', productData.quantity || 0);
  formData.append('city', productData.city || '');
  formData.append('category_id', productData.category_id || '');
  if (productData.secondary_category_id) {
    formData.append('secondary_category_id', productData.secondary_category_id);
  }

  // Добавляем сложные поля как JSON-строки
  formData.append('properties', JSON.stringify(productData.properties || {}));
  const finalVideoUrls = productData.video_urls ? productData.video_urls.filter(url => url && url.trim() !== '') : [];
  formData.append('video_urls', JSON.stringify(finalVideoUrls));

  // Добавляем существующие изображения (только строки/имена файлов)
  if (productData.image_url) {
    productData.image_url.forEach(url => {
      if (typeof url === 'string' && url) {
        formData.append('existing_images[]', url);
      }
    });
  }

  // Добавляем НОВЫЕ файлы
  newImages.forEach(file => {
    formData.append('new_images[]', file);
  });
  
  return formData;
}

export const useProductStore = defineStore('products', {
  // State: здесь хранятся наши данные
  state: () => ({
    products: [], // Список всех товаров
    loading: false, // Флаг для отслеживания загрузки
    error: null, // Для хранения ошибок
  }),

  // Getters: вычисляемые свойства (как computed в компонентах)
  getters: {
    // Можно добавить геттеры, если понадобится, например, для фильтрации
  },

  // Actions: методы для изменения состояния (здесь вся логика)
  actions: {
    // 1. Получить все товары с сервера
    async fetchProducts() {
      this.loading = true;
      this.error = null;
      try {
        const response = await apiClient.get('/products');
        this.products = response.data; // Записываем полученные данные в state
      } catch (err) {
        this.error = 'Ошибка при загрузке товаров';
        console.error(err);
      } finally {
        this.loading = false;
      }
    },

    // 2. Добавить новый товар
    async addProduct(productData, newImages) {
      await apiClient.getCsrfCookie();
      
      const formData = createProductFormData(productData, newImages);
      // При создании, все `newImages` передаются как `images`
      formData.delete('existing_images[]');
      formData.delete('new_images[]');
      newImages.forEach(file => {
        formData.append('images[]', file);
      });

      const response = await apiClient.post('/products', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      
      this.products.unshift(response.data);
      await this.fetchProducts();
    },

    // обновить товар
    async updateProduct(productData, newImages) {
      await apiClient.getCsrfCookie();
      
      const formData = createProductFormData(productData, newImages);
      
      // Для PUT-запросов в Laravel c FormData нужен хак - добавляем _method
      formData.append('_method', 'PUT');

      const response = await apiClient.post(`/products/${productData.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      
      const index = this.products.findIndex(p => p.id === productData.id);
      if (index !== -1) {
        this.products[index] = response.data;
      }
      await this.fetchProducts(); // Обновляем список
    },
    
    // удалить товар
    async deleteProduct(productId) {
      await apiClient.getCsrfCookie();
      await apiClient.delete(`/products/${productId}`);
      this.products = this.products.filter(p => p.id !== productId);
    },
  },
});