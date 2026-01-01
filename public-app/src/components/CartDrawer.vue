<template>
  <div>
    <!-- Кнопка корзины с badge -->
    <v-btn icon @click="isCartDrawerOpen = true">
      <v-badge
        :content="cartItemsCount"
        :model-value="cartItemsCount > 0"
        color="error"
        overlap
      >
        <v-icon>mdi-cart-outline</v-icon>
      </v-badge>
    </v-btn>

    <!-- Боковая панель корзины -->
    <v-navigation-drawer
      v-model="isCartDrawerOpen"
      location="right"
      temporary
      width="400"
    >
      <v-card flat tile class="d-flex flex-column" style="height: 100%">
        <!-- Заголовок -->
        <v-card-title class="d-flex align-center justify-space-between border-b">
          <span class="text-h6">Корзина</span>
          <v-btn icon variant="text" @click="isCartDrawerOpen = false">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </v-card-title>

        <!-- Содержимое корзины -->
        <v-card-text class="flex-grow-1 overflow-y-auto pa-0">
          <!-- Пустая корзина -->
          <div
            v-if="cartStore.items.length === 0"
            class="d-flex flex-column align-center justify-center pa-8"
            style="min-height: 300px"
          >
            <v-icon size="80" color="grey-lighten-1">mdi-cart-outline</v-icon>
            <p class="text-h6 text-grey mt-4">Корзина пуста</p>
            <p class="text-body-2 text-grey">Добавьте товары для оформления заказа</p>
          </div>

          <!-- Список товаров -->
          <v-list v-else lines="two">
            <template v-for="(item, index) in cartStore.items" :key="item.product.id">
              <v-list-item class="px-4 py-2">
                <template v-slot:prepend>
                  <v-avatar size="60" rounded="lg">
                    <v-img
                      v-if="item.product.image_url && item.product.image_url.length > 0"
                      :src="getImageUrl(item.product.image_url[0])"
                      cover
                    />
                    <v-icon v-else size="40">mdi-image-outline</v-icon>
                  </v-avatar>
                </template>

                <v-list-item-title class="font-weight-medium mb-1">
                  {{ item.product.title }}
                </v-list-item-title>
                <v-list-item-subtitle class="d-flex align-center">
                  <span class="text-h6 font-weight-bold">{{ formatPrice(item.product.price) }} ₴</span>
                </v-list-item-subtitle>

                <template v-slot:append>
                  <div class="d-flex flex-column align-center">
                    <!-- Счетчик количества -->
                    <div class="d-flex align-center mb-2">
                      <v-btn
                        icon
                        size="x-small"
                        variant="text"
                        @click="decrementQuantity(item.product.id)"
                      >
                        <v-icon>mdi-minus</v-icon>
                      </v-btn>
                      <span class="mx-2 text-body-1">{{ item.quantity }}</span>
                      <v-btn
                        icon
                        size="x-small"
                        variant="text"
                        @click="incrementQuantity(item.product.id)"
                        :disabled="item.quantity >= item.product.quantity"
                      >
                        <v-icon>mdi-plus</v-icon>
                      </v-btn>
                    </div>
                    <!-- Кнопка удаления -->
                    <v-btn
                      icon
                      size="small"
                      variant="text"
                      color="error"
                      @click="removeItem(item.product.id)"
                    >
                      <v-icon size="small">mdi-delete-outline</v-icon>
                    </v-btn>
                  </div>
                </template>
              </v-list-item>

              <v-divider v-if="index < cartStore.items.length - 1" />
            </template>
          </v-list>
        </v-card-text>

        <!-- Футер с итогом и кнопками -->
        <v-card-actions
          v-if="cartStore.items.length > 0"
          class="flex-column align-stretch pa-4 border-t"
        >
          <!-- Общая сумма -->
          <div class="d-flex justify-space-between align-center mb-4">
            <span class="text-h6">Итого:</span>
            <span class="text-h5 font-weight-bold">{{ formatPrice(cartStore.totalPrice) }} ₴</span>
          </div>

          <!-- Предупреждение о разных продавцах -->
          <v-alert
            v-if="hasMixedSellers"
            type="warning"
            density="compact"
            class="mb-3"
            text="Товары от разных продавцов. Заказ можно оформить только для одного продавца."
          />

          <!-- Кнопки действий -->
          <v-btn
            color="primary"
            size="large"
            block
            @click="goToCheckout"
            :disabled="hasMixedSellers"
          >
            Оформить заказ
          </v-btn>
          <v-btn
            variant="outlined"
            size="large"
            block
            class="mt-2"
            @click="goToCart"
          >
            Перейти в корзину
          </v-btn>
          <v-btn
            variant="text"
            size="small"
            block
            class="mt-2"
            color="error"
            @click="clearCart"
          >
            Очистить корзину
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-navigation-drawer>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '@/stores/cartStore';

const router = useRouter();
const cartStore = useCartStore();
const isCartDrawerOpen = ref(false);

// Вычисляемое свойство для количества товаров
const cartItemsCount = computed(() => cartStore.totalItems);

// Проверка на товары от разных продавцов
const hasMixedSellers = computed(() => {
  if (cartStore.items.length <= 1) return false;
  const sellerIds = new Set(cartStore.items.map(item => item.product.user_id));
  return sellerIds.size > 1;
});

// Форматирование цены
function formatPrice(price) {
  return parseFloat(price).toFixed(2);
}

// Получение URL изображения
function getImageUrl(imagePath) {
  if (!imagePath) return '';
  // Если путь уже полный URL, возвращаем как есть
  if (imagePath.startsWith('http')) return imagePath;
  // Иначе добавляем базовый URL API
  const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000';
  return `${apiUrl}${imagePath}`;
}

// Увеличение количества товара
function incrementQuantity(productId) {
  const item = cartStore.items.find(i => i.product.id === productId);
  if (item && item.quantity < item.product.quantity) {
    cartStore.updateQuantity(productId, item.quantity + 1);
  }
}

// Уменьшение количества товара
function decrementQuantity(productId) {
  const item = cartStore.items.find(i => i.product.id === productId);
  if (item && item.quantity > 1) {
    cartStore.updateQuantity(productId, item.quantity - 1);
  } else if (item && item.quantity === 1) {
    // Если количество 1, то удаляем товар
    removeItem(productId);
  }
}

// Удаление товара из корзины
function removeItem(productId) {
  cartStore.removeFromCart(productId);
}

// Очистка корзины
function clearCart() {
  if (confirm('Вы уверены, что хотите очистить корзину?')) {
    cartStore.clearCart();
  }
}

// Переход на страницу корзины
function goToCart() {
  isCartDrawerOpen.value = false;
  router.push({ name: 'cart' });
}

// Переход на страницу оформления заказа
function goToCheckout() {
  isCartDrawerOpen.value = false;
  router.push({ name: 'checkout' });
}
</script>

<style scoped>
.border-b {
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
}

.border-t {
  border-top: 1px solid rgba(0, 0, 0, 0.12);
}
</style>
