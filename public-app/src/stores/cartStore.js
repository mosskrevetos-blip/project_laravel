// public-app/src/stores/cartStore.js
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const STORAGE_KEY = 'public_cart_v1';

export const useCartStore = defineStore('cart', () => {
  
  const items = ref([]);


  // Функція для відновлення стану кошика зі сховища (localStorage) при перезавантаженні сторінки за ключем (STORAGE_KEY)
  function load() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) items.value = JSON.parse(raw);
    } catch (e) {
      items.value = [];
    }
  }


  // Зберігає поточний стан кошика в localStorage за ключем (STORAGE_KEY)
  function persist() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items.value));
    } catch (e) {
      // ignore
    }
  }

  // Відновлює стан кошика з localStorage при ініціалізації магазину
  load();


  // Знаходить індекс товару в кошику за його ідентифікатором
  function findIndex(productId) {
    return items.value.findIndex(i => Number(i.product_id) === Number(productId));
  }


  // Завантаження кошика з сервера (для авторизованих користувачів)
  async function loadFromServer() {
    const authStore = useAuthStore();
    
    // Якщо користувач не авторизований — нічого не робимо
    if (!authStore.isAuthenticated) {
      return;
    }

    try {
      // Запит до API для отримання кошика з сервера
      const response = await apiClient.get('/cart');
      
      // Перетворюємо дані з сервера у формат, який очікує фронтенд
      items.value = response.data.items.map(item => ({
        id: item.id, // ID запису в таблиці carts (потрібен для оновлення/видалення)
        product_id: item.product_id,
        title: item.product?.title ?? '',
        price: Number(item.price ?? 0),
        quantity: Number(item.quantity),
        image: item.product?.image_url?.[0] ?? null,
        in_stock: item.product?.quantity ?? 0, // Кількість на складі
      }));

      // Зберігаємо в localStorage (як кеш)
      persist();
    } catch (error) {
      console.error('Помилка завантаження кошика з сервера:', error);
    }
  }


  // Синхронізація кошика при авторизації
  // Переносить товари з localStorage на сервер
  async function syncWithServer() {
    const authStore = useAuthStore();
    
    // Якщо користувач не авторизований — нічого не робимо
    if (!authStore.isAuthenticated) {
      return;
    }

    try {
      // Відправляємо поточні товари з localStorage на сервер
      await apiClient.post('/cart/sync', {
        items: items.value.map(item => ({
          product_id: item.product_id,
          quantity: item.quantity,
        })),
      });

      // Після синхронізації завантажуємо оновлений кошик з сервера
      await loadFromServer();
    } catch (error) {
      console.error('Помилка синхронізації кошика:', error);
    }
  }


  // Додає товар до кошика або збільшує кількість, якщо товар вже є в кошику
  async function addItem(product, quantity = 1) {
    // Використовуємо authStore для перевірки авторизації
    const authStore = useAuthStore();

    // Якщо користувач авторизований — працюємо з сервером
    if (authStore.isAuthenticated) {
      try {
        await apiClient.post('/cart', {
          product_id: product.id,
          quantity: quantity,
        });

        // Після додавання завантажуємо оновлений кошик з сервера
        await loadFromServer();
      } catch (error) {
        console.error('Помилка додавання товару на сервер:', error);
        alert(error.response?.data?.message || 'Помилка додавання товару');
      }
    } else {
      // product: object { id, title, price, image? }
      const pid = product.id;
      const idx = findIndex(pid);
      if (idx !== -1) {
        items.value[idx].product_quantity += quantity;
      } else {
        items.value.push({
          product_id: pid,
          title: product.title ?? '',
          price: Number(product.price ?? 0),
          quantity: Number(quantity),
          image: product.image ?? null,
        });
        
      }
      persist();
    }
  }


  // Видаляє товар з кошика за його ідентифікатором
  async function removeItem(productId) {
    const authStore = useAuthStore();

    // Якщо користувач авторизований — працюємо з сервером
    if (authStore.isAuthenticated) {
      try {
        // Знаходимо товар у локальному масиві, щоб отримати його ID в таблиці carts
        const item = items.value.find(i => Number(i.product_id) === Number(productId));
        
        if (item && item.id) {
          // Видаляємо товар на сервері
          await apiClient.delete(`/cart/${item.id}`);
          
          // Після видалення завантажуємо оновлений кошик з сервера
          await loadFromServer();
        }
      } catch (error) {
        console.error('Помилка видалення товару на сервері:', error);
      }
    } else {
      // Якщо гість — працюємо з localStorage
      items.value = items.value.filter(i => Number(i.product_id) !== Number(productId));
      persist();
    }
  }


  // Оновлює кількість товару в кошику за його ідентифікатором
  async function updateQuantity(productId, quantity) {
    const authStore = useAuthStore();

    // Якщо користувач авторизований — працюємо з сервером
    if (authStore.isAuthenticated) {
      try {
        // Знаходимо товар у локальному масиві
        const item = items.value.find(i => Number(i.product_id) === Number(productId));
        
        if (item && item.id) {
          // Якщо кількість <= 0 — видаляємо товар
          if (quantity <= 0) {
            await removeItem(productId);
            return;
          }

          // Оновлюємо кількість на сервері
          await apiClient.put(`/cart/${item.id}`, { quantity });
          
          // Після оновлення завантажуємо оновлений кошик з сервера
          await loadFromServer();
        }
      } catch (error) {
        console.error('Помилка оновлення кількості на сервері:', error);
        alert(error.response?.data?.message || 'Помилка оновлення кількості');
      }
    } else {
      // Якщо гість — працюємо з localStorage
      const idx = findIndex(productId);

      if (idx !== -1) {
        // Перевіряємо, щоб кількість не перевищувала in_stock
        const maxQuantity = items.value[idx].in_stock;
        if (quantity > maxQuantity) {
          return; // Блокуємо оновлення
        }

        // Оновлюємо кількість
        items.value[idx].quantity = Number(quantity);
        
        // Якщо кількість <= 0 — видаляємо товар
        if (items.value[idx].quantity <= 0) {
          items.value.splice(idx, 1);
        }
        
        persist();
      }
    }
  }


  // Очищує кошик повністю
  function clear() {
    items.value = [];
    persist();
  }


  // Отримує всі товари від конкретного продавця за його ідентифікатором
  function getItemsBySeller(sellerId) {
    return items.value.filter(i => Number(i.seller_id) === Number(sellerId));
  }


  // Обчислювані властивості для загальної кількості товарів і загальної вартості кошика
  const totalItems = computed(() => items.value.reduce((s, it) => s + Number(it.quantity), 0));
  const totalPrice = computed(() => items.value.reduce((s, it) => s + Number(it.price) * Number(it.quantity), 0));
  
  
  return {
    items,
    addItem,
    removeItem,
    updateQuantity,
    clear,
    totalItems,
    totalPrice,
    persist,
    getItemsBySeller,
    loadFromServer,
    syncWithServer,
  };
});