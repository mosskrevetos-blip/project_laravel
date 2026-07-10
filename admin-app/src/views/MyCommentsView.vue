<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-4">
      <h1 class="text-h5">Мої коментарі</h1>
      <v-btn variant="outlined" :loading="loading" @click="loadData">
        Оновити
      </v-btn>
    </div>

    <v-card class="mb-4" elevation="1">
      <v-card-title class="text-subtitle-1">Фільтри</v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" md="3">
            <v-select
              v-model="localFilters.type"
              :items="typeOptions"
              label="Тип"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="comfortable"
              hide-details
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              v-model="localFilters.status"
              :items="statusOptions"
              label="Статус"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="comfortable"
              hide-details
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              v-model="localFilters.per_page"
              :items="[10, 20, 50, 100]"
              label="На сторінку"
              variant="outlined"
              density="comfortable"
              hide-details
            />
          </v-col>

          <v-col cols="12" md="3" class="d-flex align-center ga-2">
            <v-btn color="primary" @click="applyFilters">Застосувати</v-btn>
            <v-btn variant="text" @click="resetFilters">Скинути</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
      {{ error }}
    </v-alert>

    <v-card>
      <v-list lines="two" class="py-0">
        <div class="comments-head px-4 py-2">
          <div>ID</div>
          <div>Товар</div>
          <div>Тип</div>
          <div>Рейтинг</div>
          <div>Текст</div>
          <div>Статус</div>
          <div>Відповідь</div>
          <div>Дата</div>
        </div>

        <template v-if="loading">
          <div class="py-6 text-center">
            <v-progress-circular indeterminate color="primary" />
          </div>
        </template>

        <template v-else-if="!items.length">
          <v-list-item>
            <v-list-item-title class="text-medium-emphasis">Немає коментарів</v-list-item-title>
          </v-list-item>
        </template>

        <template v-else>
          <TransitionGroup name="list-move" tag="div">
            <div v-for="c in items" :key="c.id">
              <v-list-item class="comment-row" @click="toggleComment(c)">
                <v-list-item-title>
                  <div class="comments-grid">
                    <div>#{{ c.id }}</div>

                    <div>
                      <a
                        class="product-link"
                        :href="getProductUrl(c)"
                        target="_blank"
                        rel="noopener noreferrer"
                        @click.stop
                      >
                        {{ shortProductTitle(c) }}
                      </a>
                    </div>

                    <div>
                      <v-chip size="small" variant="tonal" :color="typeColor(c.type)">
                        {{ typeLabel(c.type) }}
                      </v-chip>
                    </div>

                    <div>
                      <v-rating
                        v-if="c.type === 'review'"
                        :model-value="c.rating || 0"
                        density="compact"
                        size="16"
                        color="amber"
                        readonly
                      />
                      <span v-else>—</span>
                    </div>

                    <div class="text-truncate">
                      {{ shortText(c.body, 80) }}
                    </div>

                    <div>
                      <v-chip size="small" variant="tonal" :color="statusColor(c.moderation_status)">
                        {{ statusLabel(c.moderation_status) }}
                      </v-chip>
                    </div>

                    <div>
                      <span v-if="c.answers?.length">Є</span>
                      <span v-else class="text-grey">—</span>
                    </div>

                    <div>{{ formatDate(c.created_at) }}</div>
                  </div>
                </v-list-item-title>
              </v-list-item>

              <v-expand-transition>
                <div v-if="openedCommentId === c.id" class="comment-expand pa-3">
                  <v-sheet rounded border class="expand-shell pa-4">
                    <div class="detail-col">
                      <div class="detail-block section-gap">
                        <div class="detail-label">Товар</div>
                        <div class="detail-value">
                          <a :href="getProductUrl(c)" target="_blank" rel="noopener noreferrer" @click.stop>
                            {{ c.product?.title || `Товар #${c.product_id}` }}
                          </a>
                        </div>
                      </div>

                      <div class="detail-row section-gap">
                        <div class="detail-block">
                          <div class="detail-label">Тип</div>
                          <div class="detail-value">{{ typeLabel(c.type) }}</div>
                        </div>
                        <div class="detail-block">
                          <div class="detail-label">Статус</div>
                          <div class="detail-value">{{ statusLabel(c.moderation_status) }}</div>
                        </div>
                        <div class="detail-block">
                          <div class="detail-label">Рейтинг</div>
                          <div class="detail-value">
                            <v-rating
                              v-if="c.type === 'review'"
                              :model-value="c.rating || 0"
                              density="compact"
                              size="22"
                              color="amber"
                              readonly
                            />
                            <span v-else>—</span>
                          </div>
                        </div>
                      </div>

                      <div class="detail-block section-gap">
                        <div class="detail-label">Текст повідомлення</div>
                        <div class="detail-message">{{ c.body || '—' }}</div>
                      </div>

                      <div v-if="c.pros" class="detail-block section-gap">
                        <div class="detail-label">Переваги</div>
                        <div class="detail-value">{{ c.pros }}</div>
                      </div>

                      <div v-if="c.cons" class="detail-block section-gap">
                        <div class="detail-label">Недоліки</div>
                        <div class="detail-value">{{ c.cons }}</div>
                      </div>

                      <div
                        v-if="c.moderation_status === 'rejected' && c.moderation_reject_reason"
                        class="detail-block section-gap"
                      >
                        <div class="detail-label">Причина відхилення</div>
                        <div class="detail-value text-error">{{ c.moderation_reject_reason }}</div>
                      </div>

                      <!-- Реакции на комментарий -->
                      <div class="detail-block section-gap">
                        <div class="detail-label">Оцінки коментаря</div>
                        <div class="scores-row">
                          <v-tooltip text="Подобається" location="top">
                            <template #activator="{ props }">
                              <div class="score-item" v-bind="props">
                                <v-icon size="24" color="success">mdi-thumb-up</v-icon>
                                <span class="score-count">{{ c.likes_count || 0 }}</span>
                              </div>
                            </template>
                          </v-tooltip>

                          <v-tooltip text="Не подобається" location="top">
                            <template #activator="{ props }">
                              <div class="score-item" v-bind="props">
                                <v-icon size="24" color="error">mdi-thumb-down</v-icon>
                                <span class="score-count">{{ c.dislikes_count || 0 }}</span>
                              </div>
                            </template>
                          </v-tooltip>
                        </div>
                      </div>

                      <div class="detail-row section-gap">
                        <div class="detail-block">
                          <div class="detail-label">Створено</div>
                          <div class="detail-value">{{ formatDate(c.created_at) }}</div>
                        </div>
                        <div class="detail-block">
                          <div class="detail-label">Оновлено</div>
                          <div class="detail-value">{{ formatDate(c.updated_at) }}</div>
                        </div>
                      </div>

                      <!-- Файлы комментария (теперь ДО блока ответов, как просили) -->
                      <div class="detail-block section-gap mt-2">
                        <div class="detail-label">Файли коментаря</div>
                        <div v-if="!c.media?.length" class="detail-muted">Немає файлів</div>

                        <div v-else class="d-flex flex-wrap ga-2">
                          <template v-for="m in c.media" :key="m.id">
                            <v-img
                              v-if="m.type === 'image'"
                              :src="m.url"
                              width="140"
                              height="100"
                              cover
                              class="rounded media-thumb"
                              @click.stop="openImagePreview(m.url)"
                            />
                            <a
                              v-else-if="m.type === 'youtube'"
                              :href="m.url || m.external_url"
                              target="_blank"
                              rel="noopener noreferrer"
                              @click.stop
                            >
                              <v-chip size="small" color="red-darken-1" variant="flat">
                                <v-icon start size="16">mdi-youtube</v-icon>
                                Відкрити відео
                              </v-chip>
                            </a>
                          </template>
                        </div>
                      </div>

                      <!-- Ответ(ы) в самом конце -->
                      <div class="detail-block section-gap mt-2">
                        <div class="detail-label">Відповідь</div>

                        <template v-if="c.answers?.length">
                          <v-sheet
                            v-for="ans in c.answers"
                            :key="ans.id"
                            rounded
                            border
                            class="answer-card pa-3 mb-3"
                          >
                            <div class="text-caption mb-2 answer-head">
                              <v-icon size="16" class="mr-1" color="info">mdi-information</v-icon>
                              {{ ans.answer_origin === 'administration' ? 'Адміністрація' : 'Продавець' }}
                              • {{ formatDate(ans.created_at) }}
                            </div>

                            <div class="detail-message mb-3">{{ ans.body || '—' }}</div>

                            <!-- Медиа ответа -->
                            <div class="mb-3">
                              <div class="detail-label mb-2">Файли відповіді</div>
                              <div v-if="!ans.media?.length" class="detail-muted">Немає файлів</div>

                              <div v-else class="d-flex flex-wrap ga-2">
                                <template v-for="m in ans.media" :key="m.id">
                                  <v-img
                                    v-if="m.type === 'image'"
                                    :src="m.url"
                                    width="140"
                                    height="100"
                                    cover
                                    class="rounded media-thumb"
                                    @click.stop="openImagePreview(m.url)"
                                  />
                                  <a
                                    v-else-if="m.type === 'youtube'"
                                    :href="m.url || m.external_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    @click.stop
                                  >
                                    <v-chip size="small" color="red-darken-1" variant="flat">
                                      <v-icon start size="16">mdi-youtube</v-icon>
                                      Відкрити відео
                                    </v-chip>
                                  </a>
                                </template>
                              </div>
                            </div>

                            <!-- Реакции ответа -->
                            <div>
                              <div class="detail-label mb-1">Оцінки відповіді</div>
                              <div class="scores-row">
                                <v-tooltip text="Подобається" location="top">
                                  <template #activator="{ props }">
                                    <div class="score-item" v-bind="props">
                                      <v-icon size="22" color="success">mdi-thumb-up</v-icon>
                                      <span class="score-count">{{ ans.likes_count || 0 }}</span>
                                    </div>
                                  </template>
                                </v-tooltip>

                                <v-tooltip text="Не подобається" location="top">
                                  <template #activator="{ props }">
                                    <div class="score-item" v-bind="props">
                                      <v-icon size="22" color="error">mdi-thumb-down</v-icon>
                                      <span class="score-count">{{ ans.dislikes_count || 0 }}</span>
                                    </div>
                                  </template>
                                </v-tooltip>
                              </div>
                            </div>
                          </v-sheet>
                        </template>

                        <div v-else class="detail-muted">Відповіді немає</div>
                      </div>
                    </div>
                  </v-sheet>
                </div>
              </v-expand-transition>
            </div>
          </TransitionGroup>
        </template>
      </v-list>
    </v-card>

    <div class="d-flex justify-center mt-4" v-if="pagination.last_page > 1">
      <v-pagination
        v-model="localFilters.page"
        :length="pagination.last_page || 1"
        @update:model-value="onPageChange"
      />
    </div>

    <v-dialog v-model="imagePreview.open" max-width="1100">
      <v-card class="bg-black">
        <v-toolbar density="comfortable" color="black">
          <v-spacer />
          <v-btn icon variant="text" @click="closeImagePreview">
            <v-icon color="white">mdi-close</v-icon>
          </v-btn>
        </v-toolbar>
        <v-card-text class="d-flex justify-center align-center pa-2" style="min-height: 60vh;">
          <v-img v-if="imagePreview.url" :src="imagePreview.url" contain max-height="80vh" class="w-100" />
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useMyCommentsStore } from '@/stores/myCommentsStore';

const myCommentsStore = useMyCommentsStore();
const { items, loading, error, filters, pagination } = storeToRefs(myCommentsStore);

const PUBLIC_BASE = (import.meta.env.VITE_PUBLIC_SITE_URL || '').replace(/\/$/, '');
const openedCommentId = ref(null);
const imagePreview = ref({ open: false, url: '' });

const localFilters = reactive({
  type: null,
  status: null,
  page: 1,
  per_page: 20,
});

const typeOptions = [
  { title: 'Усі', value: null },
  { title: 'Відгук', value: 'review' },
  { title: 'Питання', value: 'question' },
];

const statusOptions = [
  { title: 'Усі', value: null },
  { title: 'На модерації', value: 'pending' },
  { title: 'Схвалено', value: 'approved' },
  { title: 'Відхилено', value: 'rejected' },
];

function syncLocalFromStore() {
  localFilters.type = filters.value?.type ?? null;
  localFilters.status = filters.value?.status ?? null;
  localFilters.page = filters.value?.page ?? 1;
  localFilters.per_page = filters.value?.per_page ?? 20;
}

function toggleComment(c) {
  openedCommentId.value = openedCommentId.value === c.id ? null : c.id;
}

function formatDate(v) {
  if (!v) return '—';
  return new Date(v).toLocaleString('uk-UA');
}

function shortText(text, max = 12) {
  if (!text) return '—';
  return text.length > max ? `${text.slice(0, max)}…` : text;
}

function shortProductTitle(comment) {
  return shortText(comment?.product?.title, 12);
}

function getProductUrl(comment) {
  const id = comment?.product_id;
  const slug = comment?.product?.slug;
  if (!id) return '#';
  return slug ? `${PUBLIC_BASE}/product/${id}-${slug}` : `${PUBLIC_BASE}/product/${id}`;
}

function typeColor(type) {
  if (type === 'review') return 'primary';
  if (type === 'question') return 'deep-purple';
  return 'grey';
}

function statusColor(status) {
  if (status === 'approved') return 'success';
  if (status === 'rejected') return 'warning';
  return 'info';
}

function typeLabel(type) {
  if (type === 'review') return 'Відгук';
  if (type === 'question') return 'Питання';
  if (type === 'answer') return 'Відповідь';
  return '—';
}

function statusLabel(status) {
  if (status === 'pending') return 'На модерації';
  if (status === 'approved') return 'Схвалено';
  if (status === 'rejected') return 'Відхилено';
  return '—';
}

function openImagePreview(url) {
  if (!url) return;
  imagePreview.value = { open: true, url };
}

function closeImagePreview() {
  imagePreview.value = { open: false, url: '' };
}

async function loadData() {
  myCommentsStore.setFilters({ ...localFilters });
  await myCommentsStore.fetchMyComments();
}

async function applyFilters() {
  localFilters.page = 1;
  openedCommentId.value = null;
  await loadData();
}

async function resetFilters() {
  myCommentsStore.resetFilters();
  syncLocalFromStore();
  openedCommentId.value = null;
  await myCommentsStore.fetchMyComments();
}

async function onPageChange(page) {
  localFilters.page = page;
  openedCommentId.value = null;
  await loadData();
}

watch(() => localFilters.per_page, () => {
  localFilters.page = 1;
});

onMounted(async () => {
  syncLocalFromStore();
  await myCommentsStore.fetchMyComments();
});
</script>

<style scoped>
.comments-head,
.comments-grid {
  display: grid;
  grid-template-columns: 90px 1.2fr 140px 150px 1.5fr 150px 120px 170px;
  gap: 10px;
  align-items: center;
}

.comments-head {
  font-size: 12px;
  color: rgba(127, 127, 127, 0.9);
  border-bottom: 1px solid rgba(127, 127, 127, 0.18);
}

.comment-row {
  border-bottom: 1px solid rgba(127, 127, 127, 0.18);
  cursor: pointer;
}
.comment-row:hover {
  background: rgba(127, 127, 127, 0.08);
}

.comment-expand {
  border-bottom: 1px solid rgba(127, 127, 127, 0.18);
}
.expand-shell {
  background: rgba(127, 127, 127, 0.04);
}

.detail-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.section-gap {
  margin-bottom: 12px;
}
.detail-row {
  display: flex;
  flex-wrap: wrap;
  gap: 28px;
}
.detail-block {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.detail-label {
  font-size: 12px;
  line-height: 1.2;
  color: rgba(127, 127, 127, 0.95);
}
.detail-value {
  font-size: 15px;
  line-height: 1.5;
  color: rgba(235, 235, 235, 0.98);
}
.detail-message {
  font-size: 18px;
  line-height: 1.58;
  color: #ffffff;
  font-weight: 500;
  white-space: pre-wrap;
  word-break: break-word;
}
.detail-muted {
  font-size: 13px;
  color: rgba(127, 127, 127, 0.95);
}

.scores-row {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-top: 2px;
}
.score-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: default;
}
.score-count {
  font-size: 18px;
  font-weight: 700;
  color: rgba(245, 245, 245, 0.98);
}

.product-link {
  text-decoration: underline;
  font-weight: 600;
}

.comment-body {
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.35;
}
.media-thumb {
  cursor: zoom-in;
}

.answer-card {
  background: rgba(33, 150, 243, 0.08);
  border-color: rgba(33, 150, 243, 0.35) !important;
}
.answer-head {
  color: rgb(33, 150, 243);
  display: flex;
  align-items: center;
}

.list-move-move {
  transition: transform 420ms cubic-bezier(0.22, 1, 0.36, 1);
}
.list-move-enter-active,
.list-move-leave-active {
  transition: all 260ms ease;
}
.list-move-enter-from,
.list-move-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

@media (max-width: 1400px) {
  .comments-head,
  .comments-grid {
    grid-template-columns: 70px 1fr 120px 130px 1.2fr 130px 100px 150px;
  }
}
</style>