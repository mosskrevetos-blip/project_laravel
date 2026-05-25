<template>
  <v-card 
    class="product-card ma-4" 
    width="250" 
    elevation="2"
    @mouseenter="hover = true"
    @mouseleave="hover = false"
  >
    <!-- Контейнер зображення улюбленого -->
    <div class="image-container">
      <!-- Іконка улюбленого (сердечко) -->
      <v-btn
        icon
        size="small"
        class="favorite-btn"
        :class="{ 'favorite-active': isFavorite }"
        @click.prevent="toggleFavorite"
      >
        <v-icon :color="isFavorite ? 'white' : 'grey-darken-2'">
          {{ isFavorite ? 'mdi-heart' : 'mdi-heart-outline' }}
        </v-icon>
      </v-btn>

      <!-- Посилання на сторінку товару -->
      <router-link 
      :to="productUrl"
        class="no-decoration"
      >
        <!-- Зображення товару -->
        <div class="product-image">
          <ProductResponsiveImagePublic
            v-if="hasImage"
            :product-id="product.id"
            :filename="firstImageName"
            :alt-text="product.title"
            :sizes-List="[150, 400, 800]"
            :sizes-attr="'(max-width: 480px) 150px, (max-width: 800px) 400px, 800px'"
            :style-object="{ height: '220px', width: '100%' }"
          />
          <img 
            v-else 
            src="https://cdn.vuetifyjs.com/images/cards/docks.jpg" 
            class="product-image-fallback"
            alt="Placeholder"
          />
        </div>
      </router-link>
    </div>

    <!-- Інформація про товар -->
    <router-link
      :to="productUrl"
      class="no-decoration"
    >
      <v-card-text class="pb-2">
        <!-- Ціна та кнопка корзини в одному рядку -->
        <div class="price-cart-container mb-3">
          <span class="price-text">{{ product.price }} грн</span>
          
          <!-- Кнопка додавання в кошик -->
          <div class="cart-btn-wrapper">
            <v-btn
              icon
              size="small"
              color="red"
              :disabled="isInCart"
              :loading="addingToCart"
              @click.prevent="handleAddToCart"
              class="cart-btn"
            >
              <v-icon>mdi-cart-plus</v-icon>
            </v-btn>
            
            <!-- Зелена галочка якщо товар у кошику -->
            <v-icon 
              v-if="isInCart" 
              class="check-icon"
              color="success"
            >
              mdi-check-circle
            </v-icon>
          </div>
        </div>

        <!-- Назва товару (обмеження в 2 рядки) -->
        <div class="product-title mb-2">
          {{ product.title }}
        </div>

        <!-- Рейтинг та кількість відгуків -->
        <div class="rating-container">
          <v-rating
            :model-value="productRating"
            density="compact"
            size="small"
            color="amber"
            active-color="amber"
            readonly
            half-increments
          ></v-rating>
          <span class="reviews-count">{{ reviewsCount }}</span>
        </div>
      </v-card-text>
    </router-link>

    <!-- Вспливаюче повідомлення -->
    <v-snackbar
        v-model="snackbar"
        :timeout="2000"
        :color="snackbarColor"
        location="top right"
        elevation="6"
        >
        <div class="d-flex align-center">
            <v-icon :icon="snackbarIcon" class="mr-2"></v-icon>
            <span>{{ snackbarText }}</span>
        </div>
    </v-snackbar>

  </v-card>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/stores/cartStore';
import { useFavoriteStore } from '@/stores/favoriteStore';
import ProductResponsiveImagePublic from '@/components/product/ProductResponsiveImagePublic.vue';

const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
});

const cartStore = useCartStore();
const favoriteStore = useFavoriteStore();


// створюємо URL для сторінки товару з урахуванням slug
const productUrl = computed(() => {
  return `/product/${props.product.id}-${props.product.slug}`;
});


// ========================================
// Стан компоненту
// ========================================
const hover = ref(false);
const isFavorite = computed(() => favoriteStore.isFavorite(props.product.id));
const addingToCart = ref(false);

// Стан для повідомлення (snackbar)
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const snackbarIcon = ref('mdi-check-circle');

// ========================================
// Перевірка: товар у кошику?
// ========================================

const isInCart = computed(() => {
  return cartStore.items.some(item => Number(item.product_id) === Number(props.product.id));
});

// ========================================
// Робота з зображенням
// ========================================

const firstImageName = computed(() => {
  const images = props.product.image_url || [];
  if (Array.isArray(images) && images.length) return images[0];
  if (typeof images === 'string' && images) {
    try {
      const parsed = JSON.parse(images);
      if (Array.isArray(parsed) && parsed.length) return parsed[0];
    } catch (e) {
      return images;
    }
  }
  return null;
});

const hasImage = computed(() => !!firstImageName.value);

// ========================================
// Рейтинг та відгуки (заглушка)
// ========================================

// Поки використовуємо рейтинг з product.rating або генеруємо випадковий
const productRating = computed(() => {
  // Якщо в БД є колонка rating — використовуємо її (припустимо, вона зберігається як число від 0 до 1000)      
  if (props.product.rating) {
    return Number(props.product.rating) / 200; // Перетворюємо з 1000 в 5.0
  }
  // Інакше генеруємо випадковий рейтинг від 3.5 до 5.0
  return 4.0 + Math.random();
});

// Випадкова кількість відгуків (заглушка)
const reviewsCount = computed(() => {
  return Math.floor(Math.random() * 100) + 1;
});

// ========================================
// Обране (заглушка)
// ========================================

async function toggleFavorite() {
  try {
    await favoriteStore.toggleFavorite(props.product.id);
    
    // Показуємо повідомлення
    if (isFavorite.value) {
        snackbarText.value = 'Додано в обране';
        snackbarColor.value = 'blue';
        snackbarIcon.value = 'mdi-heart';
    } else {
        snackbarText.value = 'Видалено з обраного';
        snackbarColor.value = 'grey';
        snackbarIcon.value = 'mdi-heart-outline';
    }
      snackbar.value = true;
  } catch (error) {
      console.error('Помилка при роботі з обраним:', error);
      snackbarText.value = 'Помилка. Спробуйте ще раз';
      snackbarColor.value = 'error';
      snackbarIcon.value = 'mdi-alert-circle';
      snackbar.value = true;
  }
}

// ========================================
// Додавання в кошик
// ========================================

async function handleAddToCart() {
  // Якщо товар вже в кошику — нічого не робимо
  if (isInCart.value) return;

  addingToCart.value = true;
  try {
    await cartStore.addItem(props.product, 1);
    
    // Показуємо успішне повідомлення з іконкою
    snackbarText.value = 'Товар додано в кошик!';
    snackbarColor.value = 'success';
    snackbarIcon.value = 'mdi-cart-check'; // Іконка кошика з галочкою
    snackbar.value = true;

    console.log('Товар додано в кошик:', props.product.title);
  } catch (error) {
    console.error('Помилка додавання в кошик:', error);
    
    // Показуємо повідомлення про помилку з іконкою
    snackbarText.value = 'Помилка додавання товару';
    snackbarColor.value = 'error';
    snackbarIcon.value = 'mdi-alert-circle'; // Іконка помилки
    snackbar.value = true;
  } finally {
    addingToCart.value = false;
  }
}
</script>

<style scoped>
.product-card {
  position: relative;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  cursor: pointer;
}

.product-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15) !important;
}

/* ========================================
   Контейнер зображення та кнопка улюбленого
   ======================================== */

.image-container {
  position: relative;
  width: 100%;
}

.product-image {
  width: 100%;
  height: 220px;
  overflow: hidden;
  background-color: #f5f5f5;
}

.product-image-fallback {
  width: 100%;
  height: 220px;
  object-fit: cover;
}

/* ========================================
   Кнопка улюбленого (сердечко)
   ======================================== */

.favorite-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 10;
  background-color: rgba(255, 255, 255, 0.9) !important;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: background-color 0.2s ease;
}

/* Блакитний колір при активному улюбленому */
.favorite-btn.favorite-active {
  background-color: #2196F3 !important; /* Блакитний колір */
}

.favorite-btn:hover {
  background-color: rgba(255, 255, 255, 1) !important;
}

.favorite-btn.favorite-active:hover {
  background-color: #1976D2 !important; /* Темніше при hover */
}

/* ========================================
   Ціна та кнопка кошика        
   ======================================== */

.price-cart-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.price-text {
  font-size: 18px;
  font-weight: 700;
  color: #e53935; /* Червоний колір */
}

/* Обгортка для кнопки кошика та галочки */
.cart-btn-wrapper {
  position: relative;
  display: inline-block;
}

.cart-btn {
  transition: opacity 0.2s ease, filter 0.2s ease;
}

/* Заблюренная кнопка якщо товар в кошику */
.cart-btn:disabled {
  opacity: 0.5;
  filter: blur(1px);
}

/* Зелена галочка поверх кнопки */
.check-icon {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 10;
  font-size: 24px !important;
  pointer-events: none; /* Не блокує кліки */
}

/* ========================================
   Назва товару (обмеження в 2 рядки)   
   ======================================== */

.product-title {
  font-size: 15px;
  line-height: 1.4;
  font-weight: 500;
  /* Адаптація під тему */
  color: rgb(var(--v-theme-on-surface));
  /* Обмеження в 2 рядки з багатокрапкою */
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  min-height: 42px; /* 2 рядки */
}

/* ========================================
   Рейтинг та кількість відгуків
   ======================================== */

.rating-container {
  display: flex;
  align-items: center;
  gap: 4px;
}

.reviews-count {
  font-size: 12px;
  color: #757575;
  margin-left: 4px;
}

/* ========================================
   Прибираємо підкреслення посилань
   ======================================== */

.no-decoration {
  text-decoration: none;
  color: inherit;
  display: block;
}
</style>