import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useOrderStore = defineStore('orders', {
  state: () => ({
    orders: [],
    loading: false,
    error: null,
  }),
  actions: {
    async fetchOrders() {
      this.loading = true;
      this.error = null;
      try {
        const response = await apiClient.get('/orders');
        this.orders = response.data;
      } catch (err) {
        this.error = 'Ошибка при загрузке заказов';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    // 2. Додати нове замовлення
    async addOrder(orderData) {
      this.loading = true;
      this.error = null;
      try {
        const response = await apiClient.post('/orders', orderData);
        this.orders.unshift(response.data);
      } catch (err) {
        this.error = 'Помилка при додаванні замовлення';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    // Оновити замовлення
    async updateOrder(orderData) {
      this.loading = true;
      this.error = null;
      try {
        const response = await apiClient.put(`/orders/${orderData.id}`, orderData);
        const index = this.orders.findIndex(p => p.id === orderData.id);
        if (index !== -1) {
          this.orders[index] = response.data;
        }
      } catch (err) {
        this.error = 'Помилка при оновленні замовлення';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    // видалити замовлення
    async deleteProduct(orderId) {
      this.loading = true;
      this.error = null;
      try {
        await apiClient.delete(`/orders/${orderId}`);
        this.orders = this.orders.filter(p => p.id !== orderId);
      } catch (err) {
        this.error = 'Помилка при видаленні замовлення';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },
  },
});