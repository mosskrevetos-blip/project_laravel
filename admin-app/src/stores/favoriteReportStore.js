import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useFavoriteReportStore = defineStore('favoriteReport', {
  state: () => ({
    mode: null,
    favoriteProductsByUser: [],
    favoriteSellersByUser: [],
    favoritedMyProducts: [],
    usersWhoFavoritedMeAsSeller: [],
    loading: false,
    error: null,
  }),

  actions: {
    async fetchReport() {
      this.loading = true;
      this.error = null;

      try {
        const response = await apiClient.get('/favorites/report');

        this.mode = response.data.mode;
        this.favoriteProductsByUser = response.data.favorite_products_by_user || [];
        this.favoriteSellersByUser = response.data.favorite_sellers_by_user || [];
        this.favoritedMyProducts = response.data.favorited_my_products || [];
        this.usersWhoFavoritedMeAsSeller = response.data.users_who_favorited_me_as_seller || [];
      } catch (err) {
        this.error = 'Помилка при завантаженні звіту по обраному';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async removeFavoriteProduct(productId) {
        this.loading = true;
        this.error = null;

        try {
            await apiClient.delete(`/favorites/${productId}`);
            await this.fetchReport();
        } catch (err) {
            this.error = 'Помилка при видаленні товару з обраного';
            console.error(err);
            throw err;
        } finally {
            this.loading = false;
        }
        },

        async removeFavoriteProductForUser(userId, productId) {
        this.loading = true;
        this.error = null;

        try {
            await apiClient.delete(`/favorites/users/${userId}/products/${productId}`);
            await this.fetchReport();
        } catch (err) {
            this.error = 'Помилка при видаленні товару з обраного користувача';
            console.error(err);
            throw err;
        } finally {
            this.loading = false;
        }
    },
  },
});