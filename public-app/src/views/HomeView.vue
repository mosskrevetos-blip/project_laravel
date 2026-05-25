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
        <v-card-title class="text-white text-h3">Сезонний розпродаж</v-card-title>
        <v-card-text class="text-white">
          Кращі пропозиції на електроніку цього літа!
        </v-card-text>
      </v-img>
    </v-card>

    <!-- Карусель з популярними товарами -->
    <ProductCarousel title="Популярні товари" :products="productStore.popular" :loading="loading" />

    <!-- Карусель з новинками -->
    <ProductCarousel title="Новинки" :products="productStore.newest" :loading="loading" />
    
    <!-- Тут можна буде додати інші каруселі -->

  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useProductStore } from '@/stores/productStore';
import ProductCarousel from '@/components/product/ProductCarousel.vue';

const productStore = useProductStore();
const loading = ref(true);

onMounted(async () => {
  loading.value = true;
  await productStore.fetchPopular();
  await productStore.fetchNewest();
  loading.value = false;
});
</script>