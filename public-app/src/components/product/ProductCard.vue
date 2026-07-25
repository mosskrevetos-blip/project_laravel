<template>
  <v-card
    class="product-card ma-4"
    width="250"
    elevation="2"
    @mouseenter="hover = true"
    @mouseleave="hover = false"
  >
    <div class="image-container">
      <FavoriteProductButton
        :product-id="product.id"
        :icon-only="true"
        :overlay="true"
        size="small"
        variant="flat"
        button-class="favorite-btn"
      />

      <router-link :to="productUrl" class="no-decoration">
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

    <router-link :to="productUrl" class="no-decoration">
      <v-card-text class="pb-2">
        <div class="price-cart-container mb-3">
          <span class="price-text">{{ product.price }} грн</span>

          <AddToCartButton
            :product="product"
            mode="icon"
            :show-snackbar="false"
            @added="showSnack"
            @error="showSnack"
          />
        </div>

        <div class="product-title mb-2">{{ product.title }}</div>

        <RatingDisplay
          :rating="productRating"
          :count="reviewsCount"
          :review-link="`/product/${product.id}-${product.slug}#comments-create-review`"
          :size="14"
          :half-increments="true"
          text-class="reviews-count"
        />
      </v-card-text>
    </router-link>

    <v-snackbar
      v-model="snackbar"
      :timeout="2000"
      :color="snackbarColor"
      location="top right"
      elevation="6"
    >
      <div class="d-flex align-center">
        <v-icon :icon="snackbarIcon" class="mr-2" />
        <span>{{ snackbarText }}</span>
      </div>
    </v-snackbar>
  </v-card>
</template>

<script setup>
import { ref, computed } from 'vue';
import ProductResponsiveImagePublic from '@/components/product/ProductResponsiveImagePublic.vue';
import RatingDisplay from '@/components/common/RatingDisplay.vue';
import FavoriteProductButton from '@/components/product/FavoriteProductButton.vue';
import AddToCartButton from '@/components/product/AddToCartButton.vue';

const props = defineProps({
  product: { type: Object, required: true },
});

const hover = ref(false);

const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const snackbarIcon = ref('mdi-check-circle');

const productUrl = computed(() => `/product/${props.product.id}-${props.product.slug}`);

const firstImageName = computed(() => {
  const images = props.product.image_url || [];
  if (Array.isArray(images) && images.length) return images[0];
  if (typeof images === 'string' && images) {
    try {
      const parsed = JSON.parse(images);
      if (Array.isArray(parsed) && parsed.length) return parsed[0];
    } catch {
      return images;
    }
  }
  return null;
});
const hasImage = computed(() => !!firstImageName.value);

const productRating = computed(() => {
  if (props.product?.rating_avg != null) return Number(props.product.rating_avg) || 0;
  if (props.product?.rating != null) return Number(props.product.rating) / 200;
  return 0;
});

const reviewsCount = computed(() => {
  if (props.product?.reviews_count != null) return Number(props.product.reviews_count) || 0;
  return 0;
});

function showSnack(payload) {
  snackbarText.value = payload.text;
  snackbarColor.value = payload.color;
  snackbarIcon.value = payload.icon;
  snackbar.value = true;
}
</script>

<style scoped>
.product-card { position: relative; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; }
.product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 16px rgba(0,0,0,0.15) !important; }
.image-container { position: relative; width: 100%; }
.product-image { width: 100%; height: 220px; overflow: hidden; background-color: #f5f5f5; }
.product-image-fallback { width: 100%; height: 220px; object-fit: cover; }

.price-cart-container { display: flex; align-items: center; justify-content: space-between; }
.price-text { font-size: 18px; font-weight: 700; color: #e53935; }

.product-title { font-size: 15px; line-height:1.4; font-weight:500; color: rgb(var(--v-theme-on-surface)); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; text-overflow:ellipsis; min-height:42px; }
.reviews-count { font-size:12px; color:#757575; margin-left:4px; }
.no-decoration { text-decoration:none; color:inherit; display:block; }
</style>