import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useUserStore = defineStore('users', {
  state: () => ({
    users: [],
    loading: false,
    error: null,
  }),
  actions: {
    async fetchUsers() {
      this.loading = true;
      this.error = null;
      try {
        const response = await apiClient.get('/users');
        this.users = response.data;
      } catch (err) {
        this.error = 'Помилка при завантаженні користувача';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    // додати нового користувача
    async addUser(userData) {
      this.loading = true;
      this.error = null;
      try {
        const response = await apiClient.post('/users', userData);
        this.users.unshift(response.data);
      } catch (err) {
        this.error = 'Помилка при створенні нового користувача';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    // оновити користувача
    async updateUser(userData) {
      this.loading = true;
      this.error = null;
      try {
        const response = await apiClient.put(`/users/${userData.id}`, userData);
        const index = this.users.findIndex(p => p.id === userData.id);
        if (index !== -1) {
          this.users[index] = response.data;
        }
      } catch (err) {
        this.error = 'Помилка при оновленні користувача';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    // видалити користувача
    async deleteUser(userId) {
      this.loading = true;
      this.error = null;
      try {
        // 👇 И здесь
        await apiClient.delete(`/users/${userId}`);
        this.users = this.users.filter(p => p.id !== userId);
      } catch (err) {
        this.error = 'Помилка при видаленні користувача';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },
  },
});