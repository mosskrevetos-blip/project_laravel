<template>
  <div>
    <v-container>
      <v-row>
        <v-col cols="12" md="6">

          <ProductImageGallery
            v-if="product"
            :product="product"
            :initial-index="initialImageIndex"
          />

          <v-skeleton-loader v-else type="article" />
        </v-col>

        <v-col cols="12" md="6">
          <div v-if="product">
            <div class="title-grid mb-2">
              <h1 class="text-h5 mb-0 title-text">{{ product.title }}</h1>

              <v-btn
                icon
                variant="flat"
                size="small"
                class="favorite-btn"
                :class="{ 'favorite-active': isFavorite }"
                @click.prevent="toggleFavorite"
                :loading="favoriteLoading"
                :aria-label="isFavorite ? 'Видалити з обраного' : 'Додати в обране'"
              >
                <v-icon class="favorite-icon">
                  {{ isFavorite ? 'mdi-heart' : 'mdi-heart-outline' }}
                </v-icon>
              </v-btn>
            </div>

            <div class="mb-3">
              <v-chip v-if="product.quantity > 0" color="green" text-color="white">В наявності: {{ product.quantity }}</v-chip>
              <v-chip v-else color="grey">Немає в наявності</v-chip>
            </div>

            <div class="mb-4">
              <span class="text-h4 font-weight-bold primary--text">{{ product.price }} {{ product.currency ? product.currency : '$' }}</span>
            </div>

            <div class="mb-4">
              <h3 class="text-subtitle-1">Опис</h3>
              <div v-html="product.description || '<i>Опис відсутній</i>'"></div>
            </div>

            <div v-if="attributes && attributes.length" class="mb-4">
              <h3 class="text-subtitle-1">Характеристики</h3>
              <v-list dense>
                <v-list-item v-for="(attr, idx) in attributes" :key="idx">
                  <v-list-item-content>
                    <v-list-item-title class="font-weight-bold">{{ attr.name }}</v-list-item-title>
                    <v-list-item-subtitle>{{ attr.value }}</v-list-item-subtitle>
                  </v-list-item-content>
                </v-list-item>
              </v-list>
            </div>

            <div class="mt-6 d-flex gap-4">
              <v-btn color="primary" @click="addToCart">Додати в кошик</v-btn>
              <v-btn color="secondary" @click="goToCheckout">Оформити</v-btn>
              <v-btn variant="outlined" @click="openChatWithSeller">Чат з продавцем</v-btn>
            </div>

            <!-- Продавець: ім'я | кнопка "в обране" (2 колонки) -->
            <div class="seller-grid mt-6">
              <div class="seller-left">
                <div class="text-subtitle-2 seller-label">Продавець</div>
                <div class="text-body-1 font-weight-medium seller-name">
                  {{ sellerName }}
                </div>
              </div>

              <div class="seller-right">
                <FavoriteSellerButton :seller-id="product.user_id" />
              </div>
            </div>
          </div>

          <div v-else>
            <v-skeleton-loader type="paragraph" />
          </div>
        </v-col>
      </v-row>
    </v-container>

    <v-container>
      <v-row>
        <v-col cols="12">
          <ProductCommentsSection
            v-if="product"
            :product-id="product.id"
            :product-owner-id="product.user_id"
          />
        </v-col>
      </v-row>
    </v-container>

    <!-- ✅ snackbar як у карточці товару -->
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProductStore } from '@/stores/productStore';
import { useCartStore } from '@/stores/cartStore';
import { useFavoriteStore } from '@/stores/favoriteStore';
import ProductImageGallery from '@/components/product/ProductImageGallery.vue';
import FavoriteSellerButton from '@/components/product/FavoriteSellerButton.vue';
import ProductCommentsSection from '@/components/product/ProductCommentsSection.vue';
import { useMessageStore } from '@/stores/messageStore';
import { useAuthStore } from '@/stores/authStore';


const route = useRoute();
const router = useRouter();
const productStore = useProductStore();
const cart = useCartStore();
const favoriteStore = useFavoriteStore();
const messageStore = useMessageStore();
const authStore = useAuthStore();

const productId = ref(route.params.id || null);

watch(() => route.params.id, (v) => {
  productId.value = v;
  if (productId.value) loadProduct(productId.value);
});

const product = computed(() => productStore.currentProduct);
const initialImageIndex = ref(0);

const attributes = computed(() => {
  if (!product.value) return [];
  const props = product.value.properties || {};
  if (Array.isArray(props)) {
    return props.map(p => ({ name: p.name || p.key || '', value: p.value ?? '' }));
  }
  return Object.keys(props).map(k => ({ name: String(k), value: String(props[k]) }));
});

// Витягуємо ім'я продавця
const sellerName = computed(() => {
  if (!product.value) return '';
  if (product.value.user && product.value.user.name) return product.value.user.name;
  return `Продавець #${product.value.user_id}`;
});

// Завантаження товару
async function loadProduct(id) {
  try {
    await productStore.fetchProduct(id);
    initialImageIndex.value = 0;
  } catch (err) {
    console.error('Failed to load product:', err);
  }
}

onMounted(() => {
  if (productId.value) loadProduct(productId.value);
});

// Обране: стан і перемикач
const isFavorite = computed(() => {
  if (!product.value) return false;
  return favoriteStore.isFavorite(product.value.id);
});

const favoriteLoading = ref(false);

// snackbar state
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const snackbarIcon = ref('mdi-check-circle');

async function toggleFavorite() {
  if (!product.value) return;

  favoriteLoading.value = true;
  try {
    await favoriteStore.toggleFavorite(product.value.id);

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
  } finally {
    favoriteLoading.value = false;
  }
}

// Додати товар у кошик
function addToCart() {
  if (!product.value) return;
  cart.addItem({
    id: product.value.id,
    title: product.value.title,
    price: product.value.price,
    image: (Array.isArray(product.value.image_url) ? product.value.image_url[0] : product.value.image_url) || null,
  }, 1, product);
  // Ніяких alert / модальних вікон — бейдж у шапці оновиться автоматично
}

// Відкрити чат з продавцем
async function openChatWithSeller() {
  if (!product.value) return;

  const sellerId = product.value.user_id;
  const productId = product.value.id;

  if (!authStore.isAuthenticated) {
    authStore.setPostLoginAction(async () => {
      await messageStore.openWithSeller(sellerId, { productId });
    });
    authStore.openLoginDialog();
    return;
  }

  await messageStore.openWithSeller(sellerId, { productId });
}

function goToCheckout() {
  router.push({ name: 'checkout' });
}
</script>

<style scoped>
  .title-grid {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    column-gap: 10px;
  }

  .title-text {
    min-width: 0;
  }


  .favorite-btn {
    background-color: rgba(255, 255, 255, 0.92) !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
  }


  .favorite-btn.favorite-active {
    background-color: #2196F3 !important;
  }


  .favorite-icon {
    color: #616161; 
  }

  .favorite-btn.favorite-active .favorite-icon {
    color: #ffffff;
  }

  .seller-grid {
    display: grid;
    grid-template-columns: 1fr auto; /* ✅ две колонки */
    align-items: center;
    column-gap: 12px;
    padding-top: 8px;
    border-top: 1px solid rgba(0,0,0,0.08);
  }

  .seller-left {
    min-width: 0;
  }

  .seller-label {
    opacity: 0.7;
    line-height: 1.2;
  }

  .seller-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .seller-right {
    display: flex;
    justify-content: flex-end;
  }

</style>