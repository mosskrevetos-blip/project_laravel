import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useProductStore = defineStore('publicProducts', {
  state: () => ({
    popular: [],
    newest: [],
    currentProduct: null,
    loadingProduct: false,
    productError: null,
  }),
  actions: {
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
    // загружает один продукт по id (используется на странице продукта)
    async fetchProduct(id) {
      this.loadingProduct = true;
      this.productError = null;
      this.currentProduct = null;
      try {
        const response = await apiClient.get(`/products/${id}`);
        const product = response.data.data ? response.data.data : response.data;
        this.currentProduct = product;
        return product;
      } catch (err) {
        console.error('Ошибка при загрузке продукта:', err);
        this.productError = err;
        throw err;
      } finally {
        this.loadingProduct = false;
      }
    },
  },
});