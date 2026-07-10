import { defineStore } from 'pinia';
import apiClient from '@/api';

function hasBinaryImages(arr) {
  return Array.isArray(arr) && arr.some((f) => f instanceof File);
}

function normalizeErrorMessage(err, fallback) {
  const data = err?.response?.data;
  if (!data) return fallback;

  if (typeof data?.message === 'string' && data.message.trim()) {
    return data.message;
  }

  // Laravel validation errors
  if (data?.errors && typeof data.errors === 'object') {
    const firstKey = Object.keys(data.errors)[0];
    if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
      return data.errors[firstKey][0];
    }
  }

  return fallback;
}

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

        this.commentsFilters = {
          ...this.commentsFilters,
          ...params,
        };
      } catch (err) {
        this.commentsError = normalizeErrorMessage(err, 'Помилка при завантаженні коментарів на модерацію');
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

        const idx = this.comments.findIndex((c) => c.id === commentId);
        if (idx !== -1) {
          this.comments[idx] = {
            ...this.comments[idx],
            moderation_status: 'approved',
            moderation_reject_reason: null,
          };
        }
      } catch (err) {
        this.actionError = normalizeErrorMessage(err, 'Помилка при підтвердженні коментаря');
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

        const idx = this.comments.findIndex((c) => c.id === commentId);
        if (idx !== -1) {
          this.comments[idx] = {
            ...this.comments[idx],
            moderation_status: 'rejected',
            moderation_reject_reason: reason ?? '',
          };
        }
      } catch (err) {
        this.actionError = normalizeErrorMessage(err, 'Помилка при відхиленні коментаря');
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

        // remove root comment if it's root
        this.comments = this.comments.filter((c) => c.id !== commentId);

        // remove answer from nested answers
        this.comments = this.comments.map((c) => ({
          ...c,
          answers: Array.isArray(c.answers)
            ? c.answers.filter((a) => a.id !== commentId)
            : c.answers,
        }));
      } catch (err) {
        this.actionError = normalizeErrorMessage(err, 'Помилка при видаленні коментаря');
        console.error(err);
        throw err;
      } finally {
        this.actionLoading = false;
      }
    },

    /**
     * payload:
     * {
     *   body?: string|null,
     *   pros?: string|null,
     *   cons?: string|null,
     *   rating?: number|null,
     *   youtube_url?: string|null,
     *   media_sync?: Array<{id|null,type,url|null,external_url|null,sort_order:number}>,
     *   remove_media_ids?: number[],
     *   new_images?: File[]
     * }
     */
    async updateComment(commentId, payload = {}) {
      this.actionLoading = true;
      this.actionError = null;

      try {
        await apiClient.getCsrfCookie();

        const hasFiles = hasBinaryImages(payload.new_images);
        const hasMediaSync = Array.isArray(payload.media_sync);
        const hasRemoveIds = Array.isArray(payload.remove_media_ids) && payload.remove_media_ids.length > 0;
        const hasYoutube = Object.prototype.hasOwnProperty.call(payload, 'youtube_url');

        let response;

        // If changing media / uploading files -> multipart (POST + _method=PUT)
        if (hasFiles || hasMediaSync || hasRemoveIds || hasYoutube) {
          const form = new FormData();

          if (Object.prototype.hasOwnProperty.call(payload, 'body')) {
            form.append('body', payload.body ?? '');
          }
          if (Object.prototype.hasOwnProperty.call(payload, 'pros')) {
            form.append('pros', payload.pros ?? '');
          }
          if (Object.prototype.hasOwnProperty.call(payload, 'cons')) {
            form.append('cons', payload.cons ?? '');
          }
          if (Object.prototype.hasOwnProperty.call(payload, 'rating')) {
            form.append('rating', payload.rating == null ? '' : String(payload.rating));
          }

          // optional youtube URL
          if (hasYoutube) {
            form.append('youtube_url', payload.youtube_url ?? '');
          }

          // new images
          if (Array.isArray(payload.new_images)) {
            payload.new_images.forEach((file) => {
              if (file instanceof File) form.append('images[]', file);
            });
          }

          // remove specific media ids (legacy path)
          if (hasRemoveIds) {
            form.append('remove_media_ids', JSON.stringify(payload.remove_media_ids));
          }

          // full media sync (keep/reorder/create youtube entries)
          // IMPORTANT: backend request now decodes JSON string in prepareForValidation()
          if (hasMediaSync) {
            form.append('media_sync', JSON.stringify(payload.media_sync));
          }

          // Laravel method spoofing for multipart update
          form.append('_method', 'PUT');

          response = await apiClient.post(`/admin/comments/${commentId}`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
          });
        } else {
          // text-only update
          const body = {
            body: payload.body ?? null,
            pros: payload.pros ?? null,
            cons: payload.cons ?? null,
            rating: typeof payload.rating === 'undefined' ? null : payload.rating,
          };

          response = await apiClient.put(`/admin/comments/${commentId}`, body);
        }

        const updated = response?.data?.data ?? null;

        // 1) update root if needed
        const rootIdx = this.comments.findIndex((c) => c.id === commentId);
        if (rootIdx !== -1) {
          this.comments[rootIdx] = {
            ...this.comments[rootIdx],
            ...(updated || payload),
          };
        }

        // 2) update nested answer if needed
        this.comments = this.comments.map((c) => {
          if (!Array.isArray(c.answers)) return c;

          const ansIdx = c.answers.findIndex((a) => a.id === commentId);
          if (ansIdx === -1) return c;

          const nextAnswers = [...c.answers];
          nextAnswers[ansIdx] = {
            ...nextAnswers[ansIdx],
            ...(updated || payload),
          };

          return {
            ...c,
            answers: nextAnswers,
          };
        });

        return updated;
      } catch (err) {
        this.actionError = normalizeErrorMessage(err, 'Помилка при редагуванні коментаря');
        console.error(err?.response?.data || err);
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
        this.reportsError = normalizeErrorMessage(err, 'Помилка при завантаженні скарг');
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

        const idx = this.reports.findIndex((r) => r.id === reportId);
        if (idx !== -1) {
          this.reports[idx] = {
            ...this.reports[idx],
            status,
            resolution_note: resolutionNote,
          };
        }
      } catch (err) {
        this.actionError = normalizeErrorMessage(err, 'Помилка при обробці скарги');
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