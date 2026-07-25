import { ref } from 'vue';
import { useCartStore } from '@/stores/cartStore';
import { useFavoriteStore } from '@/stores/favoriteStore';

export function useProductActions() {
  const cartStore = useCartStore();
  const favoriteStore = useFavoriteStore();

  const favoriteLoading = ref(false);
  const cartLoadingById = ref({});

  function isInCart(productId) {
    return cartStore.items.some(item => Number(item.product_id) === Number(productId));
  }

  function isFavorite(productId) {
    return favoriteStore.isFavorite(productId);
  }

  async function toggleFavorite(productId) {
    favoriteLoading.value = true;
    try {
      const wasFavorite = favoriteStore.isFavorite(productId);
      await favoriteStore.toggleFavorite(productId);
      const nowFavorite = favoriteStore.isFavorite(productId);

      if (!wasFavorite && nowFavorite) {
        return { ok: true, text: 'Додано в обране', color: 'blue', icon: 'mdi-heart' };
      }
      if (wasFavorite && !nowFavorite) {
        return { ok: true, text: 'Видалено з обраного', color: 'grey', icon: 'mdi-heart-outline' };
      }
      return { ok: true, text: 'Стан обраного оновлено', color: 'info', icon: 'mdi-information' };
    } catch (error) {
      console.error('Помилка при роботі з обраним:', error);
      return { ok: false, text: 'Помилка. Спробуйте ще раз', color: 'error', icon: 'mdi-alert-circle' };
    } finally {
      favoriteLoading.value = false;
    }
  }

  async function addToCart(product, qty = 1, rawProduct = null) {
    const id = Number(product?.id);
    if (!id) {
      return { ok: false, text: 'Некоректний товар', color: 'error', icon: 'mdi-alert-circle' };
    }

    if (isInCart(id)) {
      return { ok: true, alreadyInCart: true, text: 'Товар вже у кошику', color: 'info', icon: 'mdi-information' };
    }

    cartLoadingById.value = { ...cartLoadingById.value, [id]: true };
    try {
      await cartStore.addItem(product, qty, rawProduct || product);
      return { ok: true, text: 'Товар додано в кошик!', color: 'success', icon: 'mdi-cart-check' };
    } catch (error) {
      console.error('Помилка додавання в кошик:', error);
      return { ok: false, text: 'Помилка додавання товару', color: 'error', icon: 'mdi-alert-circle' };
    } finally {
      cartLoadingById.value = { ...cartLoadingById.value, [id]: false };
    }
  }

  function isCartLoading(productId) {
    return Boolean(cartLoadingById.value[Number(productId)]);
  }

  return {
    favoriteLoading,
    toggleFavorite,
    isFavorite,
    isInCart,
    addToCart,
    isCartLoading,
  };
}