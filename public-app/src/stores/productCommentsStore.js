import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useProductCommentsStore = defineStore('productComments', {
  state: () => ({
    items: [],
    loading: false,
    error: null,

    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0,
    },

    filters: {
      product_id: null,
      type: 'review', // review|question
      sort: 'date_desc',
      rating: null,
      with_photos: false,
      verified: false,
      page: 1,
      per_page: 10,
    },

    createLoading: false,
    actionLoading: false,
    reportLoading: false,
  }),

  actions: {
    setProduct(productId) {
      this.filters.product_id = Number(productId);
    },

    setType(type) {
      this.filters.type = type;
      this.filters.page = 1;

      // для питання не применяем rating
      if (type === 'question') {
        this.filters.rating = null;
        if (this.filters.sort === 'rating_desc' || this.filters.sort === 'rating_asc') {
          this.filters.sort = 'date_desc';
        }
      }
    },

    setFilters(payload = {}) {
      this.filters = { ...this.filters, ...payload };
    },

    resetFormRelatedFiltersForType() {
      if (this.filters.type === 'question') {
        this.filters.rating = null;
      }
    },

    async fetchList(customFilters = {}) {
      if (!this.filters.product_id) return;

      this.loading = true;
      this.error = null;

      try {
        this.setFilters(customFilters);
        this.resetFormRelatedFiltersForType();

        const params = {
          type: this.filters.type,
          sort: this.filters.sort,
          page: this.filters.page,
          per_page: this.filters.per_page,
          with_photos: this.filters.with_photos ? 1 : 0,
          verified: this.filters.verified ? 1 : 0,
        };

        if (this.filters.type === 'review' && this.filters.rating) {
          params.rating = this.filters.rating;
        }

        const response = await apiClient.get(`/products/${this.filters.product_id}/comments`, { params });

        this.items = response.data?.data ?? [];
        this.pagination = {
          current_page: response.data?.current_page ?? 1,
          last_page: response.data?.last_page ?? 1,
          per_page: response.data?.per_page ?? this.filters.per_page,
          total: response.data?.total ?? 0,
        };
      } catch (err) {
        this.error = 'Не вдалося завантажити коментарі';
        console.error(err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async createComment(formPayload) {
      // formPayload: { type, rating, body, pros, cons, images, youtube_url }
      if (!this.filters.product_id) return;

      this.createLoading = true;
      this.error = null;

      try {
        await apiClient.getCsrfCookie();

        const fd = new FormData();
        fd.append('type', formPayload.type);

        if (formPayload.type === 'review' && formPayload.rating) {
          fd.append('rating', String(formPayload.rating));
        }

        if (formPayload.body) fd.append('body', formPayload.body);

        if (formPayload.type === 'review' && formPayload.pros) fd.append('pros', formPayload.pros);
        if (formPayload.type === 'review' && formPayload.cons) fd.append('cons', formPayload.cons);

        if (formPayload.youtube_url) fd.append('youtube_url', formPayload.youtube_url);

        (formPayload.images || []).slice(0, 5).forEach((file) => {
          fd.append('images[]', file);
        });

        await apiClient.post(`/products/${this.filters.product_id}/comments`, fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });

        return true;
      } catch (err) {
        this.error = err?.response?.data?.message || 'Не вдалося відправити коментар';
        console.error(err);
        throw err;
      } finally {
        this.createLoading = false;
      }
    },

    async react(commentId, reaction) {
      this.actionLoading = true;
      this.error = null;

      try {
        await apiClient.getCsrfCookie();

        const response = await apiClient.post(`/comments/${commentId}/reaction`, { reaction });
        const dto = response.data?.data;

        const i = this.items.findIndex((x) => x.id === commentId);
        if (i !== -1 && dto) {
          this.items[i] = {
            ...this.items[i],
            likes_count: dto.likes_count,
            dislikes_count: dto.dislikes_count,
            helpfulness_score: dto.helpfulness_score,
          };
        }

        return dto;
      } catch (err) {
        this.error = 'Не вдалося зберегти реакцію';
        console.error(err);
        throw err;
      } finally {
        this.actionLoading = false;
      }
    },

    async report(commentId, reason) {
      this.reportLoading = true;
      this.error = null;

      try {
        await apiClient.getCsrfCookie();
        await apiClient.post(`/comments/${commentId}/report`, { reason });
        return true;
      } catch (err) {
        this.error = err?.response?.data?.message || 'Не вдалося відправити скаргу';
        console.error(err);
        throw err;
      } finally {
        this.reportLoading = false;
      }
    },
  },
});