import { defineStore } from 'pinia';
import axios from 'axios';

const API_BASE = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '');

export const useMyCommentsStore = defineStore('myComments', {
  state: () => ({
    items: [],
    loading: false,
    error: '',

    filters: {
      type: null,      // review | question | null
      status: null,    // pending | approved | rejected | null
      page: 1,
      per_page: 20,
    },

    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
    },
  }),

  actions: {
    setFilters(payload = {}) {
      this.filters = {
        ...this.filters,
        ...payload,
      };
    },

    resetFilters() {
      this.filters = {
        type: null,
        status: null,
        page: 1,
        per_page: 20,
      };
    },

    clearError() {
      this.error = '';
    },

    async fetchMyComments(overrideParams = null) {
      this.loading = true;
      this.error = '';

      try {
        const params = overrideParams ? { ...overrideParams } : { ...this.filters };

        // удаляем null/empty
        Object.keys(params).forEach((k) => {
          if (params[k] === null || params[k] === '') {
            delete params[k];
          }
        });

        const { data } = await axios.get(`${API_BASE}/api/me/comments`, {
          params,
          withCredentials: true,
          headers: {
            Accept: 'application/json',
          },
        });

        this.items = data?.data || [];
        this.pagination.current_page = data?.current_page || 1;
        this.pagination.last_page = data?.last_page || 1;
        this.pagination.per_page = data?.per_page || this.filters.per_page;
        this.pagination.total = data?.total || 0;
      } catch (e) {
        this.error = e?.response?.data?.message || 'Не вдалося завантажити коментарі';
      } finally {
        this.loading = false;
      }
    },
  },
});