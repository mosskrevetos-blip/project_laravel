import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const STORAGE_KEY = 'public_favorites_v1';

export const useFavoriteStore = defineStore('favorite', () => {
    const productIds = ref([]);

    /**
     * Завантажити favorites з localStorage
     * Використовується тільки для guest-режиму
     */
    function load() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);

            if (raw) {
                productIds.value = JSON.parse(raw).map(Number);
            } else {
                productIds.value = [];
            }
        } catch (e) {
            productIds.value = [];
        }
    }

    /**
     * Зберегти favorites у localStorage
     * Використовується тільки для guest-режиму
     */
    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(productIds.value));
        } catch (e) {
            // ignore
        }
    }

    /**
     * Повністю очистити guest localStorage
     */
    function clearLocalFavorites() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // ignore
        }
    }

    // Ініціалізуємо store з localStorage
    load();

    /**
     * Завантажити favorites з сервера для авторизованого користувача
     */
    async function loadFromServer() {
        const authStore = useAuthStore();

        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            const response = await apiClient.get('/favorites');
            productIds.value = (response.data || []).map(product => Number(product.id));
        } catch (error) {
            console.error('Помилка завантаження обраного з сервера:', error);
        }
    }

    /**
     * Синхронізація при логіні:
     * 1. беремо guest favorites з localStorage
     * 2. беремо current server favorites
     * 3. merge без дублікатів
     * 4. відправляємо merged список на сервер
     * 5. знову читаємо server state
     * 6. очищаємо localStorage, бо після логіну джерело істини — сервер
     */
    async function syncWithServer() {
        const authStore = useAuthStore();

        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            // 1. Те, що було у гостя
            const localIds = [...productIds.value].map(Number);

            // 2. Те, що вже є на сервері
            const response = await apiClient.get('/favorites');
            const serverIds = (response.data || []).map(product => Number(product.id));

            // 3. Об'єднуємо без дублікатів
            const mergedIds = [...new Set([...serverIds, ...localIds])];

            // 4. Записуємо merged state на сервер
            await apiClient.post('/favorites/sync', {
                product_ids: mergedIds,
            });

            // 5. Оновлюємо store з сервера
            await loadFromServer();

            // 6. Очищаємо guest localStorage
            clearLocalFavorites();
        } catch (error) {
            console.error('Помилка синхронізації обраного:', error);
        }
    }

    /**
     * Перевірити, чи товар у favorites
     */
    function isFavorite(productId) {
        return productIds.value.includes(Number(productId));
    }

    /**
     * Додати товар в favorites
     */
    async function addFavorite(productId) {
        const authStore = useAuthStore();
        const pid = Number(productId);

        if (isFavorite(pid)) return;

        if (authStore.isAuthenticated) {
            try {
                await apiClient.post('/favorites', { product_id: pid });
                await loadFromServer();
            } catch (error) {
                console.error('Помилка додавання в обране на сервері:', error);
                throw error;
            }
        } else {
            productIds.value.push(pid);
            persist();
        }
    }

    /**
     * Видалити товар з favorites
     */
    async function removeFavorite(productId) {
        const authStore = useAuthStore();
        const pid = Number(productId);

        if (authStore.isAuthenticated) {
            try {
                await apiClient.delete(`/favorites/${pid}`);
                await loadFromServer();
            } catch (error) {
                console.error('Помилка видалення з обраного на сервері:', error);
                throw error;
            }
        } else {
            productIds.value = productIds.value.filter(id => id !== pid);
            persist();
        }
    }

    /**
     * Переключити стан favorites
     */
    async function toggleFavorite(productId) {
        if (isFavorite(productId)) {
            await removeFavorite(productId);
        } else {
            await addFavorite(productId);
        }
    }

    /**
     * Очистити favorites в guest-режимі
     */
    function clear() {
        productIds.value = [];
        persist();
    }

    const totalFavorites = computed(() => productIds.value.length);

    return {
        productIds,
        totalFavorites,
        isFavorite,
        addFavorite,
        removeFavorite,
        toggleFavorite,
        clear,
        clearLocalFavorites,
        loadFromServer,
        syncWithServer,
    };
});