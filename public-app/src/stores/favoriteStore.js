import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const STORAGE_KEY = 'public_favorites_v1';
const EVENTS_SYNC_KEY = 'public_favorites_events_last_sync_at';

export const useFavoriteStore = defineStore('favorite', () => {
    const productIds = ref([]);

    // Завантаження localStorage
    function load() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                productIds.value = JSON.parse(raw);
            } else {
                productIds.value = [];
            }
        } catch (e) {
            productIds.value = [];
        }
    }

    // Збереження в localStorage
    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(productIds.value));
        } catch (e) {
            // ignore
        }
    }

    // Ініціалізація зі сховища
    load();

    // Завантаження з сервера
    async function loadFromServer() {
        const authStore = useAuthStore();

        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            const response = await apiClient.get('/favorites');
            productIds.value = response.data.map(product => product.id);
            persist();
        } catch (error) {
            console.error('Помилка завантаження обраного з сервера:', error);
        }
    }

    // Застосувати серверні події до localStorage
    async function applyServerEventsToLocal() {
        const authStore = useAuthStore();

        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            const since = localStorage.getItem(EVENTS_SYNC_KEY);
            const params = since ? { since } : {};

            const response = await apiClient.get('/favorites/events', { params });
            const events = response.data || [];

            for (const event of events) {
                if (event.event_type === 'remove') {
                    const pid = Number(event.product_id);
                    productIds.value = productIds.value.filter(id => id !== pid);
                }
            }

            persist();

            if (events.length > 0) {
                const lastCreatedAt = events[events.length - 1].created_at;
                localStorage.setItem(EVENTS_SYNC_KEY, lastCreatedAt);
            } else if (!since) {
                localStorage.setItem(EVENTS_SYNC_KEY, new Date().toISOString());
            }
        } catch (error) {
            console.error('Помилка застосування серверних подій до localStorage:', error);
        }
    }

    // Синхронізація localStorage -> сервер
    async function syncWithServer() {
        const authStore = useAuthStore();

        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            // Спочатку враховуємо admin/manager видалення
            await applyServerEventsToLocal();

            // Потім localStorage є джерелом істини
            await apiClient.post('/favorites/sync', {
                product_ids: productIds.value,
            });

            // Завантажуємо фінальний стан з сервера
            await loadFromServer();
            persist();
        } catch (error) {
            console.error('Помилка синхронізації обраного:', error);
        }
    }

    // Перевірити, чи товар в обраному
    function isFavorite(productId) {
        return productIds.value.includes(Number(productId));
    }

    // Додати товар в обране
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

    // Видалити товар з обраного
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

    // Переключити стан обраного
    async function toggleFavorite(productId) {
        if (isFavorite(productId)) {
            await removeFavorite(productId);
        } else {
            await addFavorite(productId);
        }
    }

    // Очистити обране
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
        loadFromServer,
        syncWithServer,
        applyServerEventsToLocal,
    };
});