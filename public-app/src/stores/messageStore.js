//public-app/src/stores/messageStore.js

import { defineStore } from 'pinia';
import apiClient from '@/api';

export const useMessageStore = defineStore('messageStore', {
  state: () => ({
    conversations: [],
    currentConversation: null,
    messages: [],
    messagesPagination: null, // laravel paginate
    loadingConversations: false,
    loadingMessages: false,
    sending: false,
    drawerOpen: false,
    // контекст товара для чата
    currentProductContextId: null,
  }),

  getters: {
    totalUnread(state) {
      return state.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
    },

    unreadThreadsCount(state) {
      return state.conversations.filter(c => (c.unread_count || 0) > 0).length;
    },
  },

  actions: {

    async refreshCurrentConversation() {
      if (!this.currentConversation?.id) return;
      const { data } = await apiClient.get(`/conversations/${this.currentConversation.id}`);
      this.currentConversation = data;
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
        this.conversations = data;
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
      
      this.currentConversation = data;
      this.drawerOpen = true;

      await this.loadMessages(data.id);
      await this.markRead(data.id);
      await this.loadConversations();
    },

    async loadMessages(conversationId, { perPage = 30 } = {}) {
      this.loadingMessages = true;
      try {
        const { data } = await apiClient.get(`/conversations/${conversationId}/messages`, {
          params: { per_page: perPage },
        });

        // Laravel paginate: data.data = items
        this.messagesPagination = data;
        this.messages = (data.data || []).slice().reverse(); // в UI снизу вверх
      } finally {
        this.loadingMessages = false;
      }
    },

    async sendMessage({ conversationId, body = '', productId = null, imageFile = null }) {
      this.sending = true;
      try {
        await apiClient.getCsrfCookie();

        const form = new FormData();
        if (body && body.trim()) form.append('body', body);
        
        const effectiveProductId = productId ?? this.currentProductContextId;
        if (!effectiveProductId) {
          throw new Error('product_id is required to send a message');
        }
        form.append('product_id', String(effectiveProductId));
        
        if (imageFile) form.append('image', imageFile);

        const { data } = await apiClient.post(`/conversations/${conversationId}/messages`, form, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });

        // append to bottom
        this.messages.push(data);

        // обновим список диалогов (last message / sorting / unread)
        await this.loadConversations();
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