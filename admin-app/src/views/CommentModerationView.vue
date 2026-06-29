<template>
  <v-container fluid class="py-4">
    <div class="d-flex align-center justify-space-between mb-4">
      <h1 class="text-h5 font-weight-bold">Модерація коментарів</h1>
      <v-btn
        color="primary"
        variant="outlined"
        :loading="isTabLoading"
        @click="reloadCurrentTab"
      >
        Оновити
      </v-btn>
    </div>

    <v-tabs v-model="activeTab" class="mb-4">
      <v-tab value="comments">Коментарі</v-tab>
      <v-tab value="reports">Скарги</v-tab>
    </v-tabs>

    <!-- COMMENTS TAB -->
    <v-window v-model="activeTab">
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
                  v-model="localCommentsFilters.per_page"
                  :items="[10, 20, 50, 100]"
                  label="На сторінку"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                />
              </v-col>

              <v-col cols="12" md="3" class="d-flex align-center ga-2">
                <v-btn color="primary" @click="applyCommentsFilters">Застосувати</v-btn>
                <v-btn variant="text" @click="resetCommentsFilters">Скинути</v-btn>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-alert
          v-if="commentsError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ commentsError }}
        </v-alert>

        <v-card elevation="1">
          <v-table fixed-header height="620">
            <thead>
              <tr>
                <th class="text-left">ID</th>
                <th class="text-left">Товар</th>
                <th class="text-left">Автор</th>
                <th class="text-left">Тип</th>
                <th class="text-left">Рейтинг</th>
                <th class="text-left">Текст</th>
                <th class="text-left">Статус</th>
                <th class="text-left">Дата</th>
                <th class="text-left">Дії</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="commentsLoading">
                <td colspan="9" class="text-center py-6">
                  <v-progress-circular indeterminate color="primary" />
                </td>
              </tr>

              <tr v-else-if="!comments.length">
                <td colspan="9" class="text-center py-6 text-medium-emphasis">
                  Немає коментарів за обраними фільтрами
                </td>
              </tr>

              <tr v-for="comment in comments" :key="comment.id">
                <td>#{{ comment.id }}</td>
                <td>{{ comment.product_id }}</td>
                <td>
                  {{ comment.author?.name || `User #${comment.author_id}` }}
                </td>
                <td>
                  <v-chip size="small" variant="tonal" :color="commentTypeColor(comment.type)">
                    {{ comment.type }}
                  </v-chip>
                </td>
                <td>{{ comment.rating ?? '—' }}</td>
                <td style="max-width: 360px;">
                  <div class="comment-body text-body-2">
                    {{ comment.body || '—' }}
                  </div>
                  <div v-if="comment.pros" class="text-caption mt-1">
                    <strong>Переваги:</strong> {{ comment.pros }}
                  </div>
                  <div v-if="comment.cons" class="text-caption mt-1">
                    <strong>Недоліки:</strong> {{ comment.cons }}
                  </div>
                  <div
                    v-if="comment.moderation_status === 'rejected' && comment.moderation_reject_reason"
                    class="text-caption text-error mt-1"
                  >
                    <strong>Причина відхилення:</strong> {{ comment.moderation_reject_reason }}
                  </div>
                </td>
                <td>
                  <v-chip
                    size="small"
                    variant="tonal"
                    :color="moderationStatusColor(comment.moderation_status)"
                  >
                    {{ comment.moderation_status }}
                  </v-chip>
                </td>
                <td>{{ formatDate(comment.created_at) }}</td>
                <td>
                  <div class="d-flex flex-wrap ga-2">
                    <v-btn
                      size="small"
                      color="success"
                      variant="tonal"
                      :loading="actionLoading && processingCommentId === comment.id && processingAction === 'approve'"
                      :disabled="comment.moderation_status === 'approved' || actionLoading"
                      @click="onApprove(comment)"
                    >
                      Approve
                    </v-btn>

                    <v-btn
                      size="small"
                      color="warning"
                      variant="tonal"
                      :disabled="actionLoading"
                      @click="openRejectDialog(comment)"
                    >
                      Reject
                    </v-btn>

                    <v-btn
                      size="small"
                      color="error"
                      variant="tonal"
                      :loading="actionLoading && processingCommentId === comment.id && processingAction === 'delete'"
                      :disabled="actionLoading"
                      @click="onDelete(comment)"
                    >
                      Delete
                    </v-btn>
                  </div>
                </td>
              </tr>
            </tbody>
          </v-table>

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

        <v-alert
          v-if="reportsError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
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
                    {{ report.status }}
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
                      Resolve
                    </v-btn>

                    <v-btn
                      size="small"
                      color="warning"
                      variant="tonal"
                      :disabled="report.status !== 'pending' || actionLoading"
                      :loading="actionLoading && processingReportId === report.id && processingAction === 'reject-report'"
                      @click="openResolveDialog(report, 'rejected')"
                    >
                      Reject
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

    <!-- Reject comment dialog -->
    <v-dialog v-model="rejectDialog.open" max-width="560">
      <v-card>
        <v-card-title>Відхилити коментар #{{ rejectDialog.comment?.id }}</v-card-title>
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

    <!-- Resolve report dialog -->
    <v-dialog v-model="resolveDialog.open" max-width="560">
      <v-card>
        <v-card-title>
          {{ resolveDialog.status === 'resolved' ? 'Підтвердити обробку скарги' : 'Відхилити скаргу' }}
          #{{ resolveDialog.report?.id }}
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

const localCommentsFilters = reactive({
  status: 'pending',
  type: null,
  page: 1,
  per_page: 20,
});

const localReportsFilters = reactive({
  status: 'pending',
  page: 1,
  per_page: 20,
});

const processingAction = ref(null); // approve | delete | resolve | reject-report
const processingCommentId = ref(null);
const processingReportId = ref(null);

const rejectDialog = reactive({
  open: false,
  comment: null,
  reason: '',
});

const resolveDialog = reactive({
  open: false,
  report: null,
  status: 'resolved', // resolved|rejected
  note: '',
});

const snackbar = reactive({
  open: false,
  text: '',
  color: 'info',
});

const commentStatusOptions = [
  { title: 'Pending', value: 'pending' },
  { title: 'Approved', value: 'approved' },
  { title: 'Rejected', value: 'rejected' },
];

const commentTypeOptions = [
  { title: 'Усі', value: null },
  { title: 'Review', value: 'review' },
  { title: 'Question', value: 'question' },
];

const reportStatusOptions = [
  { title: 'Pending', value: 'pending' },
  { title: 'Resolved', value: 'resolved' },
  { title: 'Rejected', value: 'rejected' },
];

const isTabLoading = computed(() =>
  activeTab.value === 'comments' ? commentsLoading.value : reportsLoading.value
);

function showSnackbar(text, color = 'info') {
  snackbar.text = text;
  snackbar.color = color;
  snackbar.open = true;
}

function formatDate(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString('uk-UA');
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

async function loadComments() {
  await moderationStore.fetchComments({ ...localCommentsFilters });
}

async function loadReports() {
  await moderationStore.fetchReports({ ...localReportsFilters });
}

function syncLocalFiltersFromStore() {
  Object.assign(localCommentsFilters, {
    status: commentsFilters.value?.status ?? 'pending',
    type: commentsFilters.value?.type ?? null,
    page: commentsFilters.value?.page ?? 1,
    per_page: commentsFilters.value?.per_page ?? 20,
  });

  Object.assign(localReportsFilters, {
    status: reportsFilters.value?.status ?? 'pending',
    page: reportsFilters.value?.page ?? 1,
    per_page: reportsFilters.value?.per_page ?? 20,
  });
}

async function applyCommentsFilters() {
  localCommentsFilters.page = 1;
  moderationStore.setCommentsFilters({ ...localCommentsFilters });
  await loadComments();
}

async function resetCommentsFilters() {
  Object.assign(localCommentsFilters, {
    status: 'pending',
    type: null,
    page: 1,
    per_page: 20,
  });
  moderationStore.setCommentsFilters({ ...localCommentsFilters });
  await loadComments();
}

async function onCommentsPageChange(page) {
  localCommentsFilters.page = page;
  moderationStore.setCommentsFilters({ ...localCommentsFilters });
  await loadComments();
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

async function onApprove(comment) {
  try {
    processingAction.value = 'approve';
    processingCommentId.value = comment.id;

    await moderationStore.approveComment(comment.id);
    showSnackbar(`Коментар #${comment.id} схвалено`, 'success');

    // если фильтр pending — элемент может исчезнуть после reload
    await loadComments();
  } catch (e) {
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

  try {
    processingAction.value = 'approve'; // same endpoint moderation
    processingCommentId.value = rejectDialog.comment.id;

    await moderationStore.rejectComment(rejectDialog.comment.id, rejectDialog.reason);
    showSnackbar(`Коментар #${rejectDialog.comment.id} відхилено`, 'success');
    closeRejectDialog();
    await loadComments();
  } catch (e) {
    showSnackbar('Не вдалося відхилити коментар', 'error');
  } finally {
    processingAction.value = null;
    processingCommentId.value = null;
  }
}

async function onDelete(comment) {
  const ok = window.confirm(`Видалити коментар #${comment.id}?`);
  if (!ok) return;

  try {
    processingAction.value = 'delete';
    processingCommentId.value = comment.id;

    await moderationStore.deleteComment(comment.id);
    showSnackbar(`Коментар #${comment.id} видалено`, 'success');

    // если страница опустела после удаления — подправим page
    if (!comments.value.length && localCommentsFilters.page > 1) {
      localCommentsFilters.page -= 1;
    }
    await loadComments();
  } catch (e) {
    showSnackbar('Не вдалося видалити коментар', 'error');
  } finally {
    processingAction.value = null;
    processingCommentId.value = null;
  }
}

function openResolveDialog(report, status) {
  resolveDialog.report = report;
  resolveDialog.status = status; // resolved|rejected
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
      resolveDialog.status === 'resolved'
        ? `Скаргу #${resolveDialog.report.id} оброблено`
        : `Скаргу #${resolveDialog.report.id} відхилено`,
      'success'
    );

    closeResolveDialog();
    await loadReports();
  } catch (e) {
    showSnackbar('Не вдалося обробити скаргу', 'error');
  } finally {
    processingAction.value = null;
    processingReportId.value = null;
  }
}

async function reloadCurrentTab() {
  if (activeTab.value === 'comments') await loadComments();
  else await loadReports();
}

watch(activeTab, async (tab) => {
  moderationStore.resetErrors();

  if (tab === 'comments') {
    await loadComments();
  } else {
    await loadReports();
  }
});

onMounted(async () => {
  syncLocalFiltersFromStore();
  await loadComments();
});
</script>

<style scoped>
.comment-body {
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.35;
}
</style>