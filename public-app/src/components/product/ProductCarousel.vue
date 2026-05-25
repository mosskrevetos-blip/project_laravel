// public-app/src/components/product/ProductCarousel.vue

<template>
  <div class="my-8">
    <h2 class="text-h4 font-weight-bold mb-4">{{ title }}</h2>
    <v-sheet class="mx-auto">
      <!-- Скелетон-завантаження поки товари завантажуються -->
      <v-slide-group v-if="loading" class="pa-2" show-arrows>
        <v-slide-group-item v-for="n in 5" :key="n">
          <v-skeleton-loader
            type="card"
            class="ma-4"
            width="250"
            height="420"
          ></v-skeleton-loader>
        </v-slide-group-item>
      </v-slide-group>
      <!-- Карусель з товарами -->
      <v-slide-group v-else class="pa-2" show-arrows>
        <v-slide-group-item v-for="product in products" :key="product.id">
          <ProductCard :product="product" />
        </v-slide-group-item>
      </v-slide-group>
    </v-sheet>
  </div>
</template>

<script setup>
import ProductCard from '@/components/product/ProductCard.vue';

defineProps({
  title: {
    type: String,
    required: true,
  },
  products: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});
</script>