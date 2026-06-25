<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-4">
      <h2 class="text-h6">Написати повідомлення</h2>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
      {{ error }}
    </v-alert>

    <v-card class="mb-5">
      <v-card-text>
        <v-switch
          v-model="form.send_to_all"
          label="Надіслати всім користувачам"
          color="primary"
          inset
          class="mb-2"
        />

        <v-autocomplete
          v-if="!form.send_to_all"
          v-model="form.recipient_ids"
          :items="userOptions"
          item-title="label"
          item-value="id"
          label="Оберіть користувачів"
          multiple
          chips
          closable-chips
          clearable
          variant="outlined"
          density="comfortable"
          :loading="loadingUsers"
          @update:search="onUsersSearch"
          class="mb-3"
        />

        <v-text-field
          v-model="form.subject"
          label="Тема"
          variant="outlined"
          density="comfortable"
          maxlength="255"
          counter
          class="mb-3"
        />

        <v-textarea
          v-model="form.body"
          label="Повідомлення"
          variant="outlined"
          density="comfortable"
          rows="4"
          auto-grow
          maxlength="10000"
          counter
          class="mb-3"
        />

        <v-file-input
          v-model="form.attachments"
          label="Вкладення (до 10 файлів, до 8MB кожен)"
          variant="outlined"
          density="comfortable"
          multiple
          chips
          show-size
          counter
          prepend-icon="mdi-paperclip"
          class="mb-3"
        />

        <div class="d-flex justify-end">
          <v-btn
            color="primary"
            :loading="sending"
            :disabled="!canSend"
            @click="sendMessage"
          >
            Надіслати
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-title class="text-subtitle-1 d-flex align-center justify-space-between flex-wrap ga-3">
        <span>Надіслані повідомлення</span>

        <div class="d-flex align-center ga-2">
          <span class="text-caption text-grey">Показувати:</span>
          <v-select
            v-model="perPageUi"
            :items="perPageOptions"
            item-title="title"
            item-value="value"
            variant="outlined"
            density="compact"
            hide-details
            style="width: 130px;"
            @update:model-value="onPerPageChange"
          />
        </div>
      </v-card-title>
      <v-divider />

      <v-table density="comfortable" class="messages-table">
        <thead>
          <tr>
            <th style="width: 66px;">ID</th>
            <th style="width: 220px;">Тема</th>
            <th style="width: 170px;">Кому</th>
            <th style="width: 170px;">Відправник</th>
            <th style="width: 220px;">Статуси</th>
            <th style="width: 170px;">Оновлення статусу</th>
            <th style="width: 140px;">Надіслано</th>
            <th style="width: 88px;" class="text-center">Дії</th>
          </tr>
        </thead>

        <tbody>
          <template v-for="m in sentItems" :key="`row-${m.id}`">
            <tr
              class="message-main-row"
              @click="toggleExpanded(m.id)"
            >
              <td>#{{ m.id }}</td>

              <td>
                <div
                  class="subject-cell"
                  :title="m.subject"
                >
                  {{ truncateSubject(m.subject) }}
                </div>
              </td>

              <td>
                <template v-if="m.is_broadcast">Усім користувачам</template>
                <template v-else>{{ m.recipients?.length || 0 }} отримувач(ів)</template>
              </td>

              <td>{{ m.sender?.name || `User #${m.sender_id}` }}</td>

              <td>
                <div class="status-chips-wrap">
                  <v-chip size="x-small" color="warning" variant="tonal">
                    Не прочитано: {{ countByStatus(m, 'unread') }}
                  </v-chip>
                  <v-chip size="x-small" color="success" variant="tonal">
                    Прочитано: {{ countByStatus(m, 'read') }}
                  </v-chip>
                  <v-chip size="x-small" color="grey" variant="tonal">
                    Видалено: {{ countByStatus(m, 'deleted') }}
                  </v-chip>
                </div>
              </td>

              <td>{{ formatDateTime(maxStatusChangedAt(m)) || '—' }}</td>
              <td>{{ formatDateTime(m.sent_at || m.created_at) }}</td>

              <td class="text-center">
                <div class="d-flex align-center justify-center ga-1">
                  <v-tooltip text="Редагувати" location="top">
                    <template #activator="{ props }">
                      <v-btn
                        v-bind="props"
                        icon
                        size="x-small"
                        variant="text"
                        color="info"
                        @click.stop="openEditDialog(m)"
                      >
                        <v-icon size="18">mdi-pencil</v-icon>
                      </v-btn>
                    </template>
                  </v-tooltip>

                  <v-tooltip text="Видалити" location="top">
                    <template #activator="{ props }">
                      <v-btn
                        v-bind="props"
                        icon
                        size="x-small"
                        variant="text"
                        color="error"
                        @click.stop="removeMessage(m)"
                      >
                        <v-icon size="18">mdi-delete</v-icon>
                      </v-btn>
                    </template>
                  </v-tooltip>
                </div>
              </td>
            </tr>

            <tr v-if="expandedRows.has(m.id)" :key="`expand-${m.id}`" class="expanded-row">
              <td colspan="8">
                <div class="expanded-content">
                  <div class="d-flex align-center justify-space-between mb-3">
                    <div class="text-subtitle-2">
                      Деталі повідомлення #{{ m.id }}
                    </div>

                    <v-btn
                      size="x-small"
                      variant="text"
                      @click="toggleExpanded(m.id)"
                    >
                      Згорнути
                    </v-btn>
                  </div>

                  <template v-if="!m.is_broadcast">
                    <div class="text-body-2 font-weight-medium mb-2">Отримувачі</div>
                    <v-table density="compact" class="mb-4 recipients-table">
                      <thead>
                        <tr>
                          <th style="width: 80px;">ID</th>
                          <th>Користувач</th>
                          <th style="width: 180px;">Статус</th>
                          <th style="width: 180px;">Оновлено</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="r in (m.recipients || [])" :key="`r-${m.id}-${r.id}`">
                          <td>#{{ r.recipient_id }}</td>
                          <td>{{ r.recipient?.name || `User #${r.recipient_id}` }}</td>
                          <td>
                            <v-chip
                              size="x-small"
                              :color="recipientStatusColor(r.status)"
                              variant="tonal"
                            >
                              {{ recipientStatusLabel(r.status) }}
                            </v-chip>
                          </td>
                          <td>{{ formatDateTime(r.status_changed_at || r.read_at) || '—' }}</td>
                        </tr>
                      </tbody>
                    </v-table>
                  </template>

                  <div class="text-body-2 font-weight-medium mb-2">Повний текст повідомлення</div>
                  <div class="full-body mb-4">{{ m.body || '—' }}</div>

                  <div v-if="(m.attachments || []).length">
                    <div class="text-body-2 font-weight-medium mb-2">Вкладення</div>
                    <v-list density="compact" class="attachments-list">
                      <v-list-item
                        v-for="att in m.attachments"
                        :key="`att-${m.id}-${att.id}`"
                        :href="att.url"
                        target="_blank"
                        rel="noopener noreferrer"
                      >
                        <v-list-item-title>{{ att.original_name }}</v-list-item-title>
                        <v-list-item-subtitle>
                          {{ att.mime || 'file' }} · {{ formatSize(att.size) }}
                        </v-list-item-subtitle>
                      </v-list-item>
                    </v-list>
                  </div>
                </div>
              </td>
            </tr>
          </template>

          <tr v-if="!loadingList && !sentItems.length">
            <td colspan="8" class="text-center text-grey py-4">Поки немає надісланих повідомлень</td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <div class="d-flex justify-center mt-4" v-if="showPagination">
      <v-pagination
        v-model="page"
        :length="lastPage"
        @update:model-value="loadSent"
      />
    </div>

    <v-dialog v-model="editDialog.open" max-width="760">
      <v-card>
        <v-card-title class="text-h6">Редагувати повідомлення #{{ editDialog.item?.id }}</v-card-title>
        <v-divider />
        <v-card-text>
          <v-text-field
            v-model="editDialog.form.subject"
            label="Тема"
            variant="outlined"
            density="comfortable"
            maxlength="255"
            counter
            class="mb-3"
          />
          <v-textarea
            v-model="editDialog.form.body"
            label="Повідомлення"
            variant="outlined"
            density="comfortable"
            rows="4"
            auto-grow
            maxlength="10000"
            counter
            class="mb-3"
          />

          <v-file-input
            v-model="editDialog.form.new_attachments"
            label="Додати вкладення"
            variant="outlined"
            density="comfortable"
            multiple
            chips
            show-size
            counter
            class="mb-3"
          />

          <div v-if="(editDialog.item?.attachments || []).length">
            <div class="text-subtitle-2 mb-2">Поточні вкладення</div>
            <v-checkbox
              v-for="att in editDialog.item.attachments"
              :key="att.id"
              v-model="editDialog.form.remove_attachment_ids"
              :value="att.id"
              density="compact"
              hide-details
              class="mb-1"
            >
              <template #label>
                <span>{{ att.original_name }} ({{ formatSize(att.size) }}) — видалити</span>
              </template>
            </v-checkbox>
          </div>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="editDialog.open = false">Скасувати</v-btn>
          <v-btn color="primary" :loading="editDialog.saving" @click="saveEdit">Зберегти</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar
      v-model="snackbar.open"
      :color="snackbar.color"
      :timeout="snackbar.timeout"
      location="top right"
    >
      {{ snackbar.text }}
      <template #actions>
        <v-btn variant="text" @click="snackbar.open = false">Закрити</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import apiClient from '@/api';

const loadingUsers = ref(false);
const loadingList = ref(false);
const sending = ref(false);
const error = ref('');

const page = ref(1);
const lastPage = ref(1);

const perPageUi = ref(25);
const perPageApi = computed(() => (perPageUi.value === -1 ? 100000 : perPageUi.value));
const perPageOptions = [
  { title: '25', value: 25 },
  { title: '50', value: 50 },
  { title: '100', value: 100 },
  { title: 'Усі', value: -1 },
];

const showPagination = computed(() => perPageUi.value !== -1 && lastPage.value > 1);

const userOptions = ref([]);
const userSearch = ref('');

const sentItems = ref([]);
const expandedRows = reactive(new Set());

const form = ref({
  send_to_all: false,
  recipient_ids: [],
  subject: '',
  body: '',
  attachments: [],
});

const editDialog = ref({
  open: false,
  saving: false,
  item: null,
  form: {
    subject: '',
    body: '',
    new_attachments: [],
    remove_attachment_ids: [],
  },
});

const snackbar = ref({
  open: false,
  text: '',
  color: 'info',
  timeout: 3000,
});

function showSnackbar(text, color = 'info', timeout = 3000) {
  snackbar.value = { open: true, text, color, timeout };
}

const canSend = computed(() => {
  const hasRecipients = form.value.send_to_all || form.value.recipient_ids.length > 0;
  return hasRecipients && form.value.subject.trim() && form.value.body.trim() && !sending.value;
});

function truncateSubject(s) {
  const str = String(s || '');
  if (str.length <= 32) return str;
  return `${str.slice(0, 32)}…`;
}

function formatDateTime(iso) {
  if (!iso) return '';
  return new Intl.DateTimeFormat('uk-UA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso));
}

function formatSize(bytes) {
  const b = Number(bytes || 0);
  if (b < 1024) return `${b} B`;
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
  return `${(b / (1024 * 1024)).toFixed(1)} MB`;
}

function countByStatus(m, status) {
  return (m.recipients || []).filter(r => r.status === status).length;
}

function maxStatusChangedAt(m) {
  const arr = (m.recipients || [])
    .map(r => r.status_changed_at || r.read_at || null)
    .filter(Boolean)
    .map(v => new Date(v).getTime());
  if (!arr.length) return null;
  return new Date(Math.max(...arr)).toISOString();
}

function recipientStatusLabel(status) {
  if (status === 'unread') return 'Не прочитано';
  if (status === 'read') return 'Прочитано';
  if (status === 'deleted') return 'Видалено';
  return status || '—';
}

function recipientStatusColor(status) {
  if (status === 'unread') return 'warning';
  if (status === 'read') return 'success';
  if (status === 'deleted') return 'grey';
  return 'default';
}

function toggleExpanded(messageId) {
  if (expandedRows.has(messageId)) {
    expandedRows.delete(messageId);
  } else {
    expandedRows.add(messageId);
  }
}

async function loadUsers() {
  loadingUsers.value = true;
  try {
    const { data } = await apiClient.get('/users', {
      params: { q: userSearch.value || '', per_page: 50 },
    });
    const list = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []);
    userOptions.value = list.map(u => ({
      id: u.id,
      label: `${u.name} (#${u.id})`,
    }));
  } finally {
    loadingUsers.value = false;
  }
}

let userSearchTimer = null;
function onUsersSearch(v) {
  userSearch.value = v || '';
  if (userSearchTimer) clearTimeout(userSearchTimer);
  userSearchTimer = setTimeout(loadUsers, 350);
}

function onPerPageChange() {
  page.value = 1;
  loadSent();
}

async function loadSent() {
  loadingList.value = true;
  error.value = '';
  try {
    const { data } = await apiClient.get('/admin-messages/sent', {
      params: {
        page: page.value,
        per_page: perPageApi.value,
      },
    });

    sentItems.value = data?.data || [];
    lastPage.value = Number(data?.last_page || 1);

    // чистим раскрытые строки, которых уже нет на текущей странице
    const ids = new Set((sentItems.value || []).map(x => x.id));
    Array.from(expandedRows).forEach(id => {
      if (!ids.has(id)) expandedRows.delete(id);
    });
  } catch {
    error.value = 'Не вдалося завантажити надіслані повідомлення';
    sentItems.value = [];
    lastPage.value = 1;
    expandedRows.clear();
  } finally {
    loadingList.value = false;
  }
}

function validateAttachments(files = []) {
  for (const f of files) {
    if (Number(f.size || 0) > 8 * 1024 * 1024) {
      return `Файл "${f.name}" перевищує 8MB`;
    }
  }
  return null;
}

async function sendMessage() {
  if (!canSend.value) return;

  const invalid = validateAttachments(form.value.attachments || []);
  if (invalid) {
    showSnackbar(invalid, 'error', 4200);
    return;
  }

  sending.value = true;
  try {
    await apiClient.getCsrfCookie();

    const fd = new FormData();
    fd.append('send_to_all', form.value.send_to_all ? '1' : '0');
    fd.append('subject', form.value.subject.trim());
    fd.append('body', form.value.body.trim());

    if (!form.value.send_to_all) {
      form.value.recipient_ids.forEach((id, i) => {
        fd.append(`recipient_ids[${i}]`, String(id));
      });
    }

    (form.value.attachments || []).forEach((file, i) => {
      fd.append(`attachments[${i}]`, file);
    });

    await apiClient.post('/admin-messages', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    form.value = {
      send_to_all: false,
      recipient_ids: [],
      subject: '',
      body: '',
      attachments: [],
    };

    showSnackbar('Повідомлення надіслано', 'success');
    await loadSent();
  } catch (e) {
    const msg = e?.response?.data?.message || 'Не вдалося надіслати повідомлення';
    showSnackbar(msg, 'error', 4200);
  } finally {
    sending.value = false;
  }
}

function openEditDialog(item) {
  editDialog.value.item = item;
  editDialog.value.form.subject = item.subject || '';
  editDialog.value.form.body = item.body || '';
  editDialog.value.form.new_attachments = [];
  editDialog.value.form.remove_attachment_ids = [];
  editDialog.value.open = true;
}

async function saveEdit() {
  const item = editDialog.value.item;
  if (!item?.id) return;

  const invalid = validateAttachments(editDialog.value.form.new_attachments || []);
  if (invalid) {
    showSnackbar(invalid, 'error', 4200);
    return;
  }

  editDialog.value.saving = true;
  try {
    await apiClient.getCsrfCookie();

    const fd = new FormData();
    fd.append('_method', 'PUT');
    fd.append('subject', editDialog.value.form.subject.trim());
    fd.append('body', editDialog.value.form.body.trim());

    editDialog.value.form.remove_attachment_ids.forEach((id, i) => {
      fd.append(`remove_attachment_ids[${i}]`, String(id));
    });

    (editDialog.value.form.new_attachments || []).forEach((f, i) => {
      fd.append(`attachments[${i}]`, f);
    });

    await apiClient.post(`/admin-messages/${item.id}`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    showSnackbar('Зміни збережено', 'success');
    editDialog.value.open = false;
    await loadSent();
  } catch (e) {
    const msg = e?.response?.data?.message || 'Не вдалося зберегти зміни';
    showSnackbar(msg, 'error', 4200);
  } finally {
    editDialog.value.saving = false;
  }
}

async function removeMessage(item) {
  if (!item?.id) return;
  if (!confirm(`Видалити повідомлення #${item.id}?`)) return;

  try {
    await apiClient.getCsrfCookie();
    await apiClient.delete(`/admin-messages/${item.id}`);
    showSnackbar('Повідомлення видалено', 'success');
    await loadSent();
  } catch (e) {
    const msg = e?.response?.data?.message || 'Не вдалося видалити повідомлення';
    showSnackbar(msg, 'error', 4200);
  }
}

defineExpose({ loadSent });

onMounted(async () => {
  await loadUsers();
  await loadSent();
});
</script>

<style scoped>
.messages-table {
  table-layout: fixed;
  width: 100%;
}

.messages-table :deep(th),
.messages-table :deep(td) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.subject-cell {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status-chips-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  white-space: normal;
}

.message-main-row {
  cursor: pointer;
}

.expanded-row > td {
  white-space: normal !important;
  background: rgba(127, 127, 127, 0.06);
}

.expanded-content {
  padding: 12px 4px 8px;
}

.full-body {
  white-space: pre-wrap;
  word-break: break-word;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(127, 127, 127, 0.08);
}

.recipients-table :deep(th),
.recipients-table :deep(td) {
  white-space: nowrap;
}

.attachments-list :deep(.v-list-item-title) {
  white-space: normal;
  word-break: break-word;
}
</style>