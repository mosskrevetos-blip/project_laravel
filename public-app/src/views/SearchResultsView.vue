<template>
  <div class="py-6">
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold mb-2">Результати пошуку</h1>

      <div v-if="normalizedQuery" class="text-subtitle-1 text-medium-emphasis">
        За запитом: <strong>"{{ normalizedQuery }}"</strong>
      </div>

      <div class="text-body-2 text-medium-emphasis mt-2">
        Знайдено товарів: {{ searchStore.pagination.total || 0 }}
      </div>
    </div>

    <div v-if="searchStore.loading">
      <v-row>
        <v-col
          v-for="n in 8"
          :key="`search-skeleton-${n}`"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <v-skeleton-loader type="image, article" />
        </v-col>
      </v-row>
    </div>

    <div v-else-if="searchStore.error" class="py-8 text-center">
      <v-alert type="error" variant="tonal">
        {{ searchStore.error }}
      </v-alert>
    </div>

    <div v-else-if="!normalizedQuery" class="py-8 text-center">
      <v-alert type="info" variant="tonal">
        Введіть пошуковий запит.
      </v-alert>
    </div>

    <div v-else-if="searchStore.results.length === 0" class="py-8 text-center">
      <v-alert type="warning" variant="tonal">
        За вашим запитом нічого не знайдено.
      </v-alert>
    </div>

    <div v-else>
      <v-row>
        <v-col
          v-for="product in searchStore.results"
          :key="product.id"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <ProductCard :product="product" />
        </v-col>
      </v-row>

      <div
        v-if="searchStore.pagination.last_page > 1"
        class="d-flex justify-center mt-8"
      >
        <v-pagination
          v-model="currentPage"
          :length="searchStore.pagination.last_page"
          :total-visible="7"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSearchStore } from '@/stores/searchStore';
import ProductCard from '@/components/product/ProductCard.vue';

const route = useRoute();
const router = useRouter();
const searchStore = useSearchStore();

const lastRecordedKey = ref(null);

const normalizedQuery = computed(() => {
  return searchStore.normalizeQuery(route.query.q || '');
});

const currentPage = computed({
  get() {
    const page = Number(route.query.page || 1);
    return Number.isNaN(page) || page < 1 ? 1 : page;
  },
  set(value) {
    router.push({
      name: 'search.results',
      query: {
        q: normalizedQuery.value,
        page: value,
      },
    });
  },
});

async function loadSearchResults() {
  const query = normalizedQuery.value;
  const page = currentPage.value;

  searchStore.setQuery(query);

  if (!query) {
    searchStore.clearResults();
    return;
  }

  const { total } = await searchStore.searchProducts({
    query,
    page,
    perPage: 12,
  });

  const recordKey = `${query}::${page}::${total}`;

  if (lastRecordedKey.value !== recordKey) {
    await searchStore.recordSearch({
      query,
      resultCount: total,
    });
    lastRecordedKey.value = recordKey;
  }
}

watch(
  () => [route.query.q, route.query.page],
  async () => {
    await loadSearchResults();
  },
  { immediate: true }
);
</script>