import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

const STORAGE_KEY = 'public_cart_v1';

export const useCartStore = defineStore('cart', () => {
  const items = ref([]);

  // Load from localStorage
  function load() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) items.value = JSON.parse(raw);
    } catch (e) {
      items.value = [];
    }
  }

  function persist() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items.value));
    } catch (e) {
      // ignore
    }
  }

  load();

  function findIndex(productId) {
    return items.value.findIndex(i => Number(i.product_id) === Number(productId));
  }

  function addItem(product, quantity = 1) {
    // product: object { id, title, price, image? }
    const pid = product.id ?? product.product_id ?? product.productId;
    const idx = findIndex(pid);
    if (idx !== -1) {
      items.value[idx].quantity += quantity;
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

  function removeItem(productId) {
    items.value = items.value.filter(i => Number(i.product_id) !== Number(productId));
    persist();
  }

  function updateQuantity(productId, quantity) {
    const idx = findIndex(productId);
    if (idx !== -1) {
      items.value[idx].quantity = Number(quantity);
      if (items.value[idx].quantity <= 0) {
        items.value.splice(idx, 1);
      }
      persist();
    }
  }

  function clear() {
    items.value = [];
    persist();
  }

  function getItemsBySeller(sellerId) {
    return items.value.filter(i => Number(i.seller_id) === Number(sellerId));
  }

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
  };
});