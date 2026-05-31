// public-app/src/stores/favoriteStore.js
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const STORAGE_KEY = 'public_favorites_v1';
const EVENTS_SYNC_KEY = 'public_favorites_events_last_sync_at';

export const useFavoriteStore = defineStore('favorite', () => {
    const productIds = ref([]);

    // Функція для відновлення стану з localStorage
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

    // Зберігає поточний стан в localStorage
    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(productIds.value));
        } catch (e) {
            // ignore
        }
    }

    // Відновлює стан при ініціалізації
    load();

    // Завантаження обраного з сервера (для авторизованих)
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

            console.log('favorites events params:', params);
            console.log('favorites events response:', events);

            for (const event of events) {
                if (event.event_type === 'remove') {
                    const pid = Number(event.product_id);
                    productIds.value = productIds.value.filter(id => id !== pid);
                }
            }

            persist();

            // Оновлюємо курсор тільки якщо реально отримали події
            if (events.length > 0) {
                const lastCreatedAt = events[events.length - 1].created_at;
                localStorage.setItem(EVENTS_SYNC_KEY, lastCreatedAt);
            }
        } catch (error) {
            console.error('Помилка застосування серверних подій до localStorage:', error);
        }
    }

    // Синхронізація обраного при авторизації
    async function syncWithServer() {
        const authStore = useAuthStore();

        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            // Спочатку застосовуємо серверні події видалення до localStorage
            await applyServerEventsToLocal();

            // Потім localStorage є джерелом істини і відправляється на сервер
            await apiClient.post('/favorites/sync', {
                product_ids: productIds.value,
            });

            // Після синхронізації завантажуємо оновлений список з сервера
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

        // Якщо вже в обраному — нічого не робимо
        if (isFavorite(pid)) return;

        // Якщо авторизований — працюємо з сервером
        if (authStore.isAuthenticated) {
            try {
                await apiClient.post('/favorites', { product_id: pid });
                await loadFromServer();
            } catch (error) {
                console.error('Помилка додавання в обране на сервері:', error);
                throw error;
            }
        } else {
            // Гість — працюємо з localStorage
            productIds.value.push(pid);
            persist();
        }
    }

    // Видалити товар з обраного
    async function removeFavorite(productId) {
        const authStore = useAuthStore();
        const pid = Number(productId);

        // Якщо авторизований — працюємо з сервером
        if (authStore.isAuthenticated) {
            try {
                await apiClient.delete(`/favorites/${pid}`);
                await loadFromServer();
            } catch (error) {
                console.error('Помилка видалення з обраного на сервері:', error);
                throw error;
            }
        } else {
            // Гість — працюємо з localStorage
            productIds.value = productIds.value.filter(id => id !== pid);
            persist();
        }
    }

    // Переключити стан обраного (додати/видалити)
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

    // Обчислювана властивість: кількість товарів в обраному
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