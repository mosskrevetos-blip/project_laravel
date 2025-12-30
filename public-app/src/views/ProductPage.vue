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
            <h1 class="text-h5 mb-2">{{ product.title }}</h1>

            <div class="mb-3">
              <v-chip v-if="product.quantity > 0" color="green" text-color="white">В наличии: {{ product.quantity }}</v-chip>
              <v-chip v-else color="grey">Нет в наличии</v-chip>
            </div>

            <div class="mb-4">
              <span class="text-h4 font-weight-bold primary--text">{{ product.price }} {{ product.currency ? product.currency : '$' }}</span>
            </div>

            <div class="mb-4">
              <h3 class="text-subtitle-1">Описание</h3>
              <div v-html="product.description || '<i>Описание отсутствует</i>'"></div>
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

            <div class="mt-6 d-flex gap-2">
              <v-btn color="primary" @click="addToCart">Добавить в корзину</v-btn>
              <v-btn color="secondary" @click="goToCheckout">Оформить</v-btn>
            </div>
          </div>

          <div v-else>
            <v-skeleton-loader type="paragraph" />
          </div>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProductStore } from '@/stores/productStore';
import ProductImageGallery from '@/components/ProductImageGallery.vue';
import { useCartStore } from '@/stores/cartStore';

const route = useRoute();
const router = useRouter();
const productStore = useProductStore();
const cart = useCartStore();

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

function addToCart() {
  if (!product.value) return;
  cart.addItem({
    id: product.value.id,
    title: product.value.title,
    price: product.value.price,
    image: (Array.isArray(product.value.image_url) ? product.value.image_url[0] : product.value.image_url) || null,
  }, 1);
  // Никаких alert / модальных окон — бейдж в шапке обновится автоматически
}

function goToCheckout() {
  router.push({ name: 'checkout' });
}
</script>

<style scoped>
/* Небольшие отступы для читабельности */
</style>