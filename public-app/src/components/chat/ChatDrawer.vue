//public-app/src/components/chat/ChatDrawer.vue
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

      <v-btn icon @click="drawerModel = false" aria-label="Закрити">
        <v-icon>mdi-close</v-icon>
      </v-btn>
    </v-sheet>

    <v-divider />

    <div ref="chatBodyEl" class="chat-body" @scroll.passive="updateIsNearBottom">
      <div v-if="messageStore.loadingMessages" class="pa-4">
        <v-skeleton-loader type="paragraph" />
      </div>

      <div v-else class="messages pa-4">
        <div
          v-for="m in messageStore.messages"
          :key="m.id"
          class="message-row"
          :class="{ 'mine': isMine(m) }"
        >
          <div class="bubble">
            <div v-if="m.body" class="mb-2" style="white-space: pre-wrap;">{{ m.body }}</div>

            <div v-if="m.images && m.images.length" class="images">
              <v-img
                v-for="(img, idx) in m.images"
                :key="img.id"
                :src="img.url"
                max-width="240"
                class="mb-2 rounded chat-image"
                cover
                @click="openChatLightbox(m.images, idx)"
                @load="onAnyChatImageLoad"
              />
            </div>

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

              <!-- image -->
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
    </div>
  </v-navigation-drawer>
</template>

<script setup>
import { ref, computed, watch, onUnmounted, nextTick } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useMessageStore } from '@/stores/messageStore';

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

// пользователь сейчас “у низа”?
const isNearBottom = ref(true);

// чтобы при открытии чата 1 раз проскроллить вниз после загрузки
let didInitialScroll = false;

function updateIsNearBottom() {
  const el = chatBodyEl.value;
  if (!el) return;
  const threshold = 80; // px
  isNearBottom.value = (el.scrollTop + el.clientHeight) >= (el.scrollHeight - threshold);
}

async function scrollToBottom({ force = false } = {}) {
  await nextTick();
  const el = chatBodyEl.value;
  if (!el) return;

  // если пользователь читает историю (не внизу) — не мешаем
  if (!force && !isNearBottom.value) return;

  el.scrollTop = el.scrollHeight;
}

const pendingScrollToBottomForImages = ref(false);

function scheduleScrollToBottomForce() {
  // 2 кадра — чтобы дождаться пересчёта layout после загрузки изображения
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      scrollToBottom({ force: true });
      updateIsNearBottom();
    });
  });
}

function onAnyChatImageLoad() {
  // Скроллим только если это наш “режим после отправки фото”
  // или если пользователь сейчас внизу (чтобы новые картинки не прыгали, когда он читает историю)
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
  return (Date.now() - last) < 5 * 60 * 1000; // 5 min
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
  const urls = (images || [])
    .map(x => x?.url)
    .filter(Boolean);

  if (!urls.length) return;

  chatLightbox.value.urls = urls;
  chatLightbox.value.index = Math.max(0, Math.min(startIndex, urls.length - 1));
  chatLightbox.value.open = true;
}

function formatTimeHHmm(iso) {
  const { time } = formatTimeParts(iso);
  return time;
}

function formatDateDDMMYYYY(iso) {
  const { date } = formatTimeParts(iso);
  return date;
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

  return `${y}-${m}-${d}`; // YYYY-MM-DD
}

function isSameDayInTz(aIso, bDate = new Date(), timeZone) {
  const tz = timeZone || CHAT_TIME_ZONE;
  if (!aIso) return false;
  const a = new Date(aIso);
  return formatYMDInTz(a, tz) === formatYMDInTz(bDate, tz);
}

function formatLastSeenText(lastSeenAtIso) {
  if (!lastSeenAtIso) return 'був(ла) в мережі давно';

  const time = formatTimeHHmm(lastSeenAtIso);

  // today => "сьогодні о 22:38"
  if (isSameDayInTz(lastSeenAtIso)) {
    return `був(ла) в мережі сьогодні о ${time}`;
  }

  // not today => "08.04.2026 о 22:38"
  const date = formatDateDDMMYYYY(lastSeenAtIso);
  return `був(ла) в мережі ${date} о ${time}`;
}

function isMine(m) {
  return m.sender_id === authStore.user?.id;
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

  // Только изображения
  if (!allowedMime.has(file.type) || !allowedExt.has(ext)) {
    showError('Можна прикріпити лише зображення (JPG, PNG або WEBP).');
    resetSelectedFile(e);
    return;
  }

  // 4MB
  if (file.size > 4 * 1024 * 1024) {
    showError('Максимальний розмір фото: 4 MB');
    resetSelectedFile(e);
    return;
  }

  selectedFile.value = file;
}

// presence ping loop while drawer open
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

async function onSend() {
  if (!canSend.value) return;

  const conversationId = messageStore.currentConversation?.id;
  if (!conversationId) return;

  // (дублируем причину canSend для случая Enter/программного вызова)
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

    // 2) если было фото — дождёмся @load у v-img и доскроллим ещё раз до конца
    if (hadImage) {
      pendingScrollToBottomForImages.value = true;

      // fallback: даже если @load не сработает (кеш/ошибка) — через 300мс всё равно доскроллим
      setTimeout(() => {
        if (pendingScrollToBottomForImages.value) {
          scheduleScrollToBottomForce();
          pendingScrollToBottomForImages.value = false;
        }
      }, 300);
    }
  } catch (e) {
    showError('Не вдалося надіслати повідомлення');
  }
}

function stopPresencePing() {
  if (pingTimer) window.clearInterval(pingTimer);
  pingTimer = null;
}

watch(
  () => messageStore.drawerOpen,
  async (open) => {
    if (open) {
      didInitialScroll = false;   // чтобы при каждом открытии 1 раз скроллить вниз
      await nextTick();
      updateIsNearBottom();
      startPresencePing();
    } else {
      stopPresencePing();
    }
  }
);

watch(
  () => messageStore.loadingMessages,
  async (loading) => {
    if (loading) return;
    if (!messageStore.drawerOpen) return;

    // при открытии чата один раз прокручиваем вниз после первой загрузки
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

    .bubble {
      max-width: 80%;
      background: #f3f4f6;
      color: #111827;
      border-radius: 12px;
      padding: 10px 12px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }

    .message-row.mine .bubble {
      background: #1976D2;        /* синий */
      color: #ffffff;
    }

    .images .v-img {
      background: white;
    }

    /* meta как строка */
    .meta {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      opacity: 0.75;
      white-space: nowrap;

      /* ✅ занимает всю ширину bubble, чтобы можно было выравнивать */
      width: 100%;
    }

    /* ✅ для продавца (не mine) — по левому краю */
    .message-row:not(.mine) .meta {
      justify-content: flex-start;
    }

    /* ✅ для покупателя (mine) — по правому краю */
    .message-row.mine .meta {
      justify-content: flex-end;
    }

    /* стили частей */
    .meta-time { font-weight: 600; }
    .meta-date { font-weight: 400; opacity: 0.9; }
    .meta-sep  { opacity: 0.6; }

    .meta-status {
      margin-left: 6px;
      display: inline-flex;
      align-items: center;
    }

    /* если у "моих" сообщений темный фон и белый текст */
    .message-row.mine .meta {
      opacity: 0.9;
      color: rgba(255,255,255,0.85);
    }

    .d-none { 
      display: none; 
    }

    .seller-status--online {
      color: #16a34a; /* green-600 */
      font-weight: 600;
    }

    .chat-image {
      cursor: zoom-in;
    }

</style>