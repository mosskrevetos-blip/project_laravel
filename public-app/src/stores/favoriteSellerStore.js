// public-app/src/stores/favoriteSellerStore.js
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const STORAGE_KEY = 'public_favorite_sellers_v1';

export const useFavoriteSellerStore = defineStore('favoriteSeller', () => {
  
    const sellerIds = ref([]);

    // Функція для відновлення стану з localStorage
    function load() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) sellerIds.value = JSON.parse(raw);
        } catch (e) {
            sellerIds.value = [];
        }
    }

    // Зберігає поточний стан в localStorage
    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(sellerIds.value));
        } catch (e) {
            // ignore
        }
    }

    // Відновлює стан при ініціалізації
    load();

    // Завантаження обраних продавців з сервера
    async function loadFromServer() {
        const authStore = useAuthStore();
        
        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            const response = await apiClient.get('/favorite-sellers');
            sellerIds.value = response.data.map(seller => seller.id);
            persist();
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

        // Якщо вже в обраному — нічого не робимо
        if (isFavoriteSeller(sid)) return;

        // Якщо авторизований — працюємо з сервером
        if (authStore.isAuthenticated) {
            try {
                await apiClient.post('/favorite-sellers', { seller_id: sid });
                await loadFromServer();
            } catch (error) {
                console.error('Помилка додавання продавця в обране:', error);
                throw error;
            }
        } else {
            // Гість — працюємо з localStorage
            sellerIds.value.push(sid);
            persist();
        }
    }

    // Видалити продавця з обраного
    async function removeFavoriteSeller(sellerId) {
        const authStore = useAuthStore();
        const sid = Number(sellerId);

        // Якщо авторизований — працюємо з сервером
        if (authStore.isAuthenticated) {
            try {
                await apiClient.delete(`/favorite-sellers/${sid}`);
                await loadFromServer();
            } catch (error) {
                console.error('Помилка видалення продавця з обраного:', error);
                throw error;
            }
        } else {
            // Гість — працюємо з localStorage
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


    // Синхронізувати локальні обрані продавці з сервером після авторизації
    async function syncWithServer() {
        const authStore = useAuthStore();

        if (!authStore.isAuthenticated) {
            return;
        }

        try {
            await apiClient.post('/favorite-sellers/sync', {
                seller_ids: sellerIds.value,
            });

            await loadFromServer();
        } catch (error) {
            console.error('Помилка синхронізації обраних продавців:', error);
        }
    }

    // Очистити обраних продавців
    function clear() {
        sellerIds.value = [];
        persist();
    }

    // Обчислювана властивість: кількість обраних продавців
    const totalFavoriteSellers = computed(() => sellerIds.value.length);

    return {
        sellerIds,
        totalFavoriteSellers,
        isFavoriteSeller,
        addFavoriteSeller,
        removeFavoriteSeller,
        toggleFavoriteSeller,
        clear,
        loadFromServer,
        syncWithServer,
    };
});