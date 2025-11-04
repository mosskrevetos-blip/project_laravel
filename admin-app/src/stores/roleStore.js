import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useRoleStore = defineStore('roles', {
  state: () => ({
    roles: [],
  }),
  actions: {
    async fetchRoles() {
      try {
        const response = await apiClient.get('/roles');
        this.roles = response.data;
      } catch (error) {
        console.error('Помилка при отриманні ролів:', error);
      }
    },
  },
});