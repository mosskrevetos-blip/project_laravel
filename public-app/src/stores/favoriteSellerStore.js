import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const STORAGE_KEY = 'public_favorite_sellers_v1';

export const useFavoriteSellerStore = defineStore('favoriteSeller', () => {
    const sellerIds = ref([]);

    // Завантажити favorite sellers з localStorage тільки для guest
    function load() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                sellerIds.value = JSON.parse(raw).map(Number);
            } else {
                sellerIds.value = [];
            }
        } catch (e) {
            sellerIds.value = [];
        }
    }

    // Зберегти favorite sellers в localStorage тільки для guest
    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(sellerIds.value));
        } catch (e) {
            // ignore
        }
    }

    // Очистити guest localStorage
    function clearLocalFavoriteSellers() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // ignore
        }
    }

    // Ініціалізація зі сховища
    load();

    // Завантаження обраних продавців із сервера
    async function loadFromServer() {
        const authStore = useAuthStore();

        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            const response = await apiClient.get('/favorite-sellers');
            sellerIds.value = (response.data || []).map(seller => Number(seller.id));
        } catch (error) {
            console.error('Помилка завантаження обраних продавців з сервера:', error);
        }
    }

    // Перевірити, чи продавець в обраному
    function isFavoriteSeller(sellerId) {
        return sellerIds.value.includes(Number(sellerId));
    }

    // Додати продавця в обране
    async function addFavoriteSeller(sellerId) {
        const authStore = useAuthStore();
        const sid = Number(sellerId);

        if (isFavoriteSeller(sid)) return;

        if (authStore.isAuthenticated) {
            try {
                await apiClient.post('/favorite-sellers', { seller_id: sid });
                await loadFromServer();
            } catch (error) {
                console.error('Помилка додавання продавця в обране:', error);
                throw error;
            }
        } else {
            sellerIds.value.push(sid);
            persist();
        }
    }

    // Видалити продавця з обраного
    async function removeFavoriteSeller(sellerId) {
        const authStore = useAuthStore();
        const sid = Number(sellerId);

        if (authStore.isAuthenticated) {
            try {
                await apiClient.delete(`/favorite-sellers/${sid}`);
                await loadFromServer();
            } catch (error) {
                console.error('Помилка видалення продавця з обраного:', error);
                throw error;
            }
        } else {
            sellerIds.value = sellerIds.value.filter(id => id !== sid);
            persist();
        }
    }

    // Переключити стан обраного продавця
    async function toggleFavoriteSeller(sellerId) {
        if (isFavoriteSeller(sellerId)) {
            await removeFavoriteSeller(sellerId);
        } else {
            await addFavoriteSeller(sellerId);
        }
    }

    // Синхронізація guest localStorage -> сервер при логіні/реєстрації
    async function syncWithServer() {
        const authStore = useAuthStore();

        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            // 1. Беремо guest sellers
            const localIds = [...sellerIds.value].map(Number);

            // 2. Беремо server sellers
            const response = await apiClient.get('/favorite-sellers');
            const serverIds = (response.data || []).map(seller => Number(seller.id));

            // 3. Merge без дублікатів
            const mergedIds = [...new Set([...serverIds, ...localIds])];

            // 4. Синхронізуємо на сервер
            await apiClient.post('/favorite-sellers/sync', {
                seller_ids: mergedIds,
            });

            // 5. Завантажуємо актуальний server state
            await loadFromServer();

            // 6. Після логіну guest localStorage очищаємо
            clearLocalFavoriteSellers();
        } catch (error) {
            console.error('Помилка синхронізації обраних продавців:', error);
        }
    }

    // Очистити store для guest режиму
    function clear() {
        sellerIds.value = [];
        persist();
    }

    const totalFavoriteSellers = computed(() => sellerIds.value.length);

    return {
        sellerIds,
        totalFavoriteSellers,
        isFavoriteSeller,
        addFavoriteSeller,
        removeFavoriteSeller,
        toggleFavoriteSeller,
        clear,
        clearLocalFavoriteSellers,
        loadFromServer,
        syncWithServer,
    };
});