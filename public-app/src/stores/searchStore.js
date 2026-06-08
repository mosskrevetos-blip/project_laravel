import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const STORAGE_KEY = 'public_search_history_v1';
const HISTORY_LIMIT = 10;
const SUGGESTIONS_LIMIT = 8;

export const useSearchStore = defineStore('search', () => {
  const query = ref('');
  const results = ref([]);
  const loading = ref(false);
  const error = ref(null);

  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
  });

  const links = ref({
    first: null,
    last: null,
    prev: null,
    next: null,
  });

  const history = ref([]);
  const suggestions = ref([]);
  const suggestionsLoading = ref(false);

  let suggestionsDebounce = null;

  function normalizeQuery(value) {
    return String(value || '').trim();
  }

  // =========================
  // localStorage history
  // =========================
  function loadLocalHistory() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        history.value = [];
        return;
      }

      const parsed = JSON.parse(raw);
      history.value = Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      history.value = [];
    }
  }

  function persistLocalHistory() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(history.value));
    } catch (e) {
      // ignore
    }
  }

  function clearLocalHistory() {
    history.value = [];
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
      // ignore
    }
  }

  // =========================
  // history
  // =========================
  async function loadHistoryFromServer() {
    const authStore = useAuthStore();

    if (!authStore.isAuthenticated) {
      return;
    }

    try {
      const response = await apiClient.get('/search/history');
      history.value = Array.isArray(response.data) ? response.data : [];
    } catch (err) {
      console.error('Помилка завантаження історії пошуку:', err);
    }
  }

  async function recordSearch({ query: rawQuery, resultCount }) {
    const authStore = useAuthStore();
    const normalized = normalizeQuery(rawQuery);

    if (!normalized) {
      return;
    }

    const item = {
      query: normalized,
      result_count: Number(resultCount || 0),
      searched_at: new Date().toISOString(),
    };

    if (authStore.isAuthenticated) {
      try {
        await apiClient.post('/search/history', item);
        await loadHistoryFromServer();
      } catch (err) {
        console.error('Помилка збереження пошукового запиту:', err);
      }
    } else {
      history.value = [item, ...history.value].slice(0, HISTORY_LIMIT);
      persistLocalHistory();
    }
  }

  async function syncWithServer() {
    const authStore = useAuthStore();

    if (!authStore.isAuthenticated) {
      return;
    }

    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const localItems = raw ? JSON.parse(raw) : [];

      const validItems = Array.isArray(localItems)
        ? localItems.filter(item => normalizeQuery(item?.query))
        : [];

      if (validItems.length > 0) {
        await apiClient.post('/search/history/sync', {
          items: validItems.map(item => ({
            query: normalizeQuery(item.query),
            result_count: Number(item.result_count || 0),
            searched_at: item.searched_at || new Date().toISOString(),
          })),
        });
      }

      clearLocalHistory();
      await loadHistoryFromServer();
    } catch (err) {
      console.error('Помилка синхронізації історії пошуку:', err);
    }
  }

  async function removeHistoryItem(item) {
    const authStore = useAuthStore();

    if (authStore.isAuthenticated) {
      if (!item?.id) {
        await loadHistoryFromServer();
        return;
      }

      try {
        await apiClient.delete(`/search/history/${item.id}`);
        history.value = history.value.filter(historyItem => historyItem.id !== item.id);
      } catch (err) {
        console.error('Помилка видалення запису з історії:', err);
      }

      return;
    }

    history.value = history.value.filter(historyItem => {
      return !(
        historyItem.query === item.query &&
        historyItem.searched_at === item.searched_at
      );
    });

    persistLocalHistory();
  }

  async function clearAllHistory() {
    const authStore = useAuthStore();

    if (authStore.isAuthenticated) {
      try {
        await apiClient.delete('/search/history');
        history.value = [];
      } catch (err) {
        console.error('Помилка очищення історії пошуку:', err);
      }

      return;
    }

    clearLocalHistory();
  }

  // =========================
  // search
  // =========================
  async function searchProducts({ query: rawQuery, page = 1, perPage = 12 }) {
    const normalized = normalizeQuery(rawQuery);

    if (!normalized) {
      results.value = [];
      pagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: perPage,
        total: 0,
      };
      links.value = {
        first: null,
        last: null,
        prev: null,
        next: null,
      };
      return {
        results: [],
        total: 0,
      };
    }

    loading.value = true;
    error.value = null;

    try {
      const response = await apiClient.get('/search/products', {
        params: {
          q: normalized,
          page,
          per_page: perPage,
        },
      });

      results.value = response.data.data || [];
      pagination.value = response.data.meta || {
        current_page: 1,
        last_page: 1,
        per_page: perPage,
        total: 0,
      };
      links.value = response.data.links || {
        first: null,
        last: null,
        prev: null,
        next: null,
      };

      return {
        results: results.value,
        total: pagination.value.total || 0,
      };
    } catch (err) {
      error.value = 'Помилка при пошуку товарів';
      console.error(err);

      results.value = [];
      pagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: perPage,
        total: 0,
      };
      links.value = {
        first: null,
        last: null,
        prev: null,
        next: null,
      };

      return {
        results: [],
        total: 0,
      };
    } finally {
      loading.value = false;
    }
  }

  // =========================
  // suggestions
  // =========================
  async function fetchSuggestions(rawQuery) {
    const normalized = normalizeQuery(rawQuery);

    if (!normalized) {
      suggestions.value = [];
      return;
    }

    suggestionsLoading.value = true;

    try {
      const response = await apiClient.get('/search/suggestions', {
        params: { q: normalized },
      });

      suggestions.value = Array.isArray(response.data)
        ? response.data.slice(0, SUGGESTIONS_LIMIT)
        : [];
    } catch (err) {
      console.error('Помилка завантаження підказок:', err);
      suggestions.value = [];
    } finally {
      suggestionsLoading.value = false;
    }
  }

  function debounceFetchSuggestions(rawQuery, delay = 300) {
    clearTimeout(suggestionsDebounce);

    suggestionsDebounce = setTimeout(() => {
      fetchSuggestions(rawQuery);
    }, delay);
  }

  function clearSuggestions() {
    suggestions.value = [];
  }

  // =========================
  // helpers
  // =========================
  function setQuery(value) {
    query.value = value;
  }

  function clearResults() {
    results.value = [];
    pagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 0,
    };
    links.value = {
      first: null,
      last: null,
      prev: null,
      next: null,
    };
  }

  function submitSearch(rawQuery, router) {
    const normalized = normalizeQuery(rawQuery);

    if (!normalized) {
        return;
    }

    query.value = normalized;

    router.push({
        name: 'search.results',
        query: {
        q: normalized,
        page: 1,
        record: '1',
        ts: String(Date.now()),
        },
    });
    }

  const filteredHistory = computed(() => {
    const normalized = normalizeQuery(query.value);

    if (!normalized) {
      return history.value.slice(0, HISTORY_LIMIT);
    }

    return history.value
      .filter(item =>
        String(item.query || '')
          .toLowerCase()
          .includes(normalized.toLowerCase())
      )
      .slice(0, HISTORY_LIMIT);
  });

  loadLocalHistory();

  return {
    query,
    results,
    loading,
    error,
    pagination,
    links,
    history,
    suggestions,
    suggestionsLoading,
    filteredHistory,
    loadLocalHistory,
    persistLocalHistory,
    clearLocalHistory,
    loadHistoryFromServer,
    recordSearch,
    syncWithServer,
    removeHistoryItem,
    clearAllHistory,
    searchProducts,
    fetchSuggestions,
    debounceFetchSuggestions,
    clearSuggestions,
    setQuery,
    clearResults,
    submitSearch,
    normalizeQuery,
  };
});