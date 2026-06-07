<template>
  <div class="header-search-wrap">
    <div class="position-relative">
      <v-text-field
        v-model="localQuery"
        label="Пошук товарів"
        variant="outlined"
        density="comfortable"
        prepend-inner-icon="mdi-magnify"
        hide-details
        clearable
        bg-color="white"
        class="header-search-input"
        @focus="handleFocus"
        @click="handleFocus"
        @keydown.enter.prevent="handleSubmit"
        @update:model-value="handleInput"
      >
        <template #append-inner>
          <v-btn
            color="primary"
            variant="flat"
            size="small"
            class="search-btn"
            @click="handleSubmit"
          >
            Пошук
          </v-btn>
        </template>
      </v-text-field>

      <v-card
        v-if="dropdownVisible"
        class="search-dropdown mt-2"
        elevation="8"
      >
        <v-list density="compact" nav>
          <template v-if="hasHistorySection">
            <v-list-subheader>Недавні запити</v-list-subheader>

            <v-list-item
              v-for="(item, index) in visibleHistory"
              :key="`history-${index}-${item.id || item.searched_at || item.query}`"
              @click="selectHistoryItem(item)"
            >
              <template #prepend>
                <v-icon size="18">mdi-history</v-icon>
              </template>

              <v-list-item-title>
                {{ item.query }}
              </v-list-item-title>

              <v-list-item-subtitle>
                Знайдено товарів: {{ item.result_count ?? 0 }}
              </v-list-item-subtitle>
            </v-list-item>

            <v-divider v-if="hasSuggestionsSection" class="my-2" />
          </template>

          <template v-if="hasSuggestionsSection">
            <v-list-subheader>Підказки</v-list-subheader>

            <v-list-item
              v-for="(item, index) in visibleSuggestions"
              :key="`suggestion-${index}-${item.title}`"
              @click="selectSuggestion(item)"
            >
              <template #prepend>
                <v-icon size="18">mdi-magnify</v-icon>
              </template>

              <v-list-item-title>
                {{ item.title }}
              </v-list-item-title>
            </v-list-item>
          </template>

          <template v-if="showEmptyState">
            <v-list-item>
              <v-list-item-title class="text-medium-emphasis">
                Нічого не знайдено
              </v-list-item-title>
            </v-list-item>
          </template>
        </v-list>
      </v-card>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useSearchStore } from '@/stores/searchStore';

const router = useRouter();
const searchStore = useSearchStore();

const localQuery = ref(searchStore.query || '');
const isFocused = ref(false);

function handleFocus() {
  isFocused.value = true;

  if (!searchStore.normalizeQuery(localQuery.value)) {
    searchStore.clearSuggestions();
  }
}

function handleInput(value) {
  searchStore.setQuery(value);
  localQuery.value = value;

  const normalized = searchStore.normalizeQuery(value);

  if (!normalized) {
    searchStore.clearSuggestions();
    return;
  }

  searchStore.debounceFetchSuggestions(normalized);
}

function closeDropdown() {
  isFocused.value = false;
}

function handleClickOutside(event) {
  const target = event.target;
  const root = document.querySelector('.header-search-wrap');

  if (root && !root.contains(target)) {
    closeDropdown();
  }
}

function handleSubmit() {
  const normalized = searchStore.normalizeQuery(localQuery.value);

  if (!normalized) {
    return;
  }

  searchStore.setQuery(normalized);
  localQuery.value = normalized;
  closeDropdown();

  searchStore.submitSearch(normalized, router);
}

function selectHistoryItem(item) {
  localQuery.value = item.query;
  searchStore.setQuery(item.query);
  closeDropdown();

  searchStore.submitSearch(item.query, router);
}

function selectSuggestion(item) {
  localQuery.value = item.title;
  searchStore.setQuery(item.title);
  closeDropdown();

  searchStore.submitSearch(item.title, router);
}

const visibleHistory = computed(() => {
  return searchStore.filteredHistory || [];
});

const visibleSuggestions = computed(() => {
  return searchStore.suggestions || [];
});

const hasHistorySection = computed(() => visibleHistory.value.length > 0);
const hasSuggestionsSection = computed(() => visibleSuggestions.value.length > 0);

const dropdownVisible = computed(() => {
  return isFocused.value && (hasHistorySection.value || hasSuggestionsSection.value || showEmptyState.value);
});

const showEmptyState = computed(() => {
  const normalized = searchStore.normalizeQuery(localQuery.value);

  if (!isFocused.value) return false;
  if (!normalized) return false;

  return !hasHistorySection.value && !hasSuggestionsSection.value && !searchStore.suggestionsLoading;
});

watch(localQuery, (value) => {
  searchStore.setQuery(value);
});

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.header-search-wrap {
  width: 100%;
}

.position-relative {
  position: relative;
}

.header-search-input :deep(.v-field) {
  border-radius: 12px;
}

.search-btn {
  margin-right: -4px;
}

.search-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 1000;
  border-radius: 12px;
  overflow: hidden;
}
</style>