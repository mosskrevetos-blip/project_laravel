import { defineStore } from 'pinia';
import { ref } from 'vue';
import apiClient from '@/api';

export const useSellerStore = defineStore('seller', () => {
  const seller = ref(null);
  const products = ref([]);
  const loading = ref(false);
  const error = ref(null);

  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
  });

  function reset() {
    seller.value = null;
    products.value = [];
    error.value = null;
    pagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 0,
    };
  }

  async function fetchSellerProfile({ sellerId, page = 1, perPage = 12 }) {
    loading.value = true;
    error.value = null;

    try {
      const response = await apiClient.get(`/sellers/${sellerId}`, {
        params: {
          page,
          per_page: perPage,
        },
      });

      seller.value = response.data?.seller || null;
      products.value = response.data?.products?.data || [];
      pagination.value = response.data?.products?.meta || {
        current_page: 1,
        last_page: 1,
        per_page: perPage,
        total: 0,
      };

      return {
        seller: seller.value,
        products: products.value,
        total: pagination.value.total || 0,
      };
    } catch (err) {
      console.error('Помилка завантаження сторінки продавця:', err);
      error.value = 'Не вдалося завантажити сторінку продавця';
      seller.value = null;
      products.value = [];
      pagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: perPage,
        total: 0,
      };
      throw err;
    } finally {
      loading.value = false;
    }
  }

  return {
    seller,
    products,
    loading,
    error,
    pagination,
    reset,
    fetchSellerProfile,
  };
});