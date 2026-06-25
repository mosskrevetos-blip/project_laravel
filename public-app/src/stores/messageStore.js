import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useMessageStore = defineStore('messageStore', {
  state: () => ({
    conversations: [],
    currentConversation: null,
    messages: [],
    messagesPagination: null,
    loadingConversations: false,
    loadingMessages: false,
    sending: false,
    drawerOpen: false,
    currentProductContextId: null,

    // Непрочитанные сообщения от администратора/менеджера
    adminUnreadCount: 0,
  }),

  getters: {
    totalUnread(state) {
      return state.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
    },

    unreadThreadsCount(state) {
      return state.conversations.filter(c => (c.unread_count || 0) > 0).length;
    },

    // Сумма непрочитанных: диалоги + админ-сообщения
    totalUnreadCombined(state) {
      const unreadDialogsCount = state.conversations.filter(c => (c.unread_count || 0) > 0).length;
      return unreadDialogsCount + Number(state.adminUnreadCount || 0);
    },

    // Если где-то нужен "кол-во потоков" + админ-кол-во
    unreadThreadsCountCombined(state) {
      const dialogsUnreadThreads = state.conversations.filter(c => (c.unread_count || 0) > 0).length;
      return dialogsUnreadThreads + Number(state.adminUnreadCount || 0);
    },
  },

  actions: {
    normalizeConversation(raw) {
      const c = raw || {};

      const reportsTotal =
        Number(c.reports_total_count ?? c.reportsTotalCount ?? 0) || 0;

      const reportsOpen =
        Number(c.reports_open_count ?? c.reportsOpenCount ?? 0) || 0;

      const lastReport = c.last_report ?? c.lastReport ?? null;

      return {
        ...c,
        reports_total_count: reportsTotal,
        reports_open_count: reportsOpen,
        last_report: lastReport,
      };
    },

    async loadAdminUnreadCount({ forceForRoles = false } = {}) {
      try {
        const { useAuthStore } = await import('@/stores/authStore');
        const authStore = useAuthStore();

        // Для admin/manager unread admin-messages не считаем (0)
        if (!forceForRoles && (authStore.hasRole('admin') || authStore.hasRole('manager'))) {
          this.adminUnreadCount = 0;
          return;
        }

        const { data } = await apiClient.get('/admin-messages/unread-count');
        this.adminUnreadCount = Number(data?.unread_count || 0);
      } catch {
        this.adminUnreadCount = 0;
      }
    },

    async refreshCurrentConversation() {
      if (!this.currentConversation?.id) return;
      const { data } = await apiClient.get(`/conversations/${this.currentConversation.id}`);
      this.currentConversation = this.normalizeConversation(data);
    },

    setDrawerOpen(v) {
      this.drawerOpen = v;
      if (!v) {
        this.currentConversation = null;
        this.messages = [];
        this.messagesPagination = null;
        this.clearProductContext();
      }
    },

    async pingPresence() {
      await apiClient.getCsrfCookie();
      await apiClient.post('/presence/ping');
    },

    async loadConversations() {
      this.loadingConversations = true;
      try {
        const { data } = await apiClient.get('/conversations');
        this.conversations = Array.isArray(data) ? data : [];

        // Подтягиваем unread admin messages вместе с диалогами
        await this.loadAdminUnreadCount();
      } finally {
        this.loadingConversations = false;
      }
    },

    async openWithSeller(sellerId, { productId = null } = {}) {
      this.setProductContext(productId);

      await apiClient.getCsrfCookie();

      if (!productId) {
        throw new Error('product_id is required to open chat');
      }

      const { data } = await apiClient.post(`/conversations/with-seller/${sellerId}`, {
        product_id: productId,
      });

      this.currentConversation = this.normalizeConversation(data);
      this.drawerOpen = true;

      await this.loadMessages(this.currentConversation.id);
      await this.markRead(this.currentConversation.id);
      await this.loadConversations();
      await this.refreshCurrentConversation();
    },

    async loadMessages(conversationId, { perPage = 30 } = {}) {
      this.loadingMessages = true;
      try {
        const { data } = await apiClient.get(`/conversations/${conversationId}/messages`, {
          params: { per_page: perPage },
        });

        this.messagesPagination = data;
        this.messages = (data?.data || []).slice().reverse();
      } finally {
        this.loadingMessages = false;
      }
    },

    async sendMessage({ conversationId, body = '', productId = null, imageFile = null }) {
      this.sending = true;
      try {
        await apiClient.getCsrfCookie();

        const form = new FormData();
        if (body && body.trim()) form.append('body', body.trim());

        const effectiveProductId = productId ?? this.currentProductContextId;
        if (!effectiveProductId) {
          throw new Error('product_id is required to send a message');
        }
        form.append('product_id', String(effectiveProductId));

        if (imageFile) form.append('image', imageFile);

        const { data } = await apiClient.post(`/conversations/${conversationId}/messages`, form, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });

        this.messages.push(data);

        await this.loadConversations();
        await this.refreshCurrentConversation();
      } finally {
        this.sending = false;
      }
    },

    async markRead(conversationId) {
      await apiClient.getCsrfCookie();
      await apiClient.post(`/conversations/${conversationId}/read`);
    },

    setProductContext(productId) {
      this.currentProductContextId = productId ? Number(productId) : null;
    },

    clearProductContext() {
      this.currentProductContextId = null;
    },
  },
});