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

              <FavoriteProductButton
                :product-id="product.id"
                :icon-only="true"
                :overlay="false"
                size="small"
                variant="flat"
              />
            </div>

            <div class="mb-3">
              <v-chip v-if="product.quantity > 0" color="green" text-color="white">В наявності: {{ product.quantity }}</v-chip>
              <v-chip v-else color="grey">Немає в наявності</v-chip>
            </div>

            <RatingDisplay
              :rating="globalAverageRating"
              :count="globalReviewsCount"
              :review-link="`/product/${product.id}-${product.slug}#comments-create-review`"
              :size="25"
              :half-increments="false"
              text-class="product-rating-text"
            />

            <div class="mb-4">
              <span class="text-h4 font-weight-bold primary--text">
                {{ product.price }} {{ product.currency ? product.currency : '$' }}
              </span>
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
              <AddToCartButton
                :product="product"
                mode="full"
                label="Додати в кошик"
                :show-snackbar="false"
                @added="showSnack"
                @error="showSnack"
              />
              <v-btn color="secondary" @click="goToCheckout">Оформити</v-btn>
              <v-btn variant="outlined" @click="openChatWithSeller">Чат з продавцем</v-btn>
            </div>

            <div class="seller-grid mt-6">
              <div class="seller-left">
                <div class="text-subtitle-2 seller-label">Продавець</div>
                <div class="text-body-1 font-weight-medium seller-name seller-link" @click="goToSellerPage">
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

    <v-snackbar v-model="snackbar" :timeout="2000" :color="snackbarColor" location="top right" elevation="6">
      <div class="d-flex align-center">
        <v-icon :icon="snackbarIcon" class="mr-2" />
        <span>{{ snackbarText }}</span>
      </div>
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProductStore } from '@/stores/productStore';
import { useProductCommentsStore } from '@/stores/productCommentsStore';
import ProductImageGallery from '@/components/product/ProductImageGallery.vue';
import FavoriteSellerButton from '@/components/product/FavoriteSellerButton.vue';
import FavoriteProductButton from '@/components/product/FavoriteProductButton.vue';
import AddToCartButton from '@/components/product/AddToCartButton.vue';
import ProductCommentsSection from '@/components/product/ProductCommentsSection.vue';
import { useMessageStore } from '@/stores/messageStore';
import { useAuthStore } from '@/stores/authStore';
import RatingDisplay from '@/components/common/RatingDisplay.vue';

const route = useRoute();
const router = useRouter();
const productStore = useProductStore();
const commentsStore = useProductCommentsStore();
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
  if (Array.isArray(props)) return props.map(p => ({ name: p.name || p.key || '', value: p.value ?? '' }));
  return Object.keys(props).map(k => ({ name: String(k), value: String(props[k]) }));
});

const sellerName = computed(() => {
  if (!product.value) return '';
  if (product.value.user && product.value.user.name) return product.value.user.name;
  return `Продавець #${product.value.user_id}`;
});

const globalAverageRating = computed(() => commentsStore.averageRating || 0);
const globalReviewsCount = computed(() => commentsStore.reviewsCount || 0);

const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const snackbarIcon = ref('mdi-check-circle');

function showSnack(payload) {
  snackbarText.value = payload.text;
  snackbarColor.value = payload.color;
  snackbarIcon.value = payload.icon;
  snackbar.value = true;
}

async function loadProduct(id) {
  try {
    await productStore.fetchProduct(id);
    initialImageIndex.value = 0;

    commentsStore.setProduct(id);
    commentsStore.setType('review');
    await commentsStore.fetchList({ page: 1, per_page: 10, sort: 'date_desc' });
  } catch (err) {
    console.error('Failed to load product:', err);
  }
}

onMounted(() => { if (productId.value) loadProduct(productId.value); });

async function openChatWithSeller() {
  if (!product.value) return;

  const sellerId = product.value.user_id;
  const pid = product.value.id;

  if (!authStore.isAuthenticated) {
    authStore.setPostLoginAction(async () => {
      await messageStore.openWithSeller(sellerId, { productId: pid });
    });
    authStore.openLoginDialog();
    return;
  }

  await messageStore.openWithSeller(sellerId, { productId: pid });
}

function goToCheckout() {
  router.push({ name: 'checkout' });
}

function goToSellerPage() {
  if (!product.value?.user_id) return;
  router.push({ name: 'seller.show', params: { id: Number(product.value.user_id) } });
}
</script>

<style scoped>
.title-grid { display:grid; grid-template-columns:1fr auto; align-items:center; column-gap:10px; }
.title-text { min-width:0; }
.favorite-btn { background-color: rgba(255,255,255,.92)!important; box-shadow:0 2px 6px rgba(0,0,0,.12); }
.favorite-btn.favorite-active { background-color:#2196F3!important; }
.seller-grid { display:grid; grid-template-columns:1fr auto; align-items:center; column-gap:12px; padding-top:8px; border-top:1px solid rgba(0,0,0,.08); }
.seller-name { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.product-rating-text { color: rgba(0,0,0,.72); font-size:15px; font-weight:500; }
.seller-link { cursor:pointer; text-decoration:underline; text-decoration-style:dotted; }
</style>