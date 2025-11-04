import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useAttributeStore = defineStore('attributes', {
  state: () => ({
    attributes: [],
    loading: false,
    error: null,
  }),
  actions: {
    async fetchAttributes() {
      this.loading = true;
      this.error = null;
      try {
        const response = await apiClient.get('/attributes');
        this.attributes = response.data;
      } catch (err) {
        this.error = 'Ошибка при загрузке атрибутов';
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async addAttribute(attributeData) {
      await apiClient.getCsrfCookie();
      const response = await apiClient.post('/attributes', attributeData);
      this.attributes.unshift(response.data);
    },
    async updateAttribute(attributeData) {
      await apiClient.getCsrfCookie();
      const response = await apiClient.put(`/attributes/${attributeData.id}`, attributeData);
      const index = this.attributes.findIndex(a => a.id === attributeData.id);
      if (index !== -1) this.attributes[index] = response.data;
    },
    async deleteAttribute(attributeId) {
      await apiClient.getCsrfCookie();
      await apiClient.delete(`/attributes/${attributeId}`);
      this.attributes = this.attributes.filter(a => a.id !== attributeId);
    }
  },
});