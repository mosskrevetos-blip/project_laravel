<template>
  <div class="comments-wrap mt-8">
    <v-card elevation="1">
      <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-2">
        <div class="text-h6">Відгуки та питання</div>
        <v-btn size="small" variant="outlined" :loading="loading" @click="reload">Оновити</v-btn>
      </v-card-title>

      <v-divider />

      <v-card-text>
        <!-- Tabs -->
        <v-tabs v-model="activeType" class="mb-4">
          <v-tab value="review">Відгуки</v-tab>
          <v-tab value="question">Питання</v-tab>
        </v-tabs>

        <!-- Filters -->
        <v-row class="mb-2">
          <v-col cols="12" md="3">
            <v-select
              v-model="localFilters.sort"
              :items="sortOptions"
              item-title="title"
              item-value="value"
              label="Сортування"
              density="comfortable"
              variant="outlined"
              hide-details
            />
          </v-col>

          <v-col cols="12" md="3" v-if="activeType === 'review'">
            <v-select
              v-model="localFilters.rating"
              :items="ratingOptions"
              label="Рейтинг"
              density="comfortable"
              variant="outlined"
              hide-details
              clearable
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-checkbox v-model="localFilters.with_photos" label="Тільки з фото" density="compact" hide-details />
          </v-col>

          <v-col cols="12" md="3">
            <v-checkbox v-model="localFilters.verified" label="Підтверджена покупка" density="compact" hide-details />
          </v-col>
        </v-row>

        <div class="mb-4 d-flex ga-2">
          <v-btn color="primary" @click="applyFilters">Застосувати</v-btn>
        </div>

        <!-- Create form -->
        <v-card variant="tonal" class="mb-6">
          <v-card-title class="text-subtitle-1">
            {{ activeType === 'review' ? 'Залишити відгук' : 'Поставити питання' }}
          </v-card-title>
          <v-card-text>
            <v-row>
              <v-col cols="12" md="4" v-if="activeType === 'review'">
                <v-select
                  v-model="form.rating"
                  :items="[1,2,3,4,5]"
                  label="Рейтинг *"
                  variant="outlined"
                  density="comfortable"
                />
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="form.body"
                  :label="activeType === 'review' ? 'Текст відгуку' : 'Текст питання *'"
                  variant="outlined"
                  rows="3"
                  auto-grow
                  maxlength="5000"
                  counter
                />
              </v-col>

              <template v-if="activeType === 'review'">
                <v-col cols="12" md="6">
                  <v-textarea
                    v-model="form.pros"
                    label="Переваги"
                    variant="outlined"
                    rows="2"
                    auto-grow
                    maxlength="5000"
                    counter
                  />
                </v-col>
                <v-col cols="12" md="6">
                  <v-textarea
                    v-model="form.cons"
                    label="Недоліки"
                    variant="outlined"
                    rows="2"
                    auto-grow
                    maxlength="5000"
                    counter
                  />
                </v-col>
              </template>

              <v-col cols="12" md="6">
                <v-file-input
                  v-model="form.images"
                  label="Фото (до 5)"
                  variant="outlined"
                  density="comfortable"
                  multiple
                  show-size
                  accept="image/*"
                  prepend-icon="mdi-camera"
                />
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="form.youtube_url"
                  label="YouTube URL (необовʼязково)"
                  variant="outlined"
                  density="comfortable"
                />
              </v-col>
            </v-row>

            <div class="d-flex ga-2">
              <v-btn color="primary" :loading="createLoading" @click="submitComment">Надіслати</v-btn>
              <v-btn variant="text" @click="resetForm">Очистити</v-btn>
            </div>
          </v-card-text>
        </v-card>

        <!-- List -->
        <div v-if="loading" class="py-6 text-center">
          <v-progress-circular indeterminate color="primary" />
        </div>

        <div v-else-if="!items.length" class="py-6 text-medium-emphasis text-center">
          Поки немає записів.
        </div>

        <div v-else class="d-flex flex-column ga-4">
          <v-card v-for="item in items" :key="item.id" variant="outlined">
            <v-card-text>
              <div class="d-flex justify-space-between align-start ga-4">
                <div>
                  <div class="text-subtitle-2">{{ item.author?.name || `User #${item.author_id}` }}</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ formatDate(item.created_at) }}
                    <span v-if="item.is_verified_purchase"> • ✅ Підтверджена покупка</span>
                  </div>
                </div>

                <div v-if="item.type === 'review' && item.rating" class="text-body-2">
                  ⭐ {{ item.rating }}/5
                </div>
              </div>

              <div v-if="item.body" class="mt-3 body">{{ item.body }}</div>
              <div v-if="item.pros" class="mt-2 text-body-2"><strong>Переваги:</strong> {{ item.pros }}</div>
              <div v-if="item.cons" class="mt-1 text-body-2"><strong>Недоліки:</strong> {{ item.cons }}</div>

              <div v-if="item.media?.length" class="mt-3 d-flex flex-wrap ga-2">
                <template v-for="m in item.media" :key="m.id">
                  <v-img
                    v-if="m.type === 'image'"
                    :src="m.url"
                    width="96"
                    height="96"
                    cover
                    class="rounded border-sm"
                  />
                  <a
                    v-else-if="m.type === 'youtube'"
                    :href="m.url"
                    target="_blank"
                    rel="noopener"
                    class="text-primary text-body-2"
                  >
                    YouTube
                  </a>
                </template>
              </div>

              <div class="mt-4 d-flex flex-wrap ga-2">
                <v-btn size="small" variant="tonal" :loading="actionLoading" @click="onReact(item.id, 'like')">
                  👍 {{ item.likes_count || 0 }}
                </v-btn>
                <v-btn size="small" variant="tonal" :loading="actionLoading" @click="onReact(item.id, 'dislike')">
                  👎 {{ item.dislikes_count || 0 }}
                </v-btn>
                <v-btn size="small" color="warning" variant="text" @click="openReport(item)">
                  Поскаржитись
                </v-btn>
              </div>

              <div v-if="item.answers?.length" class="mt-4 d-flex flex-column ga-2">
                <v-alert v-for="ans in item.answers" :key="ans.id" type="info" variant="tonal">
                  <div class="text-caption mb-1">
                    Відповідь: {{ ans.answer_origin === 'administration' ? 'Адміністрація' : 'Продавець' }}
                  </div>
                  <div class="body">{{ ans.body }}</div>
                </v-alert>
              </div>
            </v-card-text>
          </v-card>
        </div>

        <div class="d-flex justify-center mt-4" v-if="pagination.last_page > 1">
          <v-pagination
            v-model="localFilters.page"
            :length="pagination.last_page"
            @update:model-value="onPageChange"
          />
        </div>
      </v-card-text>
    </v-card>

    <v-dialog v-model="reportDialog.open" max-width="560">
      <v-card>
        <v-card-title>Скарга на коментар #{{ reportDialog.commentId }}</v-card-title>
        <v-card-text>
          <v-textarea
            v-model="reportDialog.reason"
            label="Причина скарги"
            variant="outlined"
            rows="4"
            auto-grow
            maxlength="5000"
            counter
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeReportDialog">Скасувати</v-btn>
          <v-btn color="warning" :loading="reportLoading" @click="submitReport">Надіслати</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.open" :color="snackbar.color" :timeout="2500" location="top right">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useProductCommentsStore } from '@/stores/productCommentsStore';
import { useAuthStore } from '@/stores/authStore';

const props = defineProps({
  productId: { type: [Number, String], required: true },
});

const authStore = useAuthStore();
const commentsStore = useProductCommentsStore();

const {
  items,
  loading,
  error,
  pagination,
  filters,
  createLoading,
  actionLoading,
  reportLoading,
} = storeToRefs(commentsStore);

const activeType = ref('review');

const localFilters = reactive({
  sort: 'date_desc',
  rating: null,
  with_photos: false,
  verified: false,
  page: 1,
  per_page: 10,
});

const ratingOptions = [5, 4, 3, 2, 1];
const sortOptions = ref([]);

const form = reactive({
  rating: null,
  body: '',
  pros: '',
  cons: '',
  images: [],
  youtube_url: '',
});

const reportDialog = reactive({
  open: false,
  commentId: null,
  reason: '',
});

const snackbar = reactive({
  open: false,
  text: '',
  color: 'success',
});

function showSnack(text, color = 'success') {
  snackbar.text = text;
  snackbar.color = color;
  snackbar.open = true;
}

function formatDate(v) {
  if (!v) return '';
  return new Date(v).toLocaleString('uk-UA');
}

function buildSortOptions() {
  if (activeType.value === 'review') {
    sortOptions.value = [
      { title: 'Нові спочатку', value: 'date_desc' },
      { title: 'Старі спочатку', value: 'date_asc' },
      { title: 'Рейтинг: високий → низький', value: 'rating_desc' },
      { title: 'Рейтинг: низький → високий', value: 'rating_asc' },
      { title: 'Найкорисніші', value: 'helpful_desc' },
      { title: 'Найменш корисні', value: 'helpful_asc' },
    ];
  } else {
    sortOptions.value = [
      { title: 'Нові спочатку', value: 'date_desc' },
      { title: 'Старі спочатку', value: 'date_asc' },
      { title: 'Найкорисніші', value: 'helpful_desc' },
      { title: 'Найменш корисні', value: 'helpful_asc' },
    ];

    if (localFilters.sort === 'rating_desc' || localFilters.sort === 'rating_asc') {
      localFilters.sort = 'date_desc';
    }
  }
}

async function loadComments() {
  try {
    commentsStore.setType(activeType.value);
    commentsStore.setFilters({
      page: localFilters.page,
      per_page: localFilters.per_page,
      sort: localFilters.sort,
      rating: activeType.value === 'review' ? localFilters.rating : null,
      with_photos: localFilters.with_photos,
      verified: localFilters.verified,
    });
    await commentsStore.fetchList();
  } catch {
    showSnack(error.value || 'Помилка завантаження', 'error');
  }
}

function applyFilters() {
  localFilters.page = 1;
  loadComments();
}

function onPageChange(page) {
  localFilters.page = page;
  loadComments();
}

function resetForm() {
  form.rating = null;
  form.body = '';
  form.pros = '';
  form.cons = '';
  form.images = [];
  form.youtube_url = '';
}

async function submitComment() {
  if (!authStore.isAuthenticated) {
    authStore.openLoginDialog?.();
    showSnack('Увійдіть, щоб залишити коментар', 'warning');
    return;
  }

  try {
    await commentsStore.createComment({
      type: activeType.value,
      rating: form.rating,
      body: form.body,
      pros: form.pros,
      cons: form.cons,
      images: form.images,
      youtube_url: form.youtube_url,
    });

    showSnack('Коментар відправлено на модерацію', 'success');
    resetForm();
    await loadComments();
  } catch {
    showSnack(error.value || 'Не вдалося відправити коментар', 'error');
  }
}

async function onReact(commentId, reaction) {
  if (!authStore.isAuthenticated) {
    authStore.openLoginDialog?.();
    showSnack('Увійдіть, щоб оцінювати коментарі', 'warning');
    return;
  }

  try {
    await commentsStore.react(commentId, reaction);
  } catch {
    showSnack(error.value || 'Не вдалося зберегти реакцію', 'error');
  }
}

function openReport(item) {
  if (!authStore.isAuthenticated) {
    authStore.openLoginDialog?.();
    showSnack('Увійдіть, щоб надіслати скаргу', 'warning');
    return;
  }
  reportDialog.open = true;
  reportDialog.commentId = item.id;
  reportDialog.reason = '';
}

function closeReportDialog() {
  reportDialog.open = false;
  reportDialog.commentId = null;
  reportDialog.reason = '';
}

async function submitReport() {
  try {
    await commentsStore.report(reportDialog.commentId, reportDialog.reason);
    showSnack('Скаргу відправлено', 'success');
    closeReportDialog();
  } catch {
    showSnack(error.value || 'Не вдалося надіслати скаргу', 'error');
  }
}

function reload() {
  loadComments();
}

watch(activeType, async () => {
  buildSortOptions();
  localFilters.page = 1;
  localFilters.rating = null;
  await loadComments();
});

watch(() => props.productId, async (newId) => {
  commentsStore.setProduct(newId);
  localFilters.page = 1;
  await loadComments();
});

onMounted(async () => {
  commentsStore.setProduct(props.productId);
  buildSortOptions();
  await loadComments();
});
</script>

<style scoped>
.comments-wrap {
  width: 100%;
}
.body {
  white-space: pre-wrap;
  word-break: break-word;
}
</style>