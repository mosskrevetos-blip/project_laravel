<template>
  <div
    ref="rootRef"
    class="header-search-wrap"
    @focusin="openDropdown"
    @keydown.esc.stop.prevent="closeDropdown"
  >
    <div class="search-input-row" @mousedown="openDropdown">
      <v-text-field
        ref="inputRef"
        v-model="localQuery"
        placeholder="Я шукаю..."
        variant="solo"
        flat
        rounded="lg"
        hide-details
        density="comfortable"
        bg-color="white"
        prepend-inner-icon="mdi-magnify"
        class="header-search-input"
        autocomplete="off"
        autocorrect="off"
        autocapitalize="off"
        spellcheck="false"
        name="site-search"
        @keydown.enter.prevent="handleSubmit"
        @update:model-value="handleInput"
      >
        <template #append-inner>
          <v-btn
            color="primary"
            variant="flat"
            class="search-submit-btn"
            @mousedown.prevent
            @click="handleSubmit"
          >
            Знайти
          </v-btn>
        </template>
      </v-text-field>
    </div>

    <transition name="search-panel-fade">
      <div v-if="dropdownVisible" class="search-panel">
        <button
          v-if="normalizedQuery"
          type="button"
          class="search-panel-top-action"
          @mousedown.prevent
          @click="handleSubmit"
        >
          <span>Знайти: <strong>{{ normalizedQuery }}</strong></span>
          <v-icon size="20">mdi-chevron-right</v-icon>
        </button>

        <div class="search-section">
          <div class="search-section-header">
            <span class="search-section-title">Історія</span>

            <button
              v-if="hasHistorySection"
              type="button"
              class="search-clear-all-btn"
              @mousedown.prevent
              @click="clearAllHistory"
            >
              Очистити всі
            </button>
          </div>

          <div v-if="hasHistorySection" class="search-items">
            <div
              v-for="item in visibleHistory"
              :key="historyKey(item)"
              class="search-item-row"
            >
              <button
                type="button"
                class="search-item-main"
                @mousedown.prevent
                @click="selectHistoryItem(item)"
              >
                <span class="search-item-icon">
                  <v-icon size="18">mdi-history</v-icon>
                </span>

                <span class="search-item-text">
                  {{ item.query }}
                </span>
              </button>

              <button
                type="button"
                class="search-item-remove"
                aria-label="Видалити з історії"
                title="Видалити з історії"
                @mousedown.prevent
                @click.stop="removeHistoryItem(item)"
              >
                <v-icon size="18">mdi-close</v-icon>
              </button>
            </div>
          </div>

          <div v-else class="search-empty-state">
            Історія пошуку порожня
          </div>
        </div>

        <div
          v-if="showSuggestionsSection"
          class="search-section search-section-top-border"
        >
          <div class="search-section-header">
            <span class="search-section-title">Підказки</span>
          </div>

          <div v-if="hasSuggestionsSection" class="search-items">
            <div
              v-for="item in visibleSuggestions"
              :key="`suggestion-${item.title}`"
              class="search-item-row"
            >
              <button
                type="button"
                class="search-item-main"
                @mousedown.prevent
                @click="selectSuggestion(item)"
              >
                <span class="search-item-icon">
                  <v-icon size="18">mdi-magnify</v-icon>
                </span>

                <span class="search-item-text">
                  {{ item.title }}
                </span>
              </button>
            </div>
          </div>

          <div
            v-else-if="normalizedQuery && !searchStore.suggestionsLoading"
            class="search-empty-state"
          >
            Підказок не знайдено
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useSearchStore } from '@/stores/searchStore';

const router = useRouter();
const searchStore = useSearchStore();

const rootRef = ref(null);
const localQuery = ref(searchStore.query || '');
const isOpen = ref(false);

const normalizedQuery = computed(() => searchStore.normalizeQuery(localQuery.value));
const visibleHistory = computed(() => searchStore.filteredHistory || []);
const visibleSuggestions = computed(() => searchStore.suggestions || []);

const hasHistorySection = computed(() => visibleHistory.value.length > 0);
const hasSuggestionsSection = computed(() => visibleSuggestions.value.length > 0);
const showSuggestionsSection = computed(() => !!normalizedQuery.value);

// Панель открывается всегда при фокусе/клике в компонент
const dropdownVisible = computed(() => isOpen.value);

function historyKey(item) {
  return item.id ?? `${item.query}-${item.searched_at}`;
}

function openDropdown() {
  isOpen.value = true;

  if (!normalizedQuery.value) {
    searchStore.clearSuggestions();
  }
}

function closeDropdown() {
  isOpen.value = false;
}

function handleInput(value) {
  localQuery.value = value;
  searchStore.setQuery(value);

  const normalized = searchStore.normalizeQuery(value);

  if (!normalized) {
    searchStore.clearSuggestions();
    openDropdown();
    return;
  }

  openDropdown();
  searchStore.debounceFetchSuggestions(normalized);
}

function handleClickOutside(event) {
  if (!rootRef.value) return;
  if (!rootRef.value.contains(event.target)) {
    closeDropdown();
  }
}

function handleSubmit() {
  const q = normalizedQuery.value;
  if (!q) return;

  closeDropdown();
  searchStore.submitSearch(q, router);
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

async function removeHistoryItem(item) {
  await searchStore.removeHistoryItem(item);
  openDropdown();
}

async function clearAllHistory() {
  await searchStore.clearAllHistory();
  openDropdown();
}

watch(
  () => searchStore.query,
  value => {
    if (value !== localQuery.value) {
      localQuery.value = value || '';
    }
  }
);

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.header-search-wrap {
  position: relative;
  width: 100%;
}

.search-input-row {
  width: 100%;
}

.header-search-input :deep(.v-field) {
  border-radius: 14px;
  box-shadow: none;
}

.header-search-input :deep(input) {
  font-size: 18px;
}

.search-submit-btn {
  min-width: 110px;
  margin-right: -6px;
  text-transform: none;
  font-weight: 600;
}

.search-panel {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  z-index: 1000;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 12px 35px rgba(0, 0, 0, 0.14);
  overflow: hidden;
  color: #1f2430;
}

.search-panel-top-action {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px;
  font-size: 18px;
  line-height: 1.3;
  cursor: pointer;
  border: 0;
  background: #fff;
  border-bottom: 1px solid #eef0f4;
  text-align: left;
}

.search-panel-top-action:hover {
  background: #f8f9fc;
}

.search-section {
  padding: 8px 0 4px;
}

.search-section-top-border {
  border-top: 1px solid #eef0f4;
}

.search-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 18px 8px;
}

.search-section-title {
  font-size: 14px;
  color: #7b8190;
}

.search-clear-all-btn {
  border: none;
  background: transparent;
  color: #2f80ed;
  cursor: pointer;
  font-size: 14px;
  padding: 0;
}

.search-clear-all-btn:hover {
  text-decoration: underline;
}

.search-items {
  display: flex;
  flex-direction: column;
}

.search-item-row {
  display: flex;
  align-items: center;
  min-height: 52px;
  padding: 0 10px 0 6px;
}

.search-item-main {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  padding: 12px 12px;
  border-radius: 10px;
}

.search-item-main:hover {
  background: #f7f8fb;
}

.search-item-icon {
  color: #9aa1b1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.search-item-text {
  display: block;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #252b36;
  font-size: 16px;
}

.search-item-remove {
  border: none;
  background: transparent;
  cursor: pointer;
  color: #a2a8b8;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  border-radius: 8px;
}

.search-item-remove:hover {
  background: #f3f5f9;
  color: #6c7383;
}

.search-empty-state {
  padding: 14px 18px 18px;
  color: #7b8190;
  font-size: 15px;
}

.search-panel-fade-enter-active,
.search-panel-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.search-panel-fade-enter-from,
.search-panel-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@media (max-width: 768px) {
  .header-search-input :deep(input) {
    font-size: 16px;
  }

  .search-submit-btn {
    min-width: 88px;
  }

  .search-panel-top-action {
    font-size: 16px;
  }

  .search-item-text {
    font-size: 15px;
  }
}
</style>