import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useProductCommentModerationStore = defineStore('productCommentModeration', {
  state: () => ({
    // comments moderation
    comments: [],
    commentsLoading: false,
    commentsError: null,
    commentsPagination: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
    },
    commentsFilters: {
      status: 'pending', // pending|approved|rejected
      type: null,        // review|question|null
      page: 1,
      per_page: 20,
    },

    // reports moderation
    reports: [],
    reportsLoading: false,
    reportsError: null,
    reportsPagination: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
    },
    reportsFilters: {
      status: 'pending', // pending|resolved|rejected
      page: 1,
      per_page: 20,
    },

    actionLoading: false,
    actionError: null,
  }),

  actions: {
    // ---------------- COMMENTS ----------------

    async fetchComments(customParams = {}) {
      this.commentsLoading = true;
      this.commentsError = null;

      try {
        const params = {
          ...this.commentsFilters,
          ...customParams,
        };

        const response = await apiClient.get('/admin/comments/moderation', { params });

        this.comments = response.data?.data ?? [];
        this.commentsPagination = {
          current_page: response.data?.current_page ?? 1,
          last_page: response.data?.last_page ?? 1,
          per_page: response.data?.per_page ?? params.per_page ?? 20,
          total: response.data?.total ?? 0,
        };

        // синхронизируем фильтры
        this.commentsFilters = {
          ...this.commentsFilters,
          ...params,
        };
      } catch (err) {
        this.commentsError = 'Помилка при завантаженні коментарів на модерацію';
        console.error(err);
        throw err;
      } finally {
        this.commentsLoading = false;
      }
    },

    async approveComment(commentId) {
      this.actionLoading = true;
      this.actionError = null;

      try {
        await apiClient.getCsrfCookie();
        await apiClient.post(`/admin/comments/${commentId}/moderate`, {
          moderation_status: 'approved',
        });

        // оптимистично обновим локально
        const idx = this.comments.findIndex(c => c.id === commentId);
        if (idx !== -1) {
          this.comments[idx] = {
            ...this.comments[idx],
            moderation_status: 'approved',
            moderation_reject_reason: null,
          };
        }
      } catch (err) {
        this.actionError = 'Помилка при підтвердженні коментаря';
        console.error(err);
        throw err;
      } finally {
        this.actionLoading = false;
      }
    },

    async rejectComment(commentId, reason) {
      this.actionLoading = true;
      this.actionError = null;

      try {
        await apiClient.getCsrfCookie();
        await apiClient.post(`/admin/comments/${commentId}/moderate`, {
          moderation_status: 'rejected',
          moderation_reject_reason: reason ?? '',
        });

        const idx = this.comments.findIndex(c => c.id === commentId);
        if (idx !== -1) {
          this.comments[idx] = {
            ...this.comments[idx],
            moderation_status: 'rejected',
            moderation_reject_reason: reason ?? '',
          };
        }
      } catch (err) {
        this.actionError = 'Помилка при відхиленні коментаря';
        console.error(err);
        throw err;
      } finally {
        this.actionLoading = false;
      }
    },

    async deleteComment(commentId) {
      this.actionLoading = true;
      this.actionError = null;

      try {
        await apiClient.getCsrfCookie();
        await apiClient.delete(`/admin/comments/${commentId}`);
        this.comments = this.comments.filter(c => c.id !== commentId);
      } catch (err) {
        this.actionError = 'Помилка при видаленні коментаря';
        console.error(err);
        throw err;
      } finally {
        this.actionLoading = false;
      }
    },

    // ---------------- REPORTS ----------------

    async fetchReports(customParams = {}) {
      this.reportsLoading = true;
      this.reportsError = null;

      try {
        const params = {
          ...this.reportsFilters,
          ...customParams,
        };

        const response = await apiClient.get('/admin/comment-reports', { params });

        this.reports = response.data?.data ?? [];
        this.reportsPagination = {
          current_page: response.data?.current_page ?? 1,
          last_page: response.data?.last_page ?? 1,
          per_page: response.data?.per_page ?? params.per_page ?? 20,
          total: response.data?.total ?? 0,
        };

        this.reportsFilters = {
          ...this.reportsFilters,
          ...params,
        };
      } catch (err) {
        this.reportsError = 'Помилка при завантаженні скарг';
        console.error(err);
        throw err;
      } finally {
        this.reportsLoading = false;
      }
    },

    async resolveReport(reportId, status, resolutionNote = null) {
      this.actionLoading = true;
      this.actionError = null;

      try {
        await apiClient.getCsrfCookie();
        await apiClient.post(`/admin/comment-reports/${reportId}/resolve`, {
          status, // resolved|rejected
          resolution_note: resolutionNote,
        });

        const idx = this.reports.findIndex(r => r.id === reportId);
        if (idx !== -1) {
          this.reports[idx] = {
            ...this.reports[idx],
            status,
            resolution_note: resolutionNote,
          };
        }
      } catch (err) {
        this.actionError = 'Помилка при обробці скарги';
        console.error(err);
        throw err;
      } finally {
        this.actionLoading = false;
      }
    },

    // ---------------- helpers ----------------

    setCommentsFilters(payload = {}) {
      this.commentsFilters = { ...this.commentsFilters, ...payload };
    },

    setReportsFilters(payload = {}) {
      this.reportsFilters = { ...this.reportsFilters, ...payload };
    },

    resetErrors() {
      this.commentsError = null;
      this.reportsError = null;
      this.actionError = null;
    },
  },
});