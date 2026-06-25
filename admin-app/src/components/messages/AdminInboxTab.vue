<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-4">
      <h2 class="text-h6">Повідомлення від адміністратора</h2>

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
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
      {{ error }}
    </v-alert>

    <v-card>
      <v-list lines="two" class="py-0">
        <v-list-item
          v-for="item in items"
          :key="item.id"
          class="inbox-row"
          :class="{
            'inbox-row--unread-dark': item.status === 'unread' && isDark,
            'inbox-row--unread-light': item.status === 'unread' && !isDark
          }"
          @click="openMessage(item)"
        >
          <v-list-item-title class="d-flex align-center justify-space-between">
            <span class="d-flex align-center ga-2">
              <span class="font-weight-medium">{{ item.message?.subject || 'Без теми' }}</span>
              <v-chip
                size="x-small"
                :color="item.status === 'unread' ? 'warning' : 'success'"
                variant="tonal"
              >
                {{ item.status === 'unread' ? 'Нове' : 'Прочитано' }}
              </v-chip>
            </span>

            <span class="text-caption text-grey">
              {{ formatDateTime(item.message?.sent_at || item.message?.created_at) }}
            </span>
          </v-list-item-title>

          <v-list-item-subtitle
            :class="item.status === 'unread'
              ? (isDark ? 'subtitle-unread-dark' : 'subtitle-unread-light')
              : ''"
          >
            Від: {{ item.message?.sender?.name || 'Адміністратор' }}
          </v-list-item-subtitle>
        </v-list-item>

        <v-list-item v-if="!loading && !items.length">
          <v-list-item-title class="text-grey">Повідомлень поки немає</v-list-item-title>
        </v-list-item>
      </v-list>
    </v-card>

    <div class="d-flex justify-center mt-4" v-if="showPagination">
      <v-pagination v-model="page" :length="lastPage" @update:model-value="loadInbox" />
    </div>

    <v-dialog v-model="dialog.open" max-width="760">
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
          <span class="text-h6">{{ dialog.item?.message?.subject || 'Повідомлення' }}</span>
          <v-chip
            size="small"
            :color="dialog.item?.status === 'unread' ? 'warning' : 'success'"
            variant="tonal"
          >
            {{ dialog.item?.status === 'unread' ? 'Нове' : 'Прочитано' }}
          </v-chip>
        </v-card-title>

        <v-divider />

        <v-card-text>
          <div class="text-caption text-grey mb-2">
            Від: {{ dialog.item?.message?.sender?.name || 'Адміністратор' }}
          </div>
          <div class="text-caption text-grey mb-4">
            Надіслано: {{ formatDateTime(dialog.item?.message?.sent_at || dialog.item?.message?.created_at) }}
          </div>

          <div style="white-space: pre-wrap;" class="mb-4">
            {{ dialog.item?.message?.body || '' }}
          </div>

          <div v-if="(dialog.item?.message?.attachments || []).length">
            <div class="text-subtitle-2 mb-2">Вкладення</div>
            <v-list density="compact">
              <v-list-item
                v-for="att in dialog.item.message.attachments"
                :key="att.id"
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
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog.open = false">Закрити</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useTheme } from 'vuetify';
import apiClient from '@/api';

const emit = defineEmits(['unread-changed']);

const theme = useTheme();
const isDark = computed(() => theme.global.current.value.dark);

const loading = ref(false);
const error = ref('');
const items = ref([]);

const page = ref(1);
const lastPage = ref(1);

const perPageUi = ref(25);
const perPageOptions = [
  { title: '25', value: 25 },
  { title: '50', value: 50 },
  { title: '100', value: 100 },
  { title: 'Усі', value: -1 },
];
const perPageApi = computed(() => (perPageUi.value === -1 ? 100000 : perPageUi.value));
const showPagination = computed(() => perPageUi.value !== -1 && lastPage.value > 1);

const dialog = ref({
  open: false,
  item: null,
});

function onPerPageChange() {
  page.value = 1;
  loadInbox();
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

async function loadUnreadCount() {
  try {
    const { data } = await apiClient.get('/admin-messages/unread-count');
    emit('unread-changed', Number(data?.unread_count || 0));
  } catch {
    emit('unread-changed', 0);
  }
}

async function loadInbox() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await apiClient.get('/admin-messages/inbox', {
      params: {
        page: page.value,
        per_page: perPageApi.value,
      },
    });

    items.value = data?.data || [];
    lastPage.value = Number(data?.last_page || 1);
    await loadUnreadCount();
  } catch {
    error.value = 'Не вдалося завантажити повідомлення';
  } finally {
    loading.value = false;
  }
}

async function openMessage(item) {
  dialog.value.item = item;
  dialog.value.open = true;

  if (item?.status === 'unread' && item?.message?.id) {
    try {
      await apiClient.getCsrfCookie();
      await apiClient.post(`/admin-messages/${item.message.id}/read`);

      item.status = 'read';
      item.read_at = new Date().toISOString();
      item.status_changed_at = new Date().toISOString();

      await loadUnreadCount();
    } catch {
      // no-op
    }
  }
}

defineExpose({ loadInbox });

onMounted(loadInbox);
</script>

<style scoped>
.inbox-row {
  border-bottom: 1px solid rgba(127, 127, 127, 0.16);
  cursor: pointer;
}

.inbox-row--unread-dark {
  background: #1f3a2a;
}
.inbox-row--unread-dark :deep(.v-list-item-title) {
  color: #e8f5e9 !important;
  font-weight: 600;
}
.subtitle-unread-dark {
  color: rgba(232, 245, 233, 0.82) !important;
}

.inbox-row--unread-light {
  background: #e8f5e9;
}
.inbox-row--unread-light :deep(.v-list-item-title) {
  color: #1b5e20 !important;
  font-weight: 600;
}
.subtitle-unread-light {
  color: rgba(27, 94, 32, 0.78) !important;
}
</style>