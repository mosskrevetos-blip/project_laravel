import { defineStore } from 'pinia';

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [], // [{product: {...}, quantity: number}, ...]
  }),

  getters: {
    // Общее количество товаров в корзине
    totalItems: (state) => {
      return state.items.reduce((sum, item) => sum + item.quantity, 0);
    },

    // Общая сумма корзины
    totalPrice: (state) => {
      return state.items.reduce((sum, item) => {
        return sum + (parseFloat(item.product.price) * item.quantity);
      }, 0);
    },

    // Группировка товаров по продавцам
    itemsBySeller: (state) => {
      const grouped = {};
      state.items.forEach(item => {
        const sellerId = item.product.user_id;
        if (!grouped[sellerId]) {
          grouped[sellerId] = [];
        }
        grouped[sellerId].push(item);
      });
      return grouped;
    },

    // Количество уникальных продавцов в корзине
    sellerCount: (state) => {
      const sellerIds = new Set(state.items.map(item => item.product.user_id));
      return sellerIds.size;
    },

    // Проверка на товары от разных продавцов
    hasMixedSellers() {
      return this.sellerCount > 1;
    },

    // Проверка наличия товара в корзине
    hasProduct: (state) => (productId) => {
      return state.items.some(item => item.product.id === productId);
    },

    // Получение количества конкретного товара
    getProductQuantity: (state) => (productId) => {
      const item = state.items.find(item => item.product.id === productId);
      return item ? item.quantity : 0;
    },
  },

  actions: {
    // Добавление товара в корзину
    addToCart(product, quantity = 1) {
      // Проверяем, есть ли товар уже в корзине
      const existingItem = this.items.find(item => item.product.id === product.id);

      if (existingItem) {
        // Если товар уже есть, увеличиваем количество
        const newQuantity = existingItem.quantity + quantity;
        // Проверяем, не превышает ли новое количество доступное на складе
        if (newQuantity <= product.quantity) {
          existingItem.quantity = newQuantity;
        } else {
          // Устанавливаем максимально доступное количество
          existingItem.quantity = product.quantity;
          console.warn(`Товара "${product.title}" нет в наличии в таком количестве. Установлено максимальное доступное количество: ${product.quantity}`);
        }
      } else {
        // Если товара нет в корзине, добавляем его
        // Проверяем доступное количество
        const addQuantity = quantity <= product.quantity ? quantity : product.quantity;
        this.items.push({
          product: { ...product }, // Создаём копию объекта товара
          quantity: addQuantity,
        });
      }

      // Сохраняем корзину в localStorage
      this.saveCart();
    },

    // Удаление товара из корзины
    removeFromCart(productId) {
      const index = this.items.findIndex(item => item.product.id === productId);
      if (index !== -1) {
        this.items.splice(index, 1);
        this.saveCart();
      }
    },

    // Обновление количества товара
    updateQuantity(productId, quantity) {
      const item = this.items.find(item => item.product.id === productId);
      if (item) {
        // Проверяем, что количество в допустимых пределах
        if (quantity <= 0) {
          // Если количество 0 или меньше, удаляем товар
          this.removeFromCart(productId);
        } else if (quantity <= item.product.quantity) {
          // Если количество не превышает доступное, обновляем
          item.quantity = quantity;
          this.saveCart();
        } else {
          // Устанавливаем максимально доступное количество
          item.quantity = item.product.quantity;
          this.saveCart();
          console.warn(`Товара "${item.product.title}" нет в наличии в таком количестве. Установлено максимальное доступное количество: ${item.product.quantity}`);
        }
      }
    },

    // Очистка корзины
    clearCart() {
      this.items = [];
      this.saveCart();
    },

    // Сохранение корзины в localStorage
    saveCart() {
      try {
        localStorage.setItem('cart', JSON.stringify(this.items));
      } catch (error) {
        console.error('Ошибка при сохранении корзины:', error);
      }
    },

    // Загрузка корзины из localStorage
    loadCart() {
      try {
        const savedCart = localStorage.getItem('cart');
        if (savedCart) {
          this.items = JSON.parse(savedCart);
        }
      } catch (error) {
        console.error('Ошибка при загрузке корзины:', error);
        this.items = [];
      }
    },

    // Валидация корзины перед оформлением заказа
    validateCart() {
      const errors = [];

      // Проверка: корзина не пуста
      if (this.items.length === 0) {
        errors.push('Корзина пуста');
      }

      // Проверка: все товары от одного продавца
      if (this.hasMixedSellers) {
        errors.push('Все товары в корзине должны быть от одного продавца');
      }

      // Проверка: достаточно товара на складе
      this.items.forEach(item => {
        if (item.quantity > item.product.quantity) {
          errors.push(`Товара "${item.product.title}" недостаточно на складе (доступно: ${item.product.quantity})`);
        }
      });

      return {
        isValid: errors.length === 0,
        errors,
      };
    },

    // Получение данных для API заказа
    getCartForOrder() {
      return this.items.map(item => ({
        product_id: item.product.id,
        quantity: item.quantity,
      }));
    },

    // Получение ID продавца (для API)
    getSellerId() {
      if (this.items.length === 0) return null;
      return this.items[0].product.user_id;
    },
  },
});
