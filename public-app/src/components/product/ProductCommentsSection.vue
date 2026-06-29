<template>
  <div class="comments-wrap mt-8">
    <v-card elevation="1">
      <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-2">
        <div class="text-h6">Відгуки та питання</div>
        <v-btn
          size="small"
          variant="outlined"
          :loading="loading"
          @click="reload"
        >
          Оновити
        </v-btn>
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
              v-model="filters.sort"
              :items="sortOptions"
              label="Сортування"
              density="comfortable"
              variant="outlined"
              hide-details
            />
          </v-col>

          <v-col cols="12" md="3" v-if="activeType === 'review'">
            <v-select
              v-model="filters.rating"
              :items="ratingOptions"
              label="Рейтинг"
              density="comfortable"
              variant="outlined"
              hide-details
              clearable
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-checkbox
              v-model="filters.with_photos"
              label="Тільки з фото"
              density="compact"
              hide-details
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-checkbox
              v-model="filters.verified"
              label="Підтверджена покупка"
              density="compact"
              hide-details
            />
          </v-col>
        </v-row>

        <div class="mb-4">
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
              <v-btn color="primary" :loading="submitLoading" @click="submitComment">
                Надіслати
              </v-btn>
              <v-btn variant="text" @click="resetForm">Очистити</v-btn>
            </div>
          </v-card-text>
        </v-card>

        <!-- List -->
        <div v-if="loading" class="py-6 text-center">
          <v-progress-circular indeterminate color="primary" />
        </div>

        <div v-else-if="!comments.length" class="py-6 text-medium-emphasis text-center">
          Поки немає записів.
        </div>

        <div v-else class="d-flex flex-column ga-4">
          <v-card v-for="item in comments" :key="item.id" variant="outlined">
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

              <div v-if="item.body" class="mt-3" style="white-space: pre-wrap;">{{ item.body }}</div>
              <div v-if="item.pros" class="mt-2 text-body-2"><strong>Переваги:</strong> {{ item.pros }}</div>
              <div v-if="item.cons" class="mt-1 text-body-2"><strong>Недоліки:</strong> {{ item.cons }}</div>

              <!-- media -->
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

              <!-- actions -->
              <div class="mt-4 d-flex flex-wrap ga-2">
                <v-btn size="small" variant="tonal" @click="react(item.id, 'like')">
                  👍 {{ item.likes_count || 0 }}
                </v-btn>
                <v-btn size="small" variant="tonal" @click="react(item.id, 'dislike')">
                  👎 {{ item.dislikes_count || 0 }}
                </v-btn>
                <v-btn size="small" color="warning" variant="text" @click="openReport(item)">
                  Поскаржитись
                </v-btn>
              </div>

              <!-- answers -->
              <div v-if="item.answers?.length" class="mt-4 d-flex flex-column ga-2">
                <v-alert
                  v-for="ans in item.answers"
                  :key="ans.id"
                  type="info"
                  variant="tonal"
                >
                  <div class="text-caption mb-1">
                    Відповідь: {{ ans.answer_origin === 'administration' ? 'Адміністрація' : 'Продавець' }}
                  </div>
                  <div style="white-space: pre-wrap;">{{ ans.body }}</div>
                </v-alert>
              </div>
            </v-card-text>
          </v-card>
        </div>

        <div class="d-flex justify-center mt-4" v-if="pagination.last_page > 1">
          <v-pagination
            v-model="filters.page"
            :length="pagination.last_page"
            @update:model-value="loadComments"
          />
        </div>
      </v-card-text>
    </v-card>

    <!-- Report dialog -->
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
          <v-btn color="warning" @click="submitReport">Надіслати</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.open" :color="snackbar.color" :timeout="2500" location="top right">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { reactive, ref, watch, onMounted } from 'vue';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const props = defineProps({
  productId: {
    type: [Number, String],
    required: true,
  },
});

const authStore = useAuthStore();

const activeType = ref('review');
const loading = ref(false);
const submitLoading = ref(false);

const comments = ref([]);
const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
});

const filters = reactive({
  type: 'review',
  sort: 'date_desc',
  rating: null,
  with_photos: false,
  verified: false,
  page: 1,
  per_page: 10,
});

const sortOptions = ref([
  { title: 'Нові спочатку', value: 'date_desc' },
  { title: 'Старі спочатку', value: 'date_asc' },
  { title: 'Найкорисніші', value: 'helpful_desc' },
  { title: 'Найменш корисні', value: 'helpful_asc' },
]);

const ratingOptions = [5, 4, 3, 2, 1];

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
    if (filters.sort === 'rating_desc' || filters.sort === 'rating_asc') {
      filters.sort = 'date_desc';
    }
  }
}

async function loadComments() {
  loading.value = true;
  try {
    filters.type = activeType.value;

    const params = {
      type: filters.type,
      sort: filters.sort,
      page: filters.page,
      per_page: filters.per_page,
      with_photos: filters.with_photos ? 1 : 0,
      verified: filters.verified ? 1 : 0,
    };

    if (activeType.value === 'review' && filters.rating) {
      params.rating = filters.rating;
    }

    const { data } = await apiClient.get(`/products/${props.productId}/comments`, { params });

    comments.value = data.data || [];
    pagination.current_page = data.current_page || 1;
    pagination.last_page = data.last_page || 1;
    pagination.per_page = data.per_page || 10;
    pagination.total = data.total || 0;
  } catch (e) {
    showSnack('Не вдалося завантажити коментарі', 'error');
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  filters.page = 1;
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

  submitLoading.value = true;
  try {
    await apiClient.getCsrfCookie();

    const fd = new FormData();
    fd.append('type', activeType.value);

    if (activeType.value === 'review' && form.rating) {
      fd.append('rating', String(form.rating));
    }

    if (form.body) fd.append('body', form.body);
    if (activeType.value === 'review' && form.pros) fd.append('pros', form.pros);
    if (activeType.value === 'review' && form.cons) fd.append('cons', form.cons);
    if (form.youtube_url) fd.append('youtube_url', form.youtube_url);

    (form.images || []).slice(0, 5).forEach((img) => {
      fd.append('images[]', img);
    });

    await apiClient.post(`/products/${props.productId}/comments`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    showSnack('Коментар відправлено на модерацію', 'success');
    resetForm();
    await loadComments();
  } catch (e) {
    const msg = e?.response?.data?.message || 'Не вдалося відправити коментар';
    showSnack(msg, 'error');
  } finally {
    submitLoading.value = false;
  }
}

async function react(commentId, reaction) {
  if (!authStore.isAuthenticated) {
    authStore.openLoginDialog?.();
    showSnack('Увійдіть, щоб оцінювати коментарі', 'warning');
    return;
  }

  try {
    await apiClient.getCsrfCookie();
    const { data } = await apiClient.post(`/comments/${commentId}/reaction`, { reaction });

    const item = comments.value.find(c => c.id === commentId);
    if (item && data?.data) {
      item.likes_count = data.data.likes_count;
      item.dislikes_count = data.data.dislikes_count;
      item.helpfulness_score = data.data.helpfulness_score;
    }
  } catch {
    showSnack('Не вдалося зберегти реакцію', 'error');
  }
}

function openReport(item) {
  if (!authStore.isAuthenticated) {
    authStore.openLoginDialog?.();
    showSnack('Увійдіть, щоб надіслати скаргу', 'warning');
    return;
  }
  reportDialog.commentId = item.id;
  reportDialog.reason = '';
  reportDialog.open = true;
}

function closeReportDialog() {
  reportDialog.open = false;
  reportDialog.commentId = null;
  reportDialog.reason = '';
}

async function submitReport() {
  try {
    await apiClient.getCsrfCookie();
    await apiClient.post(`/comments/${reportDialog.commentId}/report`, {
      reason: reportDialog.reason,
    });
    showSnack('Скаргу надіслано', 'success');
    closeReportDialog();
  } catch (e) {
    const msg = e?.response?.data?.message || 'Не вдалося надіслати скаргу';
    showSnack(msg, 'error');
  }
}

function reload() {
  loadComments();
}

watch(activeType, () => {
  buildSortOptions();
  filters.page = 1;
  filters.rating = null;
  loadComments();
});

watch(() => props.productId, () => {
  filters.page = 1;
  loadComments();
});

onMounted(() => {
  buildSortOptions();
  loadComments();
});
</script>

<style scoped>
.comments-wrap {
  width: 100%;
}
</style>