<template>
  <v-navigation-drawer
    v-model="drawerModel"
    location="right"
    temporary
    width="520"
  >
    <v-sheet class="d-flex align-center justify-space-between pa-4" elevation="0">
      <div class="d-flex flex-column">
        <div class="text-h6">
          {{ sellerTitle }}
        </div>
        <div
          class="text-caption"
          :class="sellerIsOnline ? 'seller-status--online' : 'text-grey'">
          {{ sellerStatusText }}
        </div>
      </div>

      <div class="d-flex align-center ga-2">
        <v-btn
          v-if="hasAnyReportInConversation"
          size="small"
          variant="flat"
          :color="hasOpenReportInConversation ? 'red-darken-2' : 'success'"
          class="report-toggle-btn"
          @click="toggleReportInfo"
        >
          {{ hasOpenReportInConversation ? 'Скарга' : 'Скарга оброблена' }}
          <v-icon end size="16">
            {{ reportInfoOpen ? 'mdi-chevron-up' : 'mdi-chevron-down' }}
          </v-icon>
        </v-btn>

        <v-tooltip text="Поскаржитися" location="bottom">
          <template #activator="{ props }">
            <v-btn
              v-bind="props"
              icon
              variant="text"
              color="orange-darken-2"
              @click="openReportDialog"
              aria-label="Поскаржитися"
            >
              <v-icon>mdi-emoticon-angry-outline</v-icon>
            </v-btn>
          </template>
        </v-tooltip>

        <v-btn icon @click="drawerModel = false" aria-label="Закрити">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </div>
    </v-sheet>

    <v-expand-transition>
      <div v-if="hasAnyReportInConversation && reportInfoOpen" class="px-4 pb-3">
        <v-sheet rounded border class="pa-3 report-info-sheet">
          <div v-if="reportDetailsLoading" class="py-2">
            <v-skeleton-loader type="paragraph" />
          </div>

          <template v-else-if="hasOpenReportInConversation && latestOpenReport">
            <div class="text-caption text-grey mb-1">
              Дата скарги: {{ formatDateTime(latestOpenReport.created_at) }}
            </div>

            <div class="text-caption mb-2">
              Статус:
              <v-chip size="x-small" color="warning" variant="tonal">
                Відкрита
              </v-chip>
            </div>

            <div class="text-body-2" style="white-space: pre-wrap;">
              {{ latestOpenReport.reason || 'Без опису' }}
            </div>
          </template>

          <template v-else-if="latestResolvedReport">
            <div class="text-caption text-grey mb-1">
              Дата обробки: {{ formatDateTime(latestResolvedReport.resolved_at || latestResolvedReport.updated_at) }}
            </div>

            <div class="text-caption mb-2">
              Статус:
              <v-chip size="x-small" color="success" variant="tonal">
                Оброблена
              </v-chip>
            </div>

            <div class="text-caption text-grey mb-1">Відповідь адміністратора / менеджера:</div>
            <div class="text-body-2" style="white-space: pre-wrap;">
              {{ latestResolvedReport.resolution_note || 'Без відповіді' }}
            </div>
          </template>

          <div v-else class="text-caption text-grey">
            Дані по скарзі недоступні
          </div>
        </v-sheet>
      </div>
    </v-expand-transition>

    <v-divider />

    <div ref="chatBodyEl" class="chat-body" @scroll.passive="updateIsNearBottom">
      <div v-if="messageStore.loadingMessages" class="pa-4">
        <v-skeleton-loader type="paragraph" />
      </div>

      <div v-else class="messages pa-4">
        <template v-for="(m, idx) in messageStore.messages" :key="m.id">
          <div v-if="shouldShowDateDivider(messageStore.messages, idx)" class="date-divider">
            <span>{{ formatDividerDate(m.created_at) }}</span>
          </div>

          <div class="message-row" :class="{ mine: isMine(m) }">
            <div class="bubble" :class="{ 'bubble--with-actions': isMine(m) && !m.deleted_by_user }">
              <div class="bubble-actions" v-if="isMine(m) && !m.deleted_by_user">
                <v-menu location="bottom end">
                  <template #activator="{ props }">
                    <v-btn v-bind="props" icon size="x-small" variant="text" @click.stop>
                      <v-icon size="16">mdi-dots-vertical</v-icon>
                    </v-btn>
                  </template>

                  <v-list density="compact">
                    <v-list-item @click="deleteMyMessage(m.id)">
                      <template #prepend>
                        <v-icon size="18">mdi-delete-outline</v-icon>
                      </template>
                      <v-list-item-title>Видалити</v-list-item-title>
                    </v-list-item>
                  </v-list>
                </v-menu>
              </div>

              <template v-if="m.deleted_by_user">
                <div class="deleted-message">Повідомлення видалено</div>
              </template>

              <template v-else>
                <div v-if="m.body" class="mb-2" style="white-space: pre-wrap;">{{ m.body }}</div>

                <div v-if="m.images && m.images.length" class="images">
                  <v-img
                    v-for="(img, idx2) in m.images"
                    :key="img.id"
                    :src="img.url"
                    max-width="240"
                    class="mb-2 rounded chat-image"
                    cover
                    @click="openChatLightbox(m.images, idx2)"
                    @load="onAnyChatImageLoad"
                  />
                </div>
              </template>

              <div class="meta">
                <span class="meta-time">{{ formatTimeParts(m.created_at).time }}</span>
                <span class="meta-sep">·</span>
                <span class="meta-date">{{ formatTimeParts(m.created_at).date }}</span>

                <span v-if="isMine(m)" class="meta-status">
                  <v-icon size="14">
                    {{ m.read_at ? 'mdi-check-all' : 'mdi-check' }}
                  </v-icon>
                </span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <v-divider />

    <div class="pa-3">
      <v-textarea
        v-model="draft"
        label="Повідомлення"
        rows="2"
        auto-grow
        variant="outlined"
        density="compact"
        hide-details
        @keydown.enter.exact.prevent="onSend"
      />

      <div class="d-flex align-center mt-2">
        <v-btn variant="text" @click="pickImage" :disabled="messageStore.sending">
          <v-icon class="mr-1">mdi-image</v-icon>
          Фото
        </v-btn>

        <input
          ref="fileInput"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="d-none"
          @change="onFileSelected"
        />

        <v-spacer />

        <v-btn
          color="primary"
          @click="onSend"
          :loading="messageStore.sending"
          :disabled="!canSend"
        >
          Надіслати
        </v-btn>
      </div>

      <div v-if="selectedImageName" class="text-caption text-grey mt-1">
        Обрано: {{ selectedImageName }}
      </div>

      <v-snackbar
        v-model="snackbar.show"
        :timeout="3500"
        color="error"
        variant="tonal"
        location="bottom"
      >
        {{ snackbar.text }}

        <template #actions>
          <v-btn variant="text" @click="snackbar.show = false">OK</v-btn>
        </template>
      </v-snackbar>

      <v-dialog v-model="chatLightbox.open" width="92%" max-width="1400">
        <v-card>
          <v-card-text class="pa-0 d-flex align-center justify-center" style="background:#000;">
            <div style="position:relative; width:100%;">
              <img
                v-if="chatLightboxCurrentUrl"
                :src="chatLightboxCurrentUrl"
                alt="photo"
                style="max-height:80vh; width:100%; object-fit:contain; display:block; cursor:zoom-out;"
                @click="chatLightbox.open = false"
              />
            </div>
          </v-card-text>

          <v-card-actions>
            <v-spacer />
            <v-btn text @click="chatLightbox.open = false">Закрыть</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="reportDialog.open" max-width="520">
        <v-card>
          <v-card-title class="text-h6">Підтвердити скаргу</v-card-title>

          <v-card-text>
            <div class="mb-3">Опишіть, будь ласка, причину скарги:</div>

            <v-textarea
              v-model="reportDialog.reason"
              label="Причина скарги"
              rows="3"
              auto-grow
              variant="outlined"
              density="compact"
              maxlength="2000"
              counter
            />
          </v-card-text>

          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="closeReportDialog">Скасувати</v-btn>
            <v-btn color="red-darken-2" variant="flat" :loading="reportDialog.loading" @click="confirmReport">
              Поскаржитися
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </v-navigation-drawer>
</template>

<script setup>
import { ref, computed, watch, onUnmounted, nextTick } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useMessageStore } from '@/stores/messageStore';
import apiClient from '@/api';

const authStore = useAuthStore();
const messageStore = useMessageStore();

const CHAT_TIME_ZONE = 'Europe/Kyiv';

const draft = ref('');
const selectedFile = ref(null);
const selectedImageName = computed(() => selectedFile.value?.name || '');

const snackbar = ref({
  show: false,
  text: '',
});

function showError(text) {
  snackbar.value.text = text;
  snackbar.value.show = true;
}

const chatBodyEl = ref(null);
const isNearBottom = ref(true);
let didInitialScroll = false;

function updateIsNearBottom() {
  const el = chatBodyEl.value;
  if (!el) return;
  const threshold = 80;
  isNearBottom.value = (el.scrollTop + el.clientHeight) >= (el.scrollHeight - threshold);
}

async function scrollToBottom({ force = false } = {}) {
  await nextTick();
  const el = chatBodyEl.value;
  if (!el) return;
  if (!force && !isNearBottom.value) return;
  el.scrollTop = el.scrollHeight;
}

const pendingScrollToBottomForImages = ref(false);

function scheduleScrollToBottomForce() {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      scrollToBottom({ force: true });
      updateIsNearBottom();
    });
  });
}

function onAnyChatImageLoad() {
  if (pendingScrollToBottomForImages.value || isNearBottom.value) {
    scheduleScrollToBottomForce();
    pendingScrollToBottomForImages.value = false;
  }
}

const fileInput = ref(null);

const drawerModel = computed({
  get: () => messageStore.drawerOpen,
  set: (v) => messageStore.setDrawerOpen(v),
});

const sellerTitle = computed(() => {
  const c = messageStore.currentConversation;
  return c?.seller?.name ? `Чат: ${c.seller.name}` : 'Чат';
});

function isOnline(lastSeenAt) {
  if (!lastSeenAt) return false;
  const last = new Date(lastSeenAt).getTime();
  return (Date.now() - last) < 5 * 60 * 1000;
}

const sellerIsOnline = computed(() => {
  const seller = messageStore.currentConversation?.seller;
  return !!seller && isOnline(seller.last_seen_at);
});

const sellerStatusText = computed(() => {
  const seller = messageStore.currentConversation?.seller;
  if (!seller) return '';
  if (isOnline(seller.last_seen_at)) return 'онлайн';
  return formatLastSeenText(seller.last_seen_at);
});

const hasAnyReportInConversation = computed(() => {
  return Number(messageStore.currentConversation?.reports_total_count || 0) > 0;
});

const hasOpenReportInConversation = computed(() => {
  return Number(messageStore.currentConversation?.reports_open_count || 0) > 0;
});

const reportInfoOpen = ref(false);
const reportDetailsLoading = ref(false);
const reportDetails = ref([]);

const latestOpenReport = computed(() => {
  const open = reportDetails.value.filter(r => r?.status === 'open');
  if (!open.length) return null;
  return [...open].sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0];
});

const latestResolvedReport = computed(() => {
  const resolved = reportDetails.value.filter(r => r?.status === 'resolved');
  if (!resolved.length) return null;
  return [...resolved].sort((a, b) => {
    const aTs = new Date(a.resolved_at || a.updated_at || a.created_at).getTime();
    const bTs = new Date(b.resolved_at || b.updated_at || b.created_at).getTime();
    return bTs - aTs;
  })[0];
});

async function loadReportDetails() {
  const conversationId = messageStore.currentConversation?.id;
  if (!conversationId) return;

  reportDetailsLoading.value = true;
  try {
    const { data } = await apiClient.get(`/conversations/${conversationId}/reports`);
    reportDetails.value = Array.isArray(data?.items) ? data.items : [];
  } catch {
    reportDetails.value = [];
  } finally {
    reportDetailsLoading.value = false;
  }
}

async function toggleReportInfo() {
  reportInfoOpen.value = !reportInfoOpen.value;
  if (reportInfoOpen.value) {
    await loadReportDetails();
  }
}

const chatLightbox = ref({
  open: false,
  urls: [],
  index: 0,
});

const chatLightboxCurrentUrl = computed(() => {
  const urls = chatLightbox.value.urls || [];
  return urls[chatLightbox.value.index] || '';
});

function openChatLightbox(images, startIndex = 0) {
  const urls = (images || []).map(x => x?.url).filter(Boolean);
  if (!urls.length) return;
  chatLightbox.value.urls = urls;
  chatLightbox.value.index = Math.max(0, Math.min(startIndex, urls.length - 1));
  chatLightbox.value.open = true;
}

function formatYMDInTz(date, timeZone) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(date);

  const y = parts.find(p => p.type === 'year')?.value;
  const m = parts.find(p => p.type === 'month')?.value;
  const d = parts.find(p => p.type === 'day')?.value;

  return `${y}-${m}-${d}`;
}

function isSameDayInTz(aIso, bDate = new Date(), timeZone) {
  const tz = timeZone || CHAT_TIME_ZONE;
  if (!aIso) return false;
  const a = new Date(aIso);
  return formatYMDInTz(a, tz) === formatYMDInTz(bDate, tz);
}

function formatTimeParts(iso) {
  if (!iso) return { time: '', date: '' };
  const d = new Date(iso);

  const time = new Intl.DateTimeFormat('uk-UA', {
    timeZone: CHAT_TIME_ZONE,
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);

  const date = new Intl.DateTimeFormat('uk-UA', {
    timeZone: CHAT_TIME_ZONE,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(d);

  return { time, date };
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

function formatLastSeenText(lastSeenAtIso) {
  if (!lastSeenAtIso) return 'був(ла) в мережі давно';
  const time = formatTimeParts(lastSeenAtIso).time;
  if (isSameDayInTz(lastSeenAtIso)) return `був(ла) в мережі сьогодні о ${time}`;
  const date = formatTimeParts(lastSeenAtIso).date;
  return `був(ла) в мережі ${date} о ${time}`;
}

function formatYMD(iso) {
  if (!iso) return '';
  return formatYMDInTz(new Date(iso), CHAT_TIME_ZONE);
}

function shouldShowDateDivider(list, idx) {
  if (!list || !list.length) return false;
  if (idx === 0) return true;
  return formatYMD(list[idx - 1]?.created_at) !== formatYMD(list[idx]?.created_at);
}

function formatDividerDate(iso) {
  if (!iso) return '';
  return new Intl.DateTimeFormat('uk-UA', {
    timeZone: CHAT_TIME_ZONE,
    day: 'numeric',
    month: 'short',
  }).format(new Date(iso)).toUpperCase();
}

function isMine(m) {
  return m.sender_id === authStore.user?.id;
}

const canSend = computed(() => {
  const hasProductContext = !!messageStore.currentProductContextId;
  return (
    hasProductContext &&
    messageStore.currentConversation?.id &&
    (draft.value.trim().length > 0 || !!selectedFile.value) &&
    !messageStore.sending
  );
});

function pickImage() {
  fileInput.value?.click();
}

function resetSelectedFile(e) {
  selectedFile.value = null;
  if (fileInput.value) fileInput.value.value = '';
  if (e?.target) e.target.value = '';
}

function onFileSelected(e) {
  const file = e.target.files?.[0] || null;
  if (!file) return;

  const allowedMime = new Set(['image/jpeg', 'image/png', 'image/webp']);
  const allowedExt = new Set(['jpg', 'jpeg', 'png', 'webp']);
  const ext = (file.name.split('.').pop() || '').toLowerCase();

  if (!allowedMime.has(file.type) || !allowedExt.has(ext)) {
    showError('Можна прикріпити лише зображення (JPG, PNG або WEBP).');
    resetSelectedFile(e);
    return;
  }

  if (file.size > 4 * 1024 * 1024) {
    showError('Максимальний розмір фото: 4 MB');
    resetSelectedFile(e);
    return;
  }

  selectedFile.value = file;
}

let pingTimer = null;

async function startPresencePing() {
  if (pingTimer) return;
  await messageStore.pingPresence().catch(() => {});
  await messageStore.refreshCurrentConversation().catch(() => {});

  pingTimer = window.setInterval(() => {
    messageStore.pingPresence().catch(() => {});
    messageStore.refreshCurrentConversation().catch(() => {});
  }, 60 * 1000);
}

function stopPresencePing() {
  if (pingTimer) window.clearInterval(pingTimer);
  pingTimer = null;
}

async function onSend() {
  if (!canSend.value) return;

  const conversationId = messageStore.currentConversation?.id;
  if (!conversationId) return;

  if (!messageStore.currentProductContextId) {
    showError('Неможливо відправити повідомлення без товару');
    return;
  }

  try {
    const hadImage = !!selectedFile.value;

    await messageStore.sendMessage({
      conversationId,
      body: draft.value,
      imageFile: selectedFile.value,
    });

    draft.value = '';
    resetSelectedFile();
    await scrollToBottom({ force: true });
    updateIsNearBottom();

    if (hadImage) {
      pendingScrollToBottomForImages.value = true;
      setTimeout(() => {
        if (pendingScrollToBottomForImages.value) {
          scheduleScrollToBottomForce();
          pendingScrollToBottomForImages.value = false;
        }
      }, 300);
    }
  } catch {
    showError('Не вдалося надіслати повідомлення');
  }
}

async function deleteMyMessage(messageId) {
  try {
    await apiClient.getCsrfCookie();
    await apiClient.post(`/messages/${messageId}/delete-by-author`);

    const conversationId = messageStore.currentConversation?.id;
    if (conversationId) {
      await messageStore.loadMessages(conversationId);
      await nextTick();
      await scrollToBottom({ force: false });
    }
  } catch {
    showError('Не вдалося видалити повідомлення');
  }
}

const reportDialog = ref({
  open: false,
  loading: false,
  reason: '',
});

function openReportDialog() {
  reportDialog.value.reason = '';
  reportDialog.value.open = true;
}

function closeReportDialog() {
  reportDialog.value.open = false;
  reportDialog.value.reason = '';
}

async function confirmReport() {
  const conversationId = messageStore.currentConversation?.id;
  if (!conversationId) return;

  reportDialog.value.loading = true;
  try {
    await apiClient.getCsrfCookie();
    await apiClient.post(`/conversations/${conversationId}/report`, {
      reason: reportDialog.value.reason?.trim() || null,
    });

    await messageStore.refreshCurrentConversation();
    await loadReportDetails();
    closeReportDialog();
  } catch {
    showError('Не вдалося надіслати скаргу');
  } finally {
    reportDialog.value.loading = false;
  }
}

watch(
  () => messageStore.drawerOpen,
  async (open) => {
    if (open) {
      didInitialScroll = false;
      reportInfoOpen.value = false;
      reportDetails.value = [];
      await nextTick();
      updateIsNearBottom();
      startPresencePing();
    } else {
      stopPresencePing();
      reportInfoOpen.value = false;
      reportDetails.value = [];
    }
  }
);

watch(
  () => messageStore.currentConversation?.id,
  () => {
    reportInfoOpen.value = false;
    reportDetails.value = [];
  }
);

watch(
  () => messageStore.currentConversation?.reports_open_count,
  async () => {
    if (reportInfoOpen.value) {
      await loadReportDetails();
    }
  }
);

watch(
  () => messageStore.loadingMessages,
  async (loading) => {
    if (loading) return;
    if (!messageStore.drawerOpen) return;

    if (!didInitialScroll) {
      didInitialScroll = true;
      await scrollToBottom({ force: true });
      updateIsNearBottom();
    }
  }
);

watch(
  () => messageStore.messages.length,
  async () => {
    await scrollToBottom({ force: false });
  }
);

onUnmounted(() => stopPresencePing());
</script>

<style scoped>
.chat-body {
  height: calc(100% - 210px);
  overflow: auto;
}

.message-row {
  display: flex;
  margin-bottom: 10px;
}
.message-row.mine {
  justify-content: flex-end;
}

.date-divider {
  position: relative;
  text-align: center;
  margin: 14px 0;
  color: rgba(160, 174, 192, 0.95);
  font-weight: 700;
  font-size: 12px;
  letter-spacing: 0.03em;
}
.date-divider::before,
.date-divider::after {
  content: '';
  position: absolute;
  top: 50%;
  width: 42%;
  height: 1px;
  background: rgba(127, 127, 127, 0.28);
}
.date-divider::before { left: 0; }
.date-divider::after { right: 0; }
.date-divider span { padding: 0 8px; }

.bubble {
  max-width: 80%;
  background: #f3f4f6;
  color: #111827;
  border-radius: 12px;
  padding: 10px 12px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  position: relative;
}
.bubble--with-actions {
  padding-right: 34px;
}
.bubble-actions {
  position: absolute;
  right: 4px;
  top: 2px;
  z-index: 2;
}

.message-row.mine .bubble {
  background: #1976D2;
  color: #ffffff;
}

.images .v-img {
  background: white;
}

.meta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  opacity: 0.75;
  white-space: nowrap;
  width: 100%;
}
.message-row:not(.mine) .meta {
  justify-content: flex-start;
}
.message-row.mine .meta {
  justify-content: flex-end;
  opacity: 0.9;
  color: rgba(255,255,255,0.85);
}

.meta-time { font-weight: 600; }
.meta-date { font-weight: 400; opacity: 0.9; }
.meta-sep  { opacity: 0.6; }

.meta-status {
  margin-left: 6px;
  display: inline-flex;
  align-items: center;
}

.deleted-message {
  font-style: italic;
  opacity: 0.78;
}

.d-none {
  display: none;
}

.seller-status--online {
  color: #16a34a;
  font-weight: 600;
}

.chat-image {
  cursor: zoom-in;
}

.report-toggle-btn {
  text-transform: none !important;
  font-weight: 700;
  color: #fff !important;
}

.report-info-sheet {
  background: rgba(46, 125, 50, 0.04);
  border-color: rgba(46, 125, 50, 0.25) !important;
}
</style>