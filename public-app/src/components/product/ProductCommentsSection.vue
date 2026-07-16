<template>
  <div class="comments-wrap mt-8">
    <v-card elevation="1">
      <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-2">
        <div class="text-h6">Відгуки та питання</div>
      </v-card-title>

      <v-divider />

      <v-card-text>
        <v-tabs v-model="activeType" class="mb-4">
          <v-tab value="review">Відгуки</v-tab>
          <v-tab value="question">Питання</v-tab>
        </v-tabs>

        <!-- ✅ общий рейтинг по отзывам -->
        <div v-if="activeType === 'review'" class="mb-3 d-flex align-center ga-2">
          <v-rating
            :model-value="Number(globalAverageRating)"
            length="5"
            density="compact"
            size="20"
            color="amber"
            readonly
            half-increments
          />
          <span class="text-body-2 text-medium-emphasis">
            {{ globalAverageRating.toFixed(1) }} ({{ globalReviewsCount }})
          </span>
        </div>

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
              :disabled="uiBusy"
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
              :disabled="uiBusy"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-checkbox
              v-model="localFilters.with_photos"
              label="Тільки з фото"
              density="compact"
              hide-details
              :disabled="uiBusy"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-checkbox
              v-model="localFilters.verified"
              label="Підтверджена покупка"
              density="compact"
              hide-details
              :disabled="uiBusy"
            />
          </v-col>
        </v-row>

        <div class="mb-4 d-flex ga-2">
          <v-btn color="primary" @click="applyFilters" :disabled="uiBusy">Застосувати</v-btn>
          <v-btn color="primary" variant="outlined" @click="openCreateDialog" :disabled="uiBusy">
            {{ activeType === 'review' ? 'Написати відгук' : 'Поставити питання' }}
          </v-btn>
        </div>

        <div v-if="loading" class="py-6 text-center">
          <v-progress-circular indeterminate color="primary" />
        </div>

        <div v-else-if="!items.length" class="py-6 text-medium-emphasis text-center">
          Поки немає записів.
        </div>

        <div v-else class="d-flex flex-column ga-4">
          <v-card v-for="item in items" :key="item.id" variant="outlined" class="root-comment-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-start ga-4">
                <div>
                  <div class="text-subtitle-2">{{ item.author?.name || `User #${item.author_id}` }}</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ formatDate(item.created_at) }}
                    <span v-if="item.is_verified_purchase"> • ✅ Підтверджена покупка</span>
                    <span v-if="item.moderation_status && item.moderation_status !== 'approved'">
                      • ⏳ На модерації
                    </span>
                  </div>
                </div>

                <div v-if="item.type === 'review'" class="d-flex align-center ga-2">
                  <v-rating
                    :model-value="Number(item.rating || 0)"
                    length="5"
                    density="compact"
                    size="18"
                    color="amber"
                    readonly
                    :half-increments="false"
                  />
                </div>
              </div>

              <div v-if="item.body" class="mt-3 body">{{ item.body }}</div>
              <div v-if="item.pros" class="mt-2 text-body-2"><strong>Переваги:</strong> {{ item.pros }}</div>
              <div v-if="item.cons" class="mt-1 text-body-2"><strong>Недоліки:</strong> {{ item.cons }}</div>

              <div v-if="item.media?.length" class="mt-3 d-flex flex-wrap ga-2">
                <template v-for="m in item.media" :key="m.id">
                  <v-img
                    v-if="m.type === 'image'"
                    :src="pickImageVariant(m, 'thumb')"
                    width="140"
                    height="90"
                    cover
                    loading="lazy"
                    class="rounded border-sm media-thumb"
                    @click="openImagePreview(m)"
                  />
                  <v-img
                    v-else-if="m.type === 'youtube'"
                    :src="getYoutubeThumb(m.url || m.external_url)"
                    width="160"
                    height="90"
                    cover
                    loading="lazy"
                    class="rounded border-sm media-thumb"
                    @click="openYoutubePreview(m.url || m.external_url)"
                  >
                    <div class="youtube-badge">
                      <v-icon size="16">mdi-play-circle</v-icon>
                      <span class="ml-1">YouTube</span>
                    </div>
                  </v-img>
                </template>
              </div>

              <div class="mt-4 d-flex flex-wrap ga-2">
                <v-btn size="small" variant="tonal" :loading="actionLoading" :disabled="uiBusy" @click="onReact(item.id, 'like')">
                  👍 {{ item.likes_count || 0 }}
                </v-btn>
                <v-btn size="small" variant="tonal" :loading="actionLoading" :disabled="uiBusy" @click="onReact(item.id, 'dislike')">
                  👎 {{ item.dislikes_count || 0 }}
                </v-btn>
                <v-btn
                  size="small"
                  color="warning"
                  variant="text"
                  :disabled="isReportDisabled(item) || uiBusy"
                  @click="openReport(item)"
                >
                  {{ getReportButtonText(item) }}
                </v-btn>

                <v-btn
                  v-if="canReply && canReplyToComment(item)"
                  size="small"
                  color="primary"
                  variant="tonal"
                  :disabled="uiBusy"
                  @click="toggleReply(item.id)"
                >
                  {{ openedReplyId === item.id ? 'Скасувати відповідь' : 'Відповісти' }}
                </v-btn>
              </div>

              <div v-if="canReply && canReplyToComment(item) && openedReplyId === item.id" class="reply-form mt-3">
                <v-textarea
                  v-model="replyDrafts[item.id]"
                  label="Текст відповіді"
                  variant="outlined"
                  rows="2"
                  auto-grow
                  maxlength="5000"
                  counter
                  hide-details="auto"
                  :disabled="uiBusy"
                />

                <div class="mt-3">
                  <div class="text-subtitle-2 mb-2">Зображення (до 5 шт.)</div>
                  <ImageUploadGridLocal
                    v-model="replyImages[item.id]"
                    :max-files="5"
                    @duplicates-skipped="onDuplicatesSkipped"
                  />
                </div>

                <div class="mt-3">
                  <v-text-field
                    v-model="replyYoutube[item.id]"
                    label="YouTube URL (необовʼязково)"
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                    :disabled="uiBusy"
                  />
                </div>

                <div class="d-flex justify-end mt-2">
                  <v-btn
                    color="primary"
                    variant="tonal"
                    :loading="replyLoading && replyingToId === item.id"
                    :disabled="isReplySubmitDisabled(item.id) || uiBusy"
                    @click="submitReply(item)"
                  >
                    Надіслати відповідь
                  </v-btn>
                </div>
              </div>

              <div v-if="item.answers?.length" class="answers-wrap mt-4">
                <div v-for="ans in item.answers" :key="ans.id" class="answer-item">
                  <div class="answer-left-rail"></div>

                  <div class="answer-main">
                    <div class="d-flex justify-space-between align-start ga-3">
                      <div>
                        <div class="d-flex align-center ga-2">
                          <div class="text-subtitle-2">{{ answerAuthorLabel(ans) }}</div>
                        </div>
                        <div class="text-caption text-medium-emphasis mt-1">{{ formatDate(ans.created_at) }}</div>
                      </div>
                    </div>

                    <div v-if="ans.body" class="mt-2 body">{{ ans.body }}</div>

                    <div v-if="ans.media?.length" class="mt-3 d-flex flex-wrap ga-2">
                      <template v-for="m in ans.media" :key="m.id">
                        <v-img
                          v-if="m.type === 'image'"
                          :src="pickImageVariant(m, 'thumb')"
                          width="140"
                          height="90"
                          cover
                          loading="lazy"
                          class="rounded border-sm media-thumb"
                          @click="openImagePreview(m)"
                        />
                        <v-img
                          v-else-if="m.type === 'youtube'"
                          :src="getYoutubeThumb(m.url || m.external_url)"
                          width="160"
                          height="90"
                          cover
                          loading="lazy"
                          class="rounded border-sm media-thumb"
                          @click="openYoutubePreview(m.url || m.external_url)"
                        >
                          <div class="youtube-badge">
                            <v-icon size="16">mdi-play-circle</v-icon>
                            <span class="ml-1">YouTube</span>
                          </div>
                        </v-img>
                      </template>
                    </div>

                    <div class="mt-3 d-flex flex-wrap ga-2">
                      <v-btn size="small" variant="tonal" :loading="actionLoading" :disabled="uiBusy" @click="onReact(ans.id, 'like')">
                        👍 {{ ans.likes_count || 0 }}
                      </v-btn>
                      <v-btn size="small" variant="tonal" :loading="actionLoading" :disabled="uiBusy" @click="onReact(ans.id, 'dislike')">
                        👎 {{ ans.dislikes_count || 0 }}
                      </v-btn>
                    </div>
                  </div>
                </div>
              </div>
            </v-card-text>
          </v-card>
        </div>

        <div class="d-flex justify-center mt-4" v-if="pagination.last_page > 1">
          <v-pagination
            v-model="localFilters.page"
            :length="pagination.last_page"
            @update:model-value="onPageChange"
            :disabled="uiBusy"
          />
        </div>
      </v-card-text>
    </v-card>

    <v-dialog v-model="createDialog.open" max-width="960" scrollable>
      <v-card>
        <v-card-title>
          {{ activeType === 'review' ? 'Залишити відгук' : 'Поставити питання' }}
        </v-card-title>

        <v-card-text>
          <v-row>
            <v-col cols="12" md="6" v-if="activeType === 'review'">
              <div class="text-subtitle-2 mb-2">Рейтинг *</div>
              <v-rating
                v-model="form.rating"
                length="5"
                color="amber"
                empty-icon="mdi-star-outline"
                full-icon="mdi-star"
                hover
                size="32"
                :half-increments="false"
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
                :disabled="uiBusy"
              />
            </v-col>

            <template v-if="activeType === 'review'">
              <v-col cols="12" md="6">
                <v-textarea v-model="form.pros" label="Переваги" variant="outlined" rows="2" auto-grow maxlength="5000" counter :disabled="uiBusy" />
              </v-col>
              <v-col cols="12" md="6">
                <v-textarea v-model="form.cons" label="Недоліки" variant="outlined" rows="2" auto-grow maxlength="5000" counter :disabled="uiBusy" />
              </v-col>
            </template>

            <v-col cols="12">
              <div class="text-subtitle-2 mb-2">Зображення (до 5 шт.)</div>
              <ImageUploadGridLocal
                v-model="form.images"
                :max-files="5"
                @duplicates-skipped="onDuplicatesSkipped"
              />
            </v-col>

            <v-col cols="12">
              <v-text-field
                v-model="form.youtube_url"
                label="YouTube URL (необовʼязково)"
                variant="outlined"
                density="comfortable"
                :disabled="uiBusy"
              />
            </v-col>
          </v-row>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeCreateDialog" :disabled="uiBusy">Скасувати</v-btn>
          <v-btn color="primary" variant="tonal" :disabled="uiBusy" @click="submitComment">
            Надіслати
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

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
            :disabled="uiBusy"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeReportDialog" :disabled="uiBusy">Скасувати</v-btn>
          <v-btn color="warning" variant="tonal" :loading="reportLoading" :disabled="uiBusy" @click="submitReport">
            Надіслати
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="previewDialog.open" max-width="1000">
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
          <span>Попередній перегляд</span>
          <v-btn icon variant="text" @click="closePreview"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-divider />
        <v-card-text>
          <v-img v-if="previewDialog.type === 'image'" :src="previewDialog.src" max-height="75vh" contain />
          <div v-else-if="previewDialog.type === 'youtube'" class="youtube-embed-wrap">
            <iframe :src="previewDialog.embedUrl" width="100%" height="520" frameborder="0" allowfullscreen />
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.open" :color="snackbar.color" :timeout="2500" location="top right">
      {{ snackbar.text }}
    </v-snackbar>

    <UiBlockingOverlay
      :model-value="uiBlocker.isBlocked"
      title="Обробка..."
      :text="uiBlocker.message"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useProductCommentsStore } from '@/stores/productCommentsStore';
import { useAuthStore } from '@/stores/authStore';
import ImageUploadGridLocal from '@/components/common/ImageUploadGridLocal.vue';
import UiBlockingOverlay from '@/components/common/UiBlockingOverlay.vue';
import { useUiBlocker } from '@/composables/useUiBlocker';

const props = defineProps({
  productId: { type: [Number, String], required: true },
  productOwnerId: { type: [Number, String], default: null },
});

const authStore = useAuthStore();
const commentsStore = useProductCommentsStore();
const uiBlocker = useUiBlocker();

const { items, loading, error, pagination, createLoading, actionLoading, reportLoading } = storeToRefs(commentsStore);

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

const createDialog = reactive({ open: false });
const reportDialog = reactive({ open: false, commentId: null, reason: '' });
const previewDialog = reactive({ open: false, type: 'image', src: '', embedUrl: '' });
const snackbar = reactive({ open: false, text: '', color: 'success' });

const openedReplyId = ref(null);
const replyDrafts = reactive({});
const replyImages = reactive({});
const replyYoutube = reactive({});
const replyLoading = ref(false);
const replyingToId = ref(null);

const uiBusy = computed(() => Boolean(
  uiBlocker.isBlocked.value ||
  createLoading.value ||
  actionLoading.value ||
  reportLoading.value ||
  replyLoading.value
));

const canReply = computed(() => {
  const me = authStore.user;
  if (!me) return false;
  if (authStore.hasRole?.('admin')) return true;
  if (props.productOwnerId && Number(me.id) === Number(props.productOwnerId)) return true;

  const first = items.value?.[0];
  const ownerId =
    first?.product?.user_id ??
    first?.product?.seller_id ??
    first?.product_owner_id ??
    null;

  return ownerId ? Number(me.id) === Number(ownerId) : false;
});

// ✅ глобальный рейтинг из store summary
const globalAverageRating = computed(() => commentsStore.averageRating);
const globalReviewsCount = computed(() => commentsStore.reviewsCount);

function canReplyToComment(item) {
  return item?.moderation_status === 'approved';
}

function showSnack(text, color = 'success') {
  snackbar.text = text;
  snackbar.color = color;
  snackbar.open = true;
}

function onDuplicatesSkipped(count) {
  showSnack(`Пропущено дублікатів: ${count}`, 'info');
}

function formatDate(v) {
  if (!v) return '';
  return new Date(v).toLocaleString('uk-UA');
}

function pickImageVariant(media, target = 'thumb') {
  const v = media?.variants || null;
  const fallback = media?.url || null;

  if (!v || typeof v !== 'object') return fallback;

  const get = (size) => v?.[String(size)]?.webp || v?.[String(size)]?.fallback || null;

  if (target === 'thumb') {
    return window.innerWidth <= 768
      ? (get(150) || get(400) || get(800) || fallback)
      : (get(400) || get(800) || get(150) || fallback);
  }

  if (target === 'preview') {
    return get(1200) || get(2000) || get(800) || fallback;
  }

  return fallback;
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

function openCreateDialog() {
  if (!authStore.isAuthenticated) {
    authStore.openLoginDialog?.();
    showSnack(
      activeType.value === 'review'
        ? 'Увійдіть, щоб залишити відгук'
        : 'Увійдіть, щоб поставити питання',
      'warning'
    );
    return;
  }
  createDialog.open = true;
}

function closeCreateDialog() {
  createDialog.open = false;
  resetForm();
}

async function submitComment() {
  if (!authStore.isAuthenticated) {
    authStore.openLoginDialog?.();
    showSnack('Увійдіть, щоб залишити коментар', 'warning');
    return;
  }

  if (activeType.value === 'review' && !form.rating) {
    showSnack('Оберіть рейтинг від 1 до 5', 'warning');
    return;
  }

  const body = (form.body || '').trim();
  const hasImages = Array.isArray(form.images) && form.images.length > 0;
  const hasYoutube = Boolean((form.youtube_url || '').trim());

  if (!body && !hasImages && !hasYoutube) {
    showSnack(
      activeType.value === 'review'
        ? 'Додайте текст, фото або YouTube до відгуку'
        : 'Вкажіть текст питання або додайте фото/YouTube',
      'warning'
    );
    return;
  }

  try {
    await uiBlocker.withBlock(async () => {
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
      closeCreateDialog();
      await loadComments();
    }, 'Надсилаємо коментар та обробляємо файли...');
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
    await uiBlocker.withBlock(async () => {
      await commentsStore.react(commentId, reaction);
    }, 'Зберігаємо реакцію...');
  } catch {
    showSnack(error.value || 'Не вдалося зберегти реакцію', 'error');
  }
}

function hasPendingReport(item) {
  return Boolean(item?.my_pending_report_exists);
}

function isReportDisabled(item) {
  if (!authStore.isAuthenticated) return false;
  return hasPendingReport(item);
}

function getReportButtonText(item) {
  return hasPendingReport(item) ? 'Скарга на розгляді' : 'Поскаржитись';
}

function openReport(item) {
  if (!authStore.isAuthenticated) {
    authStore.openLoginDialog?.();
    showSnack('Увійдіть, щоб надіслати скаргу', 'warning');
    return;
  }

  if (hasPendingReport(item)) {
    showSnack('Ви вже надсилали скаргу на цей коментар. Вона ще на розгляді.', 'info');
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
    await uiBlocker.withBlock(async () => {
      await commentsStore.report(reportDialog.commentId, reportDialog.reason);
      showSnack('Скаргу відправлено', 'success');
      closeReportDialog();
      await loadComments();
    }, 'Надсилаємо скаргу...');
  } catch {
    showSnack(error.value || 'Не вдалося надіслати скаргу', 'error');
  }
}

function toggleReply(commentId) {
  if (uiBusy.value) return;
  openedReplyId.value = openedReplyId.value === commentId ? null : commentId;
  if (!replyImages[commentId]) replyImages[commentId] = [];
  if (typeof replyYoutube[commentId] === 'undefined') replyYoutube[commentId] = '';
}

function isReplySubmitDisabled(commentId) {
  const body = (replyDrafts[commentId] || '').trim();
  const images = replyImages[commentId] || [];
  const youtube = (replyYoutube[commentId] || '').trim();
  return (replyLoading.value && replyingToId.value === commentId) || (!body && !images.length && !youtube);
}

function sanitizeReplyImages(images) {
  if (!Array.isArray(images)) return [];
  return images.filter((file) => file instanceof File);
}

async function submitReply(item) {
  if (!canReplyToComment(item)) {
    showSnack('Відповідати можна лише на схвалені коментарі', 'warning');
    return;
  }

  const body = (replyDrafts[item.id] || '').trim();
  const images = sanitizeReplyImages(replyImages[item.id]);
  const youtube = (replyYoutube[item.id] || '').trim();

  if (!body && !images.length && !youtube) {
    showSnack('Додайте текст, фото або YouTube до відповіді', 'warning');
    return;
  }

  if (!authStore.isAuthenticated) {
    authStore.openLoginDialog?.();
    showSnack('Увійдіть, щоб відповісти', 'warning');
    return;
  }

  replyLoading.value = true;
  replyingToId.value = item.id;

  try {
    await uiBlocker.withBlock(async () => {
      await commentsStore.createAnswer(item.id, {
        body,
        images,
        youtube_url: youtube || null,
      });

      replyDrafts[item.id] = '';
      replyImages[item.id] = [];
      replyYoutube[item.id] = '';
      openedReplyId.value = null;

      showSnack('Відповідь надіслано', 'success');
      await loadComments();
    }, 'Надсилаємо відповідь та обробляємо файли...');
  } catch {
    showSnack(error.value || 'Не вдалося надіслати відповідь', 'error');
  } finally {
    replyLoading.value = false;
    replyingToId.value = null;
  }
}

function answerAuthorLabel(ans) {
  if (ans.answer_origin === 'administration') return 'Адміністрація';
  if (ans.answer_origin === 'seller') return 'Продавець';
  return ans.author?.name || `User #${ans.author_id}`;
}

function openImagePreview(media) {
  if (!media || uiBusy.value) return;
  const url = pickImageVariant(media, 'preview');
  if (!url) return;

  previewDialog.type = 'image';
  previewDialog.src = url;
  previewDialog.embedUrl = '';
  previewDialog.open = true;
}

function closePreview() {
  if (uiBusy.value) return;
  previewDialog.open = false;
  previewDialog.type = 'image';
  previewDialog.src = '';
  previewDialog.embedUrl = '';
}

function getYoutubeVideoId(url) {
  if (!url) return null;
  try {
    const u = new URL(url);
    if (u.hostname.includes('youtu.be')) return u.pathname.replace('/', '') || null;
    if (u.hostname.includes('youtube.com')) {
      const v = u.searchParams.get('v');
      if (v) return v;
      const parts = u.pathname.split('/').filter(Boolean);
      const shortsIndex = parts.indexOf('shorts');
      if (shortsIndex !== -1 && parts[shortsIndex + 1]) return parts[shortsIndex + 1];
      const embedIndex = parts.indexOf('embed');
      if (embedIndex !== -1 && parts[embedIndex + 1]) return parts[embedIndex + 1];
    }
  } catch {
    return null;
  }
  return null;
}

function getYoutubeThumb(url) {
  const id = getYoutubeVideoId(url);
  if (!id) return 'https://via.placeholder.com/320x180?text=YouTube';
  return `https://img.youtube.com/vi/${id}/hqdefault.jpg`;
}

function getYoutubeEmbedUrl(url) {
  const id = getYoutubeVideoId(url);
  if (!id) return '';
  return `https://www.youtube.com/embed/${id}`;
}

function openYoutubePreview(url) {
  if (uiBusy.value) return;
  const embed = getYoutubeEmbedUrl(url);
  if (!embed) {
    showSnack('Некоректне посилання на YouTube', 'warning');
    return;
  }
  previewDialog.type = 'youtube';
  previewDialog.src = '';
  previewDialog.embedUrl = embed;
  previewDialog.open = true;
}

watch(activeType, async () => {
  if (uiBusy.value) return;
  buildSortOptions();
  localFilters.page = 1;
  localFilters.rating = null;
  await loadComments();
});

watch(() => props.productId, async (newId) => {
  if (uiBusy.value) return;
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
.comments-wrap { width: 100%; }
.body { white-space: pre-wrap; word-break: break-word; }

.answers-wrap {
  --answers-indent: 52px;
  --answers-indent-mobile: 22px;
  --answer-rail-width: 4px;
}

.root-comment-card {
  border: 1px solid rgba(var(--v-theme-primary), 0.35) !important;
  background: linear-gradient(
    180deg,
    rgba(var(--v-theme-primary), 0.06) 0%,
    rgba(var(--v-theme-surface), 1) 38%
  );
}

.comment-type-badge {
  display: flex;
  align-items: center;
}

.media-thumb {
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.media-thumb:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

.youtube-badge {
  position: absolute;
  right: 6px;
  bottom: 6px;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  border-radius: 6px;
  padding: 2px 6px;
  font-size: 12px;
  display: flex;
  align-items: center;
}

.youtube-embed-wrap {
  width: 100%;
  aspect-ratio: 16 / 9;
}
.youtube-embed-wrap iframe {
  width: 100%;
  height: 100%;
  border: none;
}

.answers-wrap {
  border-top: 1px dashed rgba(var(--v-theme-primary), 0.45);
  padding-top: 12px;
  margin-top: 14px;
  margin-left: var(--answers-indent);
}

.answers-header {
  display: flex;
  align-items: center;
  color: rgba(var(--v-theme-primary), 1);
}

.answer-item {
  display: grid;
  grid-template-columns: var(--answer-rail-width) 1fr;
  gap: 10px;
  border: 1px solid rgba(var(--v-theme-primary), 0.25);
  border-radius: 12px;
  padding: 10px 12px;
  margin-bottom: 10px;
  background: rgba(var(--v-theme-primary), 0.04);
}

.answer-left-rail {
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.9);
}

.answer-main {
  min-width: 0;
}

.reply-form {
  border: 1px solid rgba(var(--v-theme-primary), 0.35);
  border-radius: 10px;
  padding: 10px;
  background: rgba(var(--v-theme-primary), 0.05);
}

@media (max-width: 768px) {
  .answers-wrap {
    margin-left: var(--answers-indent-mobile);
  }
}
</style>