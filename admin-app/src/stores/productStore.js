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

const LS_KEY = 'product_edit_locks_v1'; // localStorage key

export const useProductStore = defineStore('products', {
  // State: здесь хранятся наши данные
  state: () => ({
    products: [], // Список всех товаров
    loading: false, // Флаг для отслеживания загрузки
    error: null, // Для хранения ошибок
    pollingIntervalId: null, // id интервала polling (если активен) - оставлено для совместимости
    // Клиентские локи: { [productId]: expiryTimestampMs }
    editLocks: {},
    // тикер для очистки просроченных локов
    _lockTickerId: null,
  }),

  getters: {
    // Можно добавить геттеры, если понадобится, например, для фильтрации
  },

  actions: {
    // ------------------------
    // localStorage & ticker
    // ------------------------
    _loadLocksFromStorage() {
      try {
        const raw = localStorage.getItem(LS_KEY);
        if (raw) {
          const parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object') {
            this.editLocks = Object.fromEntries(
              Object.entries(parsed).map(([k, v]) => [k, Number(v)])
            );
          }
        }
      } catch (e) {
        console.warn('Failed to load edit locks from storage', e);
        this.editLocks = {};
      }
      this.cleanupExpiredLocks();
    },

    _saveLocksToStorage() {
      try {
        localStorage.setItem(LS_KEY, JSON.stringify(this.editLocks));
      } catch (e) {
        console.warn('Failed to save edit locks to storage', e);
      }
    },

    _initLockTicker() {
      if (this._lockTickerId) return;
      // tick every second to cleanup and update reactivity
      this._lockTickerId = setInterval(() => {
        this.cleanupExpiredLocks();
      }, 1000);

      // sync between tabs
      window.addEventListener('storage', (ev) => {
        if (ev.key === LS_KEY) {
          this._loadLocksFromStorage();
        }
      });
    },

    setEditLock(productId, durationMs = 60000) {
      const id = String(productId);
      const expiry = Date.now() + Number(durationMs);
      this.editLocks = { ...this.editLocks, [id]: expiry };
      this._saveLocksToStorage();
    },

    isEditLocked(productId) {
      const id = String(productId);
      const expiry = this.editLocks[id];
      return typeof expiry === 'number' && expiry > Date.now();
    },

    lockRemainingMs(productId) {
      const id = String(productId);
      const expiry = this.editLocks[id];
      if (!expiry) return 0;
      const rem = expiry - Date.now();
      return rem > 0 ? rem : 0;
    },

    cleanupExpiredLocks() {
      const now = Date.now();
      let changed = false;
      const keys = Object.keys(this.editLocks);
      for (const k of keys) {
        if (Number(this.editLocks[k]) <= now) {
          delete this.editLocks[k];
          changed = true;
        }
      }
      if (changed) {
        this.editLocks = { ...this.editLocks };
        this._saveLocksToStorage();
      }
    },

    // ------------------------
    // API actions
    // ------------------------
    // 1. Получить все товары с сервера
    async fetchProducts() {
      this.loading = true;
      this.error = null;
      try {
        const response = await apiClient.get('/products');
        // API может возвращать data внутри response.data.data или просто response.data
        this.products = response.data.data ? response.data.data : response.data;

        // Ensure locks loaded and ticker running
        if (!this._lockTickerId) {
          this._loadLocksFromStorage();
          this._initLockTicker();
        }
        this.cleanupExpiredLocks();
      } catch (err) {
        this.error = 'Ошибка при загрузке товаров';
        console.error(err);
      } finally {
        this.loading = false;
      }
    },

    // (Убираем polling по image_processing — больше не нужен).
    managePolling() {
      // Ничего не делаем — оставлено для обратной совместимости вызовов
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
      
      // Вставляем новый продукт в начало
      const created = response.data.data ? response.data.data : response.data;
      this.products.unshift(created);

      // Устанавливаем клиентский лок на 60 секунд для только что созданного товара
      if (created && created.id) {
        this.setEditLock(created.id, 60 * 1000);
      }

      // После создания — обновим список, чтобы получить актуальные данные
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
      
      const updated = response.data.data ? response.data.data : response.data;
      const index = this.products.findIndex(p => p.id === productData.id);
      if (index !== -1) {
        this.products[index] = updated;
      }

      // Устанавливаем клиентский лок на 60 секунд для обновлённого товара
      if (productData && productData.id) {
        this.setEditLock(productData.id, 60 * 1000);
      }

      // Обновляем список чтобы учесть возможные изменения
      await this.fetchProducts();
    },
    
    // удалить товар
    async deleteProduct(productId) {
      await apiClient.getCsrfCookie();
      await apiClient.delete(`/products/${productId}`);
      this.products = this.products.filter(p => p.id !== productId);
      // clean any locks for deleted product
      const id = String(productId);
      if (this.editLocks[id]) {
        delete this.editLocks[id];
        this.editLocks = { ...this.editLocks };
        this._saveLocksToStorage();
      }
    },

    // Принудительно остановить polling (если нужно)
    stopPolling() {
      if (this.pollingIntervalId) {
        clearInterval(this.pollingIntervalId);
        this.pollingIntervalId = null;
      }
      if (this._lockTickerId) {
        clearInterval(this._lockTickerId);
        this._lockTickerId = null;
      }
    },
  },
});