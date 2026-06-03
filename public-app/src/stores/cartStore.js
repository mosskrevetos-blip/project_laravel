import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const STORAGE_KEY = 'public_cart_v1';

export const useCartStore = defineStore('cart', () => {
  const items = ref([]);

  // Завантаження кошика з localStorage (тільки guest режим)
  function load() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        items.value = JSON.parse(raw);
      } else {
        items.value = [];
      }
    } catch (e) {
      items.value = [];
    }
  }

  // Збереження кошика в localStorage (тільки guest режим)
  function persist() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items.value));
    } catch (e) {
      // ignore
    }
  }

  // Очистка guest localStorage
  function clearLocalCart() {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
      // ignore
    }
  }

  // Ініціалізація зі сховища
  load();

  function findIndex(productId) {
    return items.value.findIndex(i => Number(i.product_id) === Number(productId));
  }

  // Завантаження кошика з сервера
  async function loadFromServer() {
    const authStore = useAuthStore();

    if (!authStore.isAuthenticated) {
      return;
    }

    try {
      const response = await apiClient.get('/cart');

      items.value = response.data.items.map(item => ({
        id: item.id,
        product_id: item.product_id,
        title: item.product?.title ?? '',
        price: Number(item.price ?? 0),
        quantity: Number(item.quantity),
        image: item.product?.image_url?.[0] ?? null,
        in_stock: item.product?.quantity ?? 0,
      }));
    } catch (error) {
      console.error('Помилка завантаження кошика з сервера:', error);
    }
  }

  // Синхронізація guest localStorage -> сервер при логіні/реєстрації
  async function syncWithServer() {
    const authStore = useAuthStore();

    if (!authStore.isAuthenticated) {
      return;
    }

    try {
      const localItems = items.value.map(item => ({
        product_id: item.product_id,
        quantity: item.quantity,
      }));

      await apiClient.post('/cart/sync', {
        items: localItems,
      });

      // Після синхронізації беремо актуальний серверний стан
      await loadFromServer();

      // Після логіну guest localStorage більше не потрібен
      clearLocalCart();
    } catch (error) {
      console.error('Помилка синхронізації кошика:', error);
    }
  }

  // Додає товар до кошика або збільшує кількість
  async function addItem(product, quantity = 1) {
    const authStore = useAuthStore();

    if (authStore.isAuthenticated) {
      try {
        await apiClient.post('/cart', {
          product_id: product.id,
          quantity: quantity,
        });

        await loadFromServer();
      } catch (error) {
        console.error('Помилка додавання товару на сервер:', error);
        alert(error.response?.data?.message || 'Помилка додавання товару');
      }
    } else {
      const pid = product.id;
      const idx = findIndex(pid);

      if (idx !== -1) {
        items.value[idx].quantity += Number(quantity);
      } else {
        items.value.push({
          product_id: pid,
          title: product.title ?? '',
          price: Number(product.price ?? 0),
          quantity: Number(quantity),
          image: product.image ?? null,
          in_stock: Number(product.quantity ?? 0),
        });
      }

      persist();
    }
  }

  // Видалення товару з кошика
  async function removeItem(productId) {
    const authStore = useAuthStore();

    if (authStore.isAuthenticated) {
      try {
        const item = items.value.find(i => Number(i.product_id) === Number(productId));

        if (item && item.id) {
          await apiClient.delete(`/cart/${item.id}`);
          await loadFromServer();
        }
      } catch (error) {
        console.error('Помилка видалення товару на сервері:', error);
      }
    } else {
      items.value = items.value.filter(i => Number(i.product_id) !== Number(productId));
      persist();
    }
  }

  // Оновлення кількості товару
  async function updateQuantity(productId, quantity) {
    const authStore = useAuthStore();

    if (authStore.isAuthenticated) {
      try {
        const item = items.value.find(i => Number(i.product_id) === Number(productId));

        if (item && item.id) {
          if (quantity <= 0) {
            await removeItem(productId);
            return;
          }

          await apiClient.put(`/cart/${item.id}`, { quantity });
          await loadFromServer();
        }
      } catch (error) {
        console.error('Помилка оновлення кількості на сервері:', error);
        alert(error.response?.data?.message || 'Помилка оновлення кількості');
      }
    } else {
      const idx = findIndex(productId);

      if (idx !== -1) {
        const maxQuantity = items.value[idx].in_stock ?? Infinity;

        if (quantity > maxQuantity) {
          return;
        }

        items.value[idx].quantity = Number(quantity);

        if (items.value[idx].quantity <= 0) {
          items.value.splice(idx, 1);
        }

        persist();
      }
    }
  }

  // Полное очищение корзины
  function clear() {
    items.value = [];
    persist();
  }

  function getItemsBySeller(sellerId) {
    return items.value.filter(i => Number(i.seller_id) === Number(sellerId));
  }

  const totalItems = computed(() =>
    items.value.reduce((sum, item) => sum + Number(item.quantity), 0)
  );

  const totalPrice = computed(() =>
    items.value.reduce((sum, item) => sum + Number(item.price) * Number(item.quantity), 0)
  );

  return {
    items,
    addItem,
    removeItem,
    updateQuantity,
    clear,
    clearLocalCart,
    totalItems,
    totalPrice,
    persist,
    getItemsBySeller,
    loadFromServer,
    syncWithServer,
  };
});