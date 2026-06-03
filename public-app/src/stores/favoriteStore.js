import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const STORAGE_KEY = 'public_favorites_v1';

export const useFavoriteStore = defineStore('favorite', () => {
    const productIds = ref([]);

    // Завантаження favorites з localStorage тільки для guest
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

    // Збереження в localStorage тільки для guest
    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(productIds.value));
        } catch (e) {
            // ignore
        }
    }

    // Очистка guest localStorage
    function clearLocalFavorites() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // ignore
        }
    }

    // При старті стору завантажуємо guest localStorage
    load();

    // Завантаження favorites з сервера для авторизованого користувача
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

    // Синхронізація guest localStorage -> сервер при логіні/реєстрації
    async function syncWithServer() {
        const authStore = useAuthStore();

        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            // 1. Беремо guest favorites
            const localIds = [...productIds.value].map(Number);

            // 2. Беремо server favorites
            const response = await apiClient.get('/favorites');
            const serverIds = (response.data || []).map(product => Number(product.id));

            // 3. Merge без дублікатів
            const mergedIds = [...new Set([...serverIds, ...localIds])];

            // 4. Записуємо merged state на сервер
            await apiClient.post('/favorites/sync', {
                product_ids: mergedIds,
            });

            // 5. Після sync завантажуємо актуальний server state
            await loadFromServer();

            // 6. Очищаємо guest localStorage
            clearLocalFavorites();
        } catch (error) {
            console.error('Помилка синхронізації обраного:', error);
        }
    }

    function isFavorite(productId) {
        return productIds.value.includes(Number(productId));
    }

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

    async function toggleFavorite(productId) {
        if (isFavorite(productId)) {
            await removeFavorite(productId);
        } else {
            await addFavorite(productId);
        }
    }

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