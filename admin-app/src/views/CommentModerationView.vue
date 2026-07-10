<template>
  <v-container fluid class="py-4">
    <div class="d-flex align-center justify-space-between mb-4">
      <h1 class="text-h5 font-weight-bold">Модерація коментарів</h1>
      <v-btn color="primary" variant="outlined" :loading="isTabLoading" @click="reloadCurrentTab">
        Оновити
      </v-btn>
    </div>

    <v-tabs v-model="activeTab" class="mb-4">
      <v-tab value="comments">Коментарі</v-tab>
      <v-tab value="reports">Скарги</v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- COMMENTS TAB -->
      <v-window-item value="comments">
        <v-card class="mb-4" elevation="1">
          <v-card-title class="text-subtitle-1">Фільтри коментарів</v-card-title>
          <v-card-text>
            <v-row>
              <v-col cols="12" md="3">
                <v-select
                  v-model="localCommentsFilters.status"
                  :items="commentStatusOptions"
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
                  v-model="localCommentsFilters.type"
                  :items="commentTypeOptions"
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
                  v-model="localCommentsFilters.file_filter"
                  :items="fileFilterOptions"
                  label="Файл"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                />
              </v-col>

              <v-col cols="12" md="3">
                <v-select
                  v-model="localCommentsFilters.per_page"
                  :items="[10, 20, 50, 100]"
                  label="На сторінку"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                />
              </v-col>
            </v-row>

            <v-row class="mt-2">
              <v-col cols="12" class="d-flex ga-2">
                <v-btn variant="text" @click="resetCommentsFilters">Скинути</v-btn>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-alert v-if="commentsError" type="error" variant="tonal" class="mb-4">
          {{ commentsError }}
        </v-alert>

        <v-card>
          <v-list lines="two" class="py-0">
            <div class="comments-head px-4 py-2">
              <div>ID</div>
              <div>Товар</div>
              <div>Автор</div>
              <div>Тип</div>
              <div>Рейтинг</div>
              <div>Відповідь</div>
              <div>Статус</div>
              <div>Дата</div>
              <div>Дії</div>
            </div>

            <template v-if="commentsLoading">
              <div class="py-6 text-center">
                <v-progress-circular indeterminate color="primary" />
              </div>
            </template>

            <template v-else-if="!filteredComments.length">
              <v-list-item>
                <v-list-item-title class="text-medium-emphasis">
                  Немає коментарів за обраними фільтрами
                </v-list-item-title>
              </v-list-item>
            </template>

            <template v-else>
              <TransitionGroup name="list-move" tag="div">
                <div v-for="comment in filteredComments" :key="comment.id">
                  <v-list-item class="comment-row" @click="toggleComment(comment)">
                    <v-list-item-title>
                      <div class="comments-grid">
                        <div>#{{ comment.id }}</div>

                        <div>
                          <a
                            class="product-link"
                            :href="getProductUrl(comment)"
                            target="_blank"
                            rel="noopener noreferrer"
                            @click.stop
                          >
                            {{ shortProductTitle(comment, 6) }}
                          </a>
                        </div>

                        <div class="text-truncate author-col">{{ shortAuthorName(comment, 18) }}</div>

                        <div>
                          <v-chip size="small" variant="tonal" :color="commentTypeColor(comment.type)">
                            {{ typeLabel(comment.type) }}
                          </v-chip>
                        </div>

                        <div>
                          <v-rating
                            v-if="comment.type === 'review'"
                            :model-value="comment.rating || 0"
                            density="compact"
                            size="16"
                            color="amber"
                            readonly
                          />
                          <span v-else>—</span>
                        </div>

                        <div class="text-truncate">{{ shortText(firstAnswerBody(comment), 30) }}</div>

                        <div>
                          <v-chip size="small" variant="tonal" :color="moderationStatusColor(comment.moderation_status)">
                            {{ moderationStatusLabel(comment.moderation_status) }}
                          </v-chip>
                        </div>

                        <div class="date-2row">
                          <div>{{ formatTime(comment.created_at) }}</div>
                          <div class="date-2row__date">{{ formatDateOnly(comment.created_at) }}</div>
                        </div>

                        <div class="d-flex align-center ga-1" @click.stop>
                          <v-tooltip text="Схвалити" location="top">
                            <template #activator="{ props }">
                              <v-btn
                                v-bind="props"
                                icon
                                size="small"
                                color="success"
                                variant="text"
                                :loading="actionLoading && processingCommentId === comment.id && processingAction === 'approve'"
                                :disabled="comment.moderation_status === 'approved' || actionLoading"
                                @click="onApprove(comment)"
                              >
                                <v-icon>mdi-check-circle-outline</v-icon>
                              </v-btn>
                            </template>
                          </v-tooltip>

                          <v-tooltip text="Відхилити" location="top">
                            <template #activator="{ props }">
                              <v-btn
                                v-bind="props"
                                icon
                                size="small"
                                color="warning"
                                variant="text"
                                :disabled="actionLoading"
                                @click="openRejectDialog(comment)"
                              >
                                <v-icon>mdi-close-circle-outline</v-icon>
                              </v-btn>
                            </template>
                          </v-tooltip>

                          <v-tooltip text="Видалити" location="top">
                            <template #activator="{ props }">
                              <v-btn
                                v-bind="props"
                                icon
                                size="small"
                                color="error"
                                variant="text"
                                :loading="actionLoading && processingCommentId === comment.id && processingAction === 'delete'"
                                :disabled="actionLoading"
                                @click="onDelete(comment)"
                              >
                                <v-icon>mdi-delete-outline</v-icon>
                              </v-btn>
                            </template>
                          </v-tooltip>
                        </div>
                      </div>
                    </v-list-item-title>
                  </v-list-item>

                  <v-expand-transition>
                    <div v-if="openedCommentId === comment.id" class="comment-expand pa-3">
                      <v-sheet rounded border class="expand-shell pa-4">
                        <div class="detail-col">
                          <div class="detail-block section-gap">
                            <div class="detail-label">Текст повідомлення</div>
                            <div class="detail-message">{{ comment.body || '—' }}</div>
                          </div>

                          <!-- Файлы самого комментария -->
                          <div class="detail-block section-gap mt-2">
                            <div class="detail-label">Файли коментаря</div>

                            <div v-if="!comment.media?.length" class="detail-muted">Немає файлів</div>

                            <div v-else class="d-flex flex-wrap ga-2">
                              <template v-for="m in comment.media" :key="m.id">
                                <v-img
                                  v-if="m.type === 'image'"
                                  :src="m.url"
                                  width="140"
                                  height="100"
                                  cover
                                  class="rounded media-thumb"
                                  @click.stop="openImagePreview(m.url)"
                                />

                                <div v-else-if="m.type === 'youtube'" class="video-url-box">
                                  <div class="video-url-label">YouTube URL:</div>
                                  <div class="video-url-text">{{ m.url || m.external_url }}</div>
                                  <v-btn
                                    size="small"
                                    color="red-darken-1"
                                    variant="tonal"
                                    @click.stop="openVideoWithConfirm(m.url || m.external_url)"
                                  >
                                    Відкрити відео
                                  </v-btn>
                                </div>
                              </template>
                            </div>
                          </div>

                          <div class="detail-block section-gap mt-2">
                            <div class="detail-label">Відповіді</div>

                            <template v-if="comment.answers?.length">
                              <v-sheet
                                v-for="ans in comment.answers"
                                :key="ans.id"
                                rounded
                                border
                                class="answer-card pa-3 mb-3"
                              >
                                <div class="d-flex justify-space-between align-center mb-2">
                                  <div class="text-caption answer-head d-flex align-center">
                                    <v-icon size="16" class="mr-1" color="info">mdi-information</v-icon>
                                    {{ ans.answer_origin === 'administration' ? 'Адміністрація' : 'Продавець' }}
                                    • {{ formatDate(ans.created_at) }}
                                  </div>

                                  <div class="d-flex align-center ga-1">
                                    <v-tooltip text="Редагувати відповідь" location="top">
                                      <template #activator="{ props }">
                                        <v-btn
                                          v-bind="props"
                                          icon
                                          size="small"
                                          color="primary"
                                          variant="text"
                                          :disabled="actionLoading"
                                          @click.stop="openEditAnswerDialog(comment, ans)"
                                        >
                                          <v-icon>mdi-pencil-outline</v-icon>
                                        </v-btn>
                                      </template>
                                    </v-tooltip>

                                    <v-tooltip text="Видалити відповідь" location="top">
                                      <template #activator="{ props }">
                                        <v-btn
                                          v-bind="props"
                                          icon
                                          size="small"
                                          color="error"
                                          variant="text"
                                          :loading="actionLoading && processingCommentId === ans.id && processingAction === 'delete-answer'"
                                          :disabled="actionLoading"
                                          @click.stop="openDeleteAnswerDialog(comment, ans)"
                                        >
                                          <v-icon>mdi-delete-outline</v-icon>
                                        </v-btn>
                                      </template>
                                    </v-tooltip>
                                  </div>
                                </div>

                                <div class="detail-message mb-3">{{ ans.body || '—' }}</div>

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

                                      <div v-else-if="m.type === 'youtube'" class="video-url-box">
                                        <div class="video-url-label">YouTube URL:</div>
                                        <div class="video-url-text">{{ m.url || m.external_url }}</div>
                                        <v-btn
                                          size="small"
                                          color="red-darken-1"
                                          variant="tonal"
                                          @click.stop="openVideoWithConfirm(m.url || m.external_url)"
                                        >
                                          Відкрити відео
                                        </v-btn>
                                      </div>
                                    </template>
                                  </div>
                                </div>
                              </v-sheet>
                            </template>

                            <div v-else class="detail-muted">Відповідей немає</div>
                          </div>
                        </div>
                      </v-sheet>
                    </div>
                  </v-expand-transition>
                </div>
              </TransitionGroup>
            </template>
          </v-list>

          <div class="d-flex justify-center py-4">
            <v-pagination
              v-model="localCommentsFilters.page"
              :length="commentsPagination.last_page || 1"
              total-visible="7"
              @update:model-value="onCommentsPageChange"
            />
          </div>
        </v-card>
      </v-window-item>

      <!-- REPORTS TAB -->
      <v-window-item value="reports">
        <v-card class="mb-4" elevation="1">
          <v-card-title class="text-subtitle-1">Фільтри скарг</v-card-title>
          <v-card-text>
            <v-row>
              <v-col cols="12" md="3">
                <v-select
                  v-model="localReportsFilters.status"
                  :items="reportStatusOptions"
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
                  v-model="localReportsFilters.per_page"
                  :items="[10, 20, 50, 100]"
                  label="На сторінку"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                />
              </v-col>

              <v-col cols="12" md="6" class="d-flex align-center ga-2">
                <v-btn color="primary" @click="applyReportsFilters">Застосувати</v-btn>
                <v-btn variant="text" @click="resetReportsFilters">Скинути</v-btn>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-alert v-if="reportsError" type="error" variant="tonal" class="mb-4">
          {{ reportsError }}
        </v-alert>

        <v-card elevation="1">
          <v-table fixed-header height="620">
            <thead>
              <tr>
                <th class="text-left">ID</th>
                <th class="text-left">Comment ID</th>
                <th class="text-left">Відправник</th>
                <th class="text-left">Причина</th>
                <th class="text-left">Статус</th>
                <th class="text-left">Дата</th>
                <th class="text-left">Дії</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="reportsLoading">
                <td colspan="7" class="text-center py-6">
                  <v-progress-circular indeterminate color="primary" />
                </td>
              </tr>

              <tr v-else-if="!reports.length">
                <td colspan="7" class="text-center py-6 text-medium-emphasis">
                  Немає скарг за обраними фільтрами
                </td>
              </tr>

              <tr v-for="report in reports" :key="report.id">
                <td>#{{ report.id }}</td>
                <td>#{{ report.comment_id }}</td>
                <td>{{ report.reporter?.name || `User #${report.reporter_id}` }}</td>
                <td style="max-width: 420px;">
                  <div class="comment-body text-body-2">{{ report.reason }}</div>
                  <div v-if="report.resolution_note" class="text-caption mt-1">
                    <strong>Нотатка модератора:</strong> {{ report.resolution_note }}
                  </div>
                </td>
                <td>
                  <v-chip size="small" variant="tonal" :color="reportStatusColor(report.status)">
                    {{ reportStatusLabel(report.status) }}
                  </v-chip>
                </td>
                <td>{{ formatDate(report.created_at) }}</td>
                <td>
                  <div class="d-flex flex-wrap ga-2">
                    <v-btn
                      size="small"
                      color="success"
                      variant="tonal"
                      :disabled="report.status !== 'pending' || actionLoading"
                      :loading="actionLoading && processingReportId === report.id && processingAction === 'resolve'"
                      @click="openResolveDialog(report, 'resolved')"
                    >
                      Опрацювати
                    </v-btn>

                    <v-btn
                      size="small"
                      color="warning"
                      variant="tonal"
                      :disabled="report.status !== 'pending' || actionLoading"
                      :loading="actionLoading && processingReportId === report.id && processingAction === 'reject-report'"
                      @click="openResolveDialog(report, 'rejected')"
                    >
                      Відхилити
                    </v-btn>
                  </div>
                </td>
              </tr>
            </tbody>
          </v-table>

          <div class="d-flex justify-center py-4">
            <v-pagination
              v-model="localReportsFilters.page"
              :length="reportsPagination.last_page || 1"
              total-visible="7"
              @update:model-value="onReportsPageChange"
            />
          </div>
        </v-card>
      </v-window-item>
    </v-window>

    <!-- reject dialog -->
    <v-dialog v-model="rejectDialog.open" max-width="560">
      <v-card>
        <v-card-title>Відхилити коментар</v-card-title>
        <v-card-text>
          <v-textarea
            v-model="rejectDialog.reason"
            label="Причина відхилення"
            rows="4"
            variant="outlined"
            auto-grow
            maxlength="5000"
            counter
            placeholder="Вкажіть причину, яка буде показана автору"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeRejectDialog">Скасувати</v-btn>
          <v-btn color="warning" @click="confirmReject">Підтвердити</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- resolve dialog -->
    <v-dialog v-model="resolveDialog.open" max-width="560">
      <v-card>
        <v-card-title>
          {{ resolveDialog.status === 'resolved' ? 'Підтвердити обробку скарги' : 'Відхилити скаргу' }}
        </v-card-title>
        <v-card-text>
          <v-textarea
            v-model="resolveDialog.note"
            label="Нотатка модератора (необовʼязково)"
            rows="4"
            variant="outlined"
            auto-grow
            maxlength="5000"
            counter
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeResolveDialog">Скасувати</v-btn>
          <v-btn color="primary" @click="confirmResolveReport">Підтвердити</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- video confirm dialog -->
    <v-dialog v-model="videoConfirmDialog.open" max-width="520">
      <v-card>
        <v-card-title>Підтвердження відкриття відео</v-card-title>
        <v-card-text>
          Ви точно хочете відкрити відео?
          <div class="video-url-text mt-2">{{ videoConfirmDialog.url }}</div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeVideoConfirmDialog">Скасувати</v-btn>
          <v-btn color="red-darken-1" @click="confirmOpenVideo">Відкрити</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- EDIT ANSWER -->
    <v-dialog v-model="editAnswerDialog.open" max-width="980">
      <v-card>
        <v-card-title>Редагувати відповідь</v-card-title>
        <v-card-text>
          <v-textarea
            v-model="editAnswerDialog.body"
            label="Текст відповіді"
            rows="5"
            variant="outlined"
            auto-grow
            maxlength="5000"
            counter
            class="mb-4"
          />

          <v-divider class="my-3" />

          <div class="text-subtitle-1 mb-2">Зображення (до 5)</div>
          <CommentImageUploadGrid
            v-model="editAnswerDialog.existingImages"
            v-model:newFiles="editAnswerDialog.newFiles"
            :max-files="5"
            @reorder="onEditGridReorder"
          />

          <v-divider class="my-3" />

          <div class="text-subtitle-1 mb-2">YouTube</div>
          <div class="d-flex ga-2">
            <v-text-field
              v-model="editAnswerDialog.youtubeInput"
              label="YouTube URL"
              variant="outlined"
              hide-details
            />
            <v-btn color="primary" variant="tonal" @click="addYoutubeToEdit">
              ДОДАТИ
            </v-btn>
          </div>

          <div class="mt-2" v-if="editAnswerDialog.youtubeUrls.length">
            <v-chip
              v-for="(yt, i) in editAnswerDialog.youtubeUrls"
              :key="`yt-${i}`"
              closable
              color="red-darken-1"
              class="mr-2 mb-2"
              @click:close="removeYoutubeAt(i)"
            >
              {{ yt }}
            </v-chip>
          </div>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeEditAnswerDialog">Скасувати</v-btn>
          <v-btn
            color="primary"
            :loading="actionLoading && processingAction === 'edit-answer' && processingCommentId === editAnswerDialog.answer?.id"
            @click="confirmEditAnswer"
          >
            Зберегти
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- DELETE ANSWER -->
    <v-dialog v-model="deleteAnswerDialog.open" max-width="520">
      <v-card>
        <v-card-title>Видалити відповідь?</v-card-title>
        <v-card-text>
          Цю дію неможливо скасувати.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeDeleteAnswerDialog">Скасувати</v-btn>
          <v-btn
            color="error"
            :loading="actionLoading && processingAction === 'delete-answer' && processingCommentId === deleteAnswerDialog.answer?.id"
            @click="confirmDeleteAnswer"
          >
            Видалити
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- preview dialog -->
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

    <!-- snackbar -->
    <v-snackbar v-model="snackbar.open" :color="snackbar.color" :timeout="3500">
      {{ snackbar.text }}
      <template #actions>
        <v-btn variant="text" @click="snackbar.open = false">OK</v-btn>
      </template>
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useProductCommentModerationStore } from '@/stores/productCommentModerationStore';
import CommentImageUploadGrid from '@/components/CommentImageUploadGrid.vue';

const moderationStore = useProductCommentModerationStore();
const {
  comments,
  commentsLoading,
  commentsError,
  commentsPagination,
  commentsFilters,

  reports,
  reportsLoading,
  reportsError,
  reportsPagination,
  reportsFilters,

  actionLoading,
} = storeToRefs(moderationStore);

const activeTab = ref('comments');
const openedCommentId = ref(null);
let commentsFilterWatchReady = false;

const imagePreview = reactive({ open: false, url: '' });
const videoConfirmDialog = reactive({ open: false, url: '' });

const PUBLIC_BASE = (import.meta.env.VITE_PUBLIC_SITE_URL || '').replace(/\/$/, '');

const localCommentsFilters = reactive({
  status: 'pending',
  type: null,
  file_filter: 'all',
  page: 1,
  per_page: 20,
});
const localReportsFilters = reactive({
  status: 'pending',
  page: 1,
  per_page: 20,
});

const processingAction = ref(null);
const processingCommentId = ref(null);
const processingReportId = ref(null);

const rejectDialog = reactive({ open: false, comment: null, reason: '' });
const resolveDialog = reactive({ open: false, report: null, status: 'resolved', note: '' });

const editAnswerDialog = reactive({
  open: false,
  parentCommentId: null,
  answer: null,
  body: '',
  existingImages: [], // [{id,url}]
  newFiles: [],       // File[]
  reorderMeta: [],    // reorder data from grid
  youtubeUrls: [],
  youtubeInput: '',
});

const deleteAnswerDialog = reactive({ open: false, parentCommentId: null, answer: null });
const snackbar = reactive({ open: false, text: '', color: 'info' });

const commentStatusOptions = [
  { title: 'Усі', value: null },
  { title: 'На модерації', value: 'pending' },
  { title: 'Схвалено', value: 'approved' },
  { title: 'Відхилено', value: 'rejected' },
];
const commentTypeOptions = [
  { title: 'Усі', value: null },
  { title: 'Відгук', value: 'review' },
  { title: 'Питання', value: 'question' },
];
const fileFilterOptions = [
  { title: 'Усі', value: 'all' },
  { title: 'З фото', value: 'with_photo' },
  { title: 'З відео', value: 'with_video' },
  { title: 'З відповіддю', value: 'with_answer' },
];
const reportStatusOptions = [
  { title: 'Відкрита', value: 'pending' },
  { title: 'Опрацьована', value: 'resolved' },
  { title: 'Відхилена', value: 'rejected' },
];

const isTabLoading = computed(() => activeTab.value === 'comments' ? commentsLoading.value : reportsLoading.value);

const filteredComments = computed(() => {
  const list = Array.isArray(comments.value) ? comments.value : [];
  const mode = localCommentsFilters.file_filter || 'all';

  if (mode === 'all') return list;

  if (mode === 'with_photo') {
    return list.filter((c) => {
      const ownHas = (Array.isArray(c.media) ? c.media : []).some((m) => m.type === 'image');
      const answerHas = (Array.isArray(c.answers) ? c.answers : []).some((a) =>
        (Array.isArray(a.media) ? a.media : []).some((m) => m.type === 'image')
      );
      return ownHas || answerHas;
    });
  }

  if (mode === 'with_video') {
    return list.filter((c) => {
      const ownHas = (Array.isArray(c.media) ? c.media : []).some((m) => m.type === 'youtube');
      const answerHas = (Array.isArray(c.answers) ? c.answers : []).some((a) =>
        (Array.isArray(a.media) ? a.media : []).some((m) => m.type === 'youtube')
      );
      return ownHas || answerHas;
    });
  }

  if (mode === 'with_answer') {
    return list.filter((c) => Array.isArray(c.answers) && c.answers.length > 0);
  }

  return list;
});

watch(
  () => [
    localCommentsFilters.status,
    localCommentsFilters.type,
    localCommentsFilters.file_filter,
    localCommentsFilters.per_page,
    localCommentsFilters.page,
  ],
  async () => {
    if (!commentsFilterWatchReady) return;

    moderationStore.setCommentsFilters({
      status: localCommentsFilters.status,
      type: localCommentsFilters.type,
      page: localCommentsFilters.page,
      per_page: localCommentsFilters.per_page,
    });

    await loadComments();
  }
);

function shortText(text, max = 30) {
  if (!text) return '—';
  return text.length > max ? `${text.slice(0, max)}…` : text;
}
function shortAuthorName(comment, max = 18) {
  const name = comment?.author?.name || `User #${comment?.author_id ?? '—'}`;
  return shortText(name, max);
}
function firstAnswerBody(comment) {
  return comment?.answers?.[0]?.body || '—';
}
function shortProductTitle(comment, max = 26) {
  const title = comment?.product?.title;
  if (!title) return '—';
  return shortText(title, max);
}
function getProductUrl(comment) {
  const id = comment?.product?.id || comment?.product_id;
  const slug = comment?.product?.slug;
  if (!id) return '#';
  return slug ? `${PUBLIC_BASE}/product/${id}-${slug}` : `${PUBLIC_BASE}/product/${id}`;
}
function toggleComment(comment) {
  openedCommentId.value = openedCommentId.value === comment.id ? null : comment.id;
}
function showSnackbar(text, color = 'info') {
  snackbar.text = text;
  snackbar.color = color;
  snackbar.open = true;
}
function formatDate(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString('uk-UA');
}
function formatTime(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('uk-UA', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(new Date(value));
}
function formatDateOnly(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('uk-UA', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(value));
}
function typeLabel(type) {
  if (type === 'review') return 'Відгук';
  if (type === 'question') return 'Питання';
  if (type === 'answer') return 'Відповідь';
  return type || '—';
}
function moderationStatusLabel(status) {
  if (status === 'pending') return 'На модерації';
  if (status === 'approved') return 'Схвалено';
  if (status === 'rejected') return 'Відхилено';
  return status || '—';
}
function reportStatusLabel(status) {
  if (status === 'pending') return 'Відкрита';
  if (status === 'resolved') return 'Опрацьована';
  if (status === 'rejected') return 'Відхилена';
  return status || '—';
}
function moderationStatusColor(status) {
  if (status === 'approved') return 'success';
  if (status === 'rejected') return 'warning';
  return 'info';
}
function reportStatusColor(status) {
  if (status === 'resolved') return 'success';
  if (status === 'rejected') return 'warning';
  return 'info';
}
function commentTypeColor(type) {
  if (type === 'review') return 'primary';
  if (type === 'question') return 'deep-purple';
  return 'grey';
}
function openImagePreview(url) {
  if (!url) return;
  imagePreview.url = url;
  imagePreview.open = true;
}
function closeImagePreview() {
  imagePreview.open = false;
  imagePreview.url = '';
}

function openVideoWithConfirm(url) {
  if (!url) return;
  videoConfirmDialog.url = url;
  videoConfirmDialog.open = true;
}
function closeVideoConfirmDialog() {
  videoConfirmDialog.open = false;
  videoConfirmDialog.url = '';
}
function confirmOpenVideo() {
  if (!videoConfirmDialog.url) return;
  window.open(videoConfirmDialog.url, '_blank', 'noopener,noreferrer');
  closeVideoConfirmDialog();
}

function onEditGridReorder(meta) {
  editAnswerDialog.reorderMeta = Array.isArray(meta) ? meta : [];
}

function addYoutubeToEdit() {
  const val = (editAnswerDialog.youtubeInput || '').trim();
  if (!val) return;
  if (editAnswerDialog.youtubeUrls.length >= 1) {
    showSnackbar('Дозволено лише 1 YouTube-посилання', 'warning');
    return;
  }
  editAnswerDialog.youtubeUrls.push(val);
  editAnswerDialog.youtubeInput = '';
}
function removeYoutubeAt(i) {
  editAnswerDialog.youtubeUrls.splice(i, 1);
}

function openEditAnswerDialog(parentComment, ans) {
  editAnswerDialog.parentCommentId = parentComment.id;
  editAnswerDialog.answer = ans;
  editAnswerDialog.body = ans?.body || '';

  const media = Array.isArray(ans?.media) ? ans.media : [];

  editAnswerDialog.existingImages = media
    .filter((m) => m.type === 'image')
    .map((m) => ({ id: m.id, url: m.url }));

  editAnswerDialog.newFiles = [];
  editAnswerDialog.reorderMeta = [];

  editAnswerDialog.youtubeUrls = media
    .filter((m) => m.type === 'youtube')
    .map((m) => m.external_url || m.url)
    .filter(Boolean)
    .slice(0, 1);

  editAnswerDialog.youtubeInput = '';
  editAnswerDialog.open = true;
}

function closeEditAnswerDialog() {
  editAnswerDialog.open = false;
  editAnswerDialog.parentCommentId = null;
  editAnswerDialog.answer = null;
  editAnswerDialog.body = '';
  editAnswerDialog.existingImages = [];
  editAnswerDialog.newFiles = [];
  editAnswerDialog.reorderMeta = [];
  editAnswerDialog.youtubeUrls = [];
  editAnswerDialog.youtubeInput = '';
}

function buildMediaSyncFromDialog() {
  const sync = [];
  let sort = 0;

  const hasReorder = Array.isArray(editAnswerDialog.reorderMeta) && editAnswerDialog.reorderMeta.length > 0;

  if (hasReorder) {
    for (const item of editAnswerDialog.reorderMeta) {
      if (item.type === 'existing') {
        const img = editAnswerDialog.existingImages.find((x) => x.id === item.id);
        if (img) {
          sync.push({
            id: img.id,
            type: 'image',
            url: img.url || null,
            external_url: null,
            sort_order: sort++,
          });
        }
      }
    }
  } else {
    for (const img of editAnswerDialog.existingImages) {
      sync.push({
        id: img.id,
        type: 'image',
        url: img.url || null,
        external_url: null,
        sort_order: sort++,
      });
    }
  }

  if (editAnswerDialog.youtubeUrls.length) {
    const yt = editAnswerDialog.youtubeUrls[0];
    sync.push({
      id: null,
      type: 'youtube',
      url: yt,
      external_url: yt,
      sort_order: sort++,
    });
  }

  return sync;
}

async function confirmEditAnswer() {
  if (!editAnswerDialog.answer) return;
  const keepOpenId = editAnswerDialog.parentCommentId;

  try {
    processingAction.value = 'edit-answer';
    processingCommentId.value = editAnswerDialog.answer.id;

    const payload = {
      body: editAnswerDialog.body,
      media_sync: buildMediaSyncFromDialog(),
      new_images: editAnswerDialog.newFiles || [],
    };

    await moderationStore.updateComment(editAnswerDialog.answer.id, payload);

    showSnackbar('Відповідь оновлено', 'success');
    closeEditAnswerDialog();
    await loadComments();
    openedCommentId.value = keepOpenId;
  } catch {
    showSnackbar('Не вдалося оновити відповідь', 'error');
  } finally {
    processingAction.value = null;
    processingCommentId.value = null;
  }
}

function openDeleteAnswerDialog(parentComment, ans) {
  deleteAnswerDialog.parentCommentId = parentComment.id;
  deleteAnswerDialog.answer = ans;
  deleteAnswerDialog.open = true;
}
function closeDeleteAnswerDialog() {
  deleteAnswerDialog.open = false;
  deleteAnswerDialog.parentCommentId = null;
  deleteAnswerDialog.answer = null;
}
async function confirmDeleteAnswer() {
  if (!deleteAnswerDialog.answer) return;
  const keepOpenId = deleteAnswerDialog.parentCommentId;

  try {
    processingAction.value = 'delete-answer';
    processingCommentId.value = deleteAnswerDialog.answer.id;

    await moderationStore.deleteComment(deleteAnswerDialog.answer.id);

    showSnackbar('Відповідь видалено', 'success');
    closeDeleteAnswerDialog();
    await loadComments();
    openedCommentId.value = keepOpenId;
  } catch {
    showSnackbar('Не вдалося видалити відповідь', 'error');
  } finally {
    processingAction.value = null;
    processingCommentId.value = null;
  }
}

async function onApprove(comment) {
  try {
    processingAction.value = 'approve';
    processingCommentId.value = comment.id;

    await moderationStore.approveComment(comment.id);
    showSnackbar(`Коментар #${comment.id} схвалено`, 'success');
    await loadComments();
    openedCommentId.value = comment.id;
  } catch {
    showSnackbar('Не вдалося схвалити коментар', 'error');
  } finally {
    processingAction.value = null;
    processingCommentId.value = null;
  }
}
function openRejectDialog(comment) {
  rejectDialog.comment = comment;
  rejectDialog.reason = '';
  rejectDialog.open = true;
}
function closeRejectDialog() {
  rejectDialog.open = false;
  rejectDialog.comment = null;
  rejectDialog.reason = '';
}
async function confirmReject() {
  if (!rejectDialog.comment) return;

  const keepOpenId = rejectDialog.comment.id;

  try {
    processingAction.value = 'approve';
    processingCommentId.value = rejectDialog.comment.id;

    await moderationStore.rejectComment(rejectDialog.comment.id, rejectDialog.reason);
    showSnackbar('Коментар відхилено', 'success');
    closeRejectDialog();
    await loadComments();
    openedCommentId.value = keepOpenId;
  } catch {
    showSnackbar('Не вдалося відхилити коментар', 'error');
  } finally {
    processingAction.value = null;
    processingCommentId.value = null;
  }
}

async function onDelete(comment) {
  const ok = window.confirm('Видалити коментар?');
  if (!ok) return;

  try {
    processingAction.value = 'delete';
    processingCommentId.value = comment.id;

    await moderationStore.deleteComment(comment.id);
    showSnackbar('Коментар видалено', 'success');

    if (!comments.value.length && localCommentsFilters.page > 1) {
      localCommentsFilters.page -= 1;
    }
    await loadComments();
  } catch {
    showSnackbar('Не вдалося видалити коментар', 'error');
  } finally {
    processingAction.value = null;
    processingCommentId.value = null;
  }
}

function openResolveDialog(report, status) {
  resolveDialog.report = report;
  resolveDialog.status = status;
  resolveDialog.note = '';
  resolveDialog.open = true;
}
function closeResolveDialog() {
  resolveDialog.open = false;
  resolveDialog.report = null;
  resolveDialog.status = 'resolved';
  resolveDialog.note = '';
}
async function confirmResolveReport() {
  if (!resolveDialog.report) return;

  try {
    processingAction.value = resolveDialog.status === 'resolved' ? 'resolve' : 'reject-report';
    processingReportId.value = resolveDialog.report.id;

    await moderationStore.resolveReport(
      resolveDialog.report.id,
      resolveDialog.status,
      resolveDialog.note || null
    );

    showSnackbar(
      resolveDialog.status === 'resolved' ? 'Скаргу опрацьовано' : 'Скаргу відхилено',
      'success'
    );

    closeResolveDialog();
    await loadReports();
  } catch {
    showSnackbar('Не вдалося опрацювати скаргу', 'error');
  } finally {
    processingAction.value = null;
    processingReportId.value = null;
  }
}

async function loadComments() {
  await moderationStore.fetchComments({
    status: localCommentsFilters.status,
    type: localCommentsFilters.type,
    page: localCommentsFilters.page,
    per_page: localCommentsFilters.per_page,
  });

  const rank = { pending: 0, rejected: 1, approved: 2 };
  comments.value = [...comments.value].sort((a, b) => {
    const ra = rank[a.moderation_status] ?? 9;
    const rb = rank[b.moderation_status] ?? 9;
    if (ra !== rb) return ra - rb;

    const at = a.created_at ? new Date(a.created_at).getTime() : 0;
    const bt = b.created_at ? new Date(b.created_at).getTime() : 0;
    return bt - at;
  });
}
async function loadReports() {
  await moderationStore.fetchReports({ ...localReportsFilters });
}

function syncLocalFiltersFromStore() {
  Object.assign(localCommentsFilters, {
    status: commentsFilters.value?.status ?? 'pending',
    type: commentsFilters.value?.type ?? null,
    file_filter: localCommentsFilters.file_filter || 'all',
    page: commentsFilters.value?.page ?? 1,
    per_page: commentsFilters.value?.per_page ?? 20,
  });

  Object.assign(localReportsFilters, {
    status: reportsFilters.value?.status ?? 'pending',
    page: reportsFilters.value?.page ?? 1,
    per_page: reportsFilters.value?.per_page ?? 20,
  });
}

async function resetCommentsFilters() {
  Object.assign(localCommentsFilters, {
    status: 'pending',
    type: null,
    file_filter: 'all',
    page: 1,
    per_page: 20,
  });
}
async function onCommentsPageChange(page) {
  localCommentsFilters.page = page;
}

async function applyReportsFilters() {
  localReportsFilters.page = 1;
  moderationStore.setReportsFilters({ ...localReportsFilters });
  await loadReports();
}
async function resetReportsFilters() {
  Object.assign(localReportsFilters, {
    status: 'pending',
    page: 1,
    per_page: 20,
  });
  moderationStore.setReportsFilters({ ...localReportsFilters });
  await loadReports();
}
async function onReportsPageChange(page) {
  localReportsFilters.page = page;
  moderationStore.setReportsFilters({ ...localReportsFilters });
  await loadReports();
}
async function reloadCurrentTab() {
  if (activeTab.value === 'comments') await loadComments();
  else await loadReports();
}

watch(activeTab, async (tab) => {
  moderationStore.resetErrors();
  if (tab === 'comments') await loadComments();
  else await loadReports();
});

onMounted(async () => {
  syncLocalFiltersFromStore();
  await loadComments();
  commentsFilterWatchReady = true;
});
</script>

<style scoped>
.comments-head,
.comments-grid {
  display: grid;
  grid-template-columns: 80px 1.2fr 1fr 120px 140px 1.5fr 130px 120px 120px;
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
.comment-row:hover { background: rgba(127, 127, 127, 0.08); }

.comment-expand { border-bottom: 1px solid rgba(127, 127, 127, 0.18); }
.expand-shell { background: rgba(127, 127, 127, 0.04); }

.detail-col { display: flex; flex-direction: column; gap: 6px; }
.section-gap { margin-bottom: 12px; }

.detail-label {
  font-size: 12px;
  line-height: 1.2;
  color: rgba(127, 127, 127, 0.95);
}
.detail-message {
  font-size: 17px;
  line-height: 1.56;
  color: #ffffff;
  font-weight: 500;
  white-space: pre-wrap;
  word-break: break-word;
}
.detail-muted { font-size: 13px; color: rgba(127, 127, 127, 0.95); }

.comment-body {
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.35;
}
.product-link {
  text-decoration: underline;
  font-weight: 600;
}
.media-thumb {
  cursor: zoom-in;
}

.author-col {
  min-width: 0;
  max-width: 100%;
}

.answer-card {
  background: rgba(33, 150, 243, 0.08);
  border-color: rgba(33, 150, 243, 0.35) !important;
}
.answer-head {
  color: rgb(33, 150, 243);
}

.video-url-box {
  min-width: 260px;
  max-width: 520px;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid rgba(244, 67, 54, 0.35);
  background: rgba(244, 67, 54, 0.08);
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.video-url-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.72);
}
.video-url-text {
  font-size: 13px;
  line-height: 1.35;
  word-break: break-all;
  color: #fff;
}

.date-2row {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}
.date-2row__date {
  color: rgba(127, 127, 127, 0.9);
  font-size: 12px;
  margin-top: 2px;
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
</style>