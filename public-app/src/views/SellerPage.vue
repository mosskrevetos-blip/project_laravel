<template>
  <div class="py-6">
    <div class="mb-6">
      <div v-if="sellerStore.seller" class="d-flex justify-space-between align-center flex-wrap ga-3">
        <div>
          <h1 class="text-h4 font-weight-bold mb-1">{{ sellerStore.seller.name }}</h1>
          <div class="text-body-2 text-medium-emphasis">
            Товарів: {{ sellerStore.pagination.total || 0 }}
          </div>
        </div>

        <FavoriteSellerButton
          v-if="sellerStore.seller?.id"
          :seller-id="Number(sellerStore.seller.id)"
        />
      </div>
      <v-skeleton-loader v-else-if="sellerStore.loading" type="article" />
    </div>

    <div v-if="sellerStore.loading">
      <v-row>
        <v-col v-for="n in 8" :key="`seller-skeleton-${n}`" cols="12" sm="6" md="4" lg="3">
          <v-skeleton-loader type="image, article" />
        </v-col>
      </v-row>
    </div>

    <div v-else-if="sellerStore.error" class="py-8 text-center">
      <v-alert type="error" variant="tonal">
        {{ sellerStore.error }}
      </v-alert>
    </div>

    <div v-else-if="sellerStore.products.length === 0" class="py-8 text-center">
      <v-alert type="info" variant="tonal">
        У цього продавця поки немає доступних товарів.
      </v-alert>
    </div>

    <div v-else>
      <v-row>
        <v-col
          v-for="product in sellerStore.products"
          :key="product.id"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <ProductCard :product="product" />
        </v-col>
      </v-row>

      <div v-if="sellerStore.pagination.last_page > 1" class="d-flex justify-center mt-8">
        <v-pagination
          v-model="currentPage"
          :length="sellerStore.pagination.last_page"
          :total-visible="7"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSellerStore } from '@/stores/sellerStore';
import ProductCard from '@/components/product/ProductCard.vue';
import FavoriteSellerButton from '@/components/product/FavoriteSellerButton.vue';

const route = useRoute();
const router = useRouter();
const sellerStore = useSellerStore();

const sellerId = computed(() => {
  const id = Number(route.params.id);
  return Number.isNaN(id) || id < 1 ? null : id;
});

const currentPage = computed({
  get() {
    const page = Number(route.query.page || 1);
    return Number.isNaN(page) || page < 1 ? 1 : page;
  },
  set(value) {
    router.push({
      name: 'seller.show',
      params: { id: sellerId.value },
      query: { ...route.query, page: value },
    });
  },
});

async function loadSellerPage() {
  if (!sellerId.value) {
    sellerStore.reset();
    return;
  }

  try {
    await sellerStore.fetchSellerProfile({
      sellerId: sellerId.value,
      page: currentPage.value,
      perPage: 12,
    });
  } catch (_) {
    // error handled in store
  }
}

watch(
  () => [route.params.id, route.query.page],
  async () => {
    await loadSellerPage();
  },
  { immediate: true }
);
</script>