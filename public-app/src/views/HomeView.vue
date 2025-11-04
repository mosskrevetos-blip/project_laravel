<template>
  <div>
    <!-- Баннер -->
    <v-card class="mb-8" flat tile>
      <v-img
        src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?q=80&w=2070&auto=format&fit=crop"
        class="align-end"
        gradient="to bottom, rgba(0,0,0,.1), rgba(0,0,0,.5)"
        height="400px"
        cover
      >
        <v-card-title class="text-white text-h3">Сезонная распродажа</v-card-title>
        <v-card-text class="text-white">
          Лучшие предложения на электронику этого лета!
        </v-card-text>
      </v-img>
    </v-card>

    <!-- Карусель с популярными товарами -->
    <ProductCarousel title="Популярные товары" :products="productStore.popular" />

    <!-- Карусель с новинками -->
    <ProductCarousel title="Новинки" :products="productStore.newest" />
    
    <!-- Здесь можно будет добавить другие карусели -->

  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useProductStore } from '@/stores/productStore';
import ProductCarousel from '@/components/ProductCarousel.vue';

const productStore = useProductStore();

onMounted(() => {
  productStore.fetchPopular();
  productStore.fetchNewest();
});
</script>