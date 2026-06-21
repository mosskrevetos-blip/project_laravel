<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-4">
      <h1 class="text-h5">Повідомлення</h1>
      <v-btn variant="outlined" :loading="loading" @click="loadConversations">
        Оновити
      </v-btn>
    </div>

    <v-tabs v-if="isAdminOrManager" v-model="activeTab" class="mb-4">
      <v-tab value="my">Мої повідомлення</v-tab>
      <v-tab value="all">Всі повідомлення</v-tab>
    </v-tabs>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
      {{ error }}
    </v-alert>

    <v-card>
      <v-list lines="two" class="py-0">
        <TransitionGroup name="list-move" tag="div">
          <div v-for="c in sortedConversations" :key="c.id">
            <v-list-item
              class="conversation-row"
              :class="{
                'conversation-row--unread-dark': showUnreadHighlight(c) && isDark,
                'conversation-row--unread-light': showUnreadHighlight(c) && !isDark
              }"
              @click="toggleConversation(c)"
            >
              <template #prepend>
                <v-avatar size="40" rounded="sm">
                  <v-img
                    v-if="getProductThumb(c)"
                    :src="getProductThumb(c)"
                    cover
                    :class="{ 'product-thumb--inactive': !isProductActive(c.product) }"
                  />
                  <v-icon v-else>mdi-image-outline</v-icon>
                </v-avatar>
              </template>

              <v-list-item-title class="d-flex align-center justify-space-between">
                <span class="d-flex align-center flex-wrap ga-1">
                  <template v-if="isModerationAllTab">
                    <span>{{ c?.buyer?.name || `Покупець #${c?.buyer_id}` }}</span>
                    <span class="between-arrow">↔</span>
                    <span>{{ c?.seller?.name || `Продавець #${c?.seller_id}` }}</span>
                  </template>

                  <template v-else>
                    <span>{{ getCounterpartyName(c) }}</span>
                  </template>

                  <span class="text-grey">·</span>

                  <span v-if="!isProductActive(c.product)" class="inactive-badge">НЕАКТИВНО</span>

                  <a
                    class="product-link"
                    :class="{ 'product-link--inactive': !isProductActive(c.product) }"
                    :href="getPublicProductUrl(c)"
                    target="_blank"
                    rel="noopener noreferrer"
                    @click.stop
                  >
                    {{ c.product?.title || 'Товар' }}
                  </a>
                </span>

                <div class="d-flex align-center ga-2">
                  <v-btn
                    v-if="isModerationAllTab && hasOpenReports(c)"
                    size="x-small"
                    color="red-darken-2"
                    variant="flat"
                    class="report-badge-btn"
                    @click.stop="openReportsModerationDialog(c)"
                  >
                    Скарга
                  </v-btn>

                  <span
                    v-else-if="!isModerationAllTab && hasOpenReports(c)"
                    class="report-badge"
                  >
                    Скарга
                  </span>

                  <v-btn
                    v-else-if="isModerationAllTab && hasOnlyResolvedReports(c)"
                    size="x-small"
                    color="success"
                    variant="flat"
                    class="report-badge-btn report-badge-btn--resolved"
                    @click.stop="openReportsModerationDialog(c)"
                  >
                    Скарга оброблена
                  </v-btn>

                  <span
                    v-else-if="!isModerationAllTab && hasOnlyResolvedReports(c)"
                    class="report-badge report-badge--resolved"
                  >
                    Скарга оброблена
                  </span>

                  <v-tooltip text="Поскаржитися" location="top">
                    <template #activator="{ props }">
                      <v-btn
                        v-bind="props"
                        icon
                        size="small"
                        variant="text"
                        color="orange-darken-2"
                        @click.stop="openReportDialog(c)"
                      >
                        <v-icon>mdi-emoticon-angry-outline</v-icon>
                      </v-btn>
                    </template>
                  </v-tooltip>

                  <v-badge
                    v-if="showUnreadHighlight(c)"
                    :content="c.unread_count"
                    color="red"
                    inline
                  />
                </div>
              </v-list-item-title>

              <v-list-item-subtitle
                :class="showUnreadHighlight(c)
                  ? (isDark ? 'subtitle-unread-dark' : 'subtitle-unread-light')
                  : ''"
              >
                Оновлено: {{ formatDateTime(c.updated_at) }}
              </v-list-item-subtitle>
            </v-list-item>

            <v-expand-transition>
              <div v-if="openedConversationId === c.id" class="chat-expand pa-3">
                <v-sheet rounded border class="chat-shell">
                  <div :ref="setChatBodyRef(c.id)" class="chat-body pa-3">
                    <div v-if="loadingMessagesMap[c.id]" class="py-4">
                      <v-skeleton-loader type="paragraph" />
                    </div>

                    <template v-else>
                      <template v-for="(m, idx) in (messagesMap[c.id] || [])" :key="m.id">
                        <div v-if="shouldShowDateDivider(messagesMap[c.id], idx)" class="date-divider">
                          <span>{{ formatDividerDate(m.created_at) }}</span>
                        </div>

                        <div :id="`msg-${c.id}-${m.id}`" class="message-row" :class="{ mine: isMine(m, c) }">
                          <div class="bubble" :class="{ 'bubble--with-actions': isMine(m, c) && !m.deleted_by_user }">
                            <div v-if="isModerationAllTab" class="msg-author">
                              {{ m.sender_id === c.seller_id ? 'Продавець' : 'Покупець' }}
                            </div>

                            <div class="bubble-actions" v-if="isMine(m, c) && !m.deleted_by_user">
                              <v-menu location="bottom end">
                                <template #activator="{ props }">
                                  <v-btn v-bind="props" icon size="x-small" variant="text" @click.stop>
                                    <v-icon size="16">mdi-dots-vertical</v-icon>
                                  </v-btn>
                                </template>

                                <v-list density="compact">
                                  <v-list-item @click="deleteMyMessage(m.id, c.id)">
                                    <template #prepend>
                                      <v-icon size="18">mdi-delete-outline</v-icon>
                                    </template>
                                    <v-list-item-title>Видалити</v-list-item-title>
                                  </v-list-item>
                                </v-list>
                              </v-menu>
                            </div>

                            <div v-if="m.deleted_by_user" class="deleted-message deleted-message--soft">
                              Повідомлення видалено користувачем
                            </div>

                            <div v-if="m.body" class="mb-2" style="white-space: pre-wrap;">{{ m.body }}</div>

                            <div v-if="m.images?.length" class="images">
                              <v-img
                                v-for="img in m.images"
                                :key="img.id"
                                :src="img.url"
                                max-width="240"
                                class="mb-2 rounded chat-image"
                                cover
                                @click="openImagePreview(img.url)"
                              />
                            </div>

                            <div class="meta mt-1">
                              <span class="meta-time">{{ formatOnlyTime(m.created_at) }}</span>
                              <span v-if="isMine(m, c)" class="meta-status">
                                <v-icon size="14">{{ m.read_at ? 'mdi-check-all' : 'mdi-check' }}</v-icon>
                              </span>
                            </div>
                          </div>
                        </div>
                      </template>

                      <div v-if="!(messagesMap[c.id] || []).length" class="text-grey py-4">
                        Повідомлень поки немає
                      </div>
                    </template>
                  </div>

                  <v-divider />

                  <div class="pa-3">
                    <v-textarea
                      v-model="drafts[c.id]"
                      label="Повідомлення"
                      rows="2"
                      auto-grow
                      variant="outlined"
                      density="compact"
                      hide-details
                      @keydown.enter.exact.prevent="sendMessage(c)"
                    />

                    <div class="d-flex align-center mt-2">
                      <v-btn variant="text" @click.stop="pickImage(c.id)" :disabled="sendingMap[c.id]">
                        <v-icon class="mr-1">mdi-image</v-icon>
                        Фото
                      </v-btn>

                      <input
                        :ref="setFileInputRef(c.id)"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="d-none"
                        @change="onFileSelected(c.id, $event)"
                      />

                      <div v-if="selectedFiles[c.id]" class="text-caption text-grey ml-2">
                        {{ selectedFiles[c.id].name }}
                      </div>

                      <v-spacer />

                      <v-btn
                        color="primary"
                        :loading="sendingMap[c.id]"
                        :disabled="sendingMap[c.id] || (!((drafts[c.id] || '').trim()) && !selectedFiles[c.id])"
                        @click.stop="sendMessage(c)"
                      >
                        Надіслати
                      </v-btn>
                    </div>
                  </div>
                </v-sheet>
              </div>
            </v-expand-transition>
          </div>
        </TransitionGroup>

        <v-list-item v-if="!loading && sortedConversations.length === 0">
          <v-list-item-title class="text-grey">Діалогів поки немає</v-list-item-title>
        </v-list-item>
      </v-list>
    </v-card>

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

    <v-dialog v-model="reportsModerationDialog.open" max-width="1100">
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
          <span class="text-h6">Історія скарг по діалогу</span>
          <div class="text-caption text-grey">
            Усього: {{ reportsModerationDialog.total_count }} · Відкритих: {{ reportsModerationDialog.open_count }}
          </div>
        </v-card-title>

        <v-divider />

        <v-card-text>
          <div v-if="reportsModerationDialog.loading" class="py-4">
            <v-skeleton-loader type="table" />
          </div>

          <template v-else>
            <v-table density="comfortable">
              <thead>
                <tr>
                  <th>Дата</th>
                  <th>Від кого</th>
                  <th>Причина</th>
                  <th>Статус</th>
                  <th>Обробив</th>
                  <th>Оброблено</th>
                  <th>Коментар модератора</th>
                  <th>Дія</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in reportsModerationDialog.items" :key="r.id">
                  <td>{{ formatDateTime(r.created_at) }}</td>
                  <td>{{ r.reporter?.name || `User #${r.reporter_id}` }}</td>
                  <td style="max-width: 240px;"><div style="white-space: pre-wrap;">{{ r.reason || '—' }}</div></td>
                  <td>
                    <v-chip size="x-small" :color="r.status === 'resolved' ? 'success' : 'warning'" variant="tonal">
                      {{ r.status === 'resolved' ? 'Оброблено' : 'Відкрита' }}
                    </v-chip>
                  </td>
                  <td>{{ r.resolver?.name || '—' }}</td>
                  <td>{{ r.resolved_at ? formatDateTime(r.resolved_at) : '—' }}</td>
                  <td style="max-width: 260px;"><div style="white-space: pre-wrap;">{{ r.resolution_note || '—' }}</div></td>
                  <td>
                    <template v-if="r.status !== 'resolved'">
                      <v-btn
                        size="x-small"
                        color="success"
                        variant="tonal"
                        :loading="resolvePanel.loading && resolvePanel.report?.id === r.id"
                        @click="openResolvePanel(r)"
                      >
                        Обробити
                      </v-btn>
                    </template>
                    <span v-else class="text-grey">—</span>
                  </td>
                </tr>
              </tbody>
            </v-table>

            <div v-if="!reportsModerationDialog.items.length" class="text-grey py-3">
              Скарг поки немає
            </div>

            <template v-if="resolvePanel.open">
              <v-divider class="my-4" />

              <div class="text-subtitle-1 mb-2">Повідомлення діалогу</div>
              <v-sheet rounded border class="pa-2 mb-4 reports-chat-preview">
                <div v-if="resolvePanel.chatLoading" class="py-3">
                  <v-skeleton-loader type="paragraph" />
                </div>
                <template v-else>
                  <div
                    v-for="m in resolvePanel.chatMessages"
                    :key="`rp-msg-${m.id}`"
                    class="reports-chat-row"
                    :class="{ 'reports-chat-row--mine': isResolveChatMine(m) }"
                  >
                    <div class="reports-chat-bubble">
                      <div class="reports-chat-meta">
                        <strong>{{ m.sender_id === resolvePanel.conversation?.seller_id ? 'Продавець' : 'Покупець' }}</strong>
                        · {{ formatDateTime(m.created_at) }}
                      </div>

                      <div v-if="m.deleted_by_user" class="reports-chat-deleted">
                        Повідомлення видалено користувачем
                      </div>

                      <div v-if="m.body" style="white-space: pre-wrap;">{{ m.body }}</div>

                      <div v-if="m.images?.length" class="d-flex flex-wrap ga-2 mt-2">
                        <v-img
                          v-for="img in m.images"
                          :key="`rp-img-${img.id}`"
                          :src="img.url"
                          width="110"
                          height="80"
                          cover
                          class="rounded"
                          @click="openImagePreview(img.url)"
                        />
                      </div>
                    </div>
                  </div>

                  <div v-if="!resolvePanel.chatMessages.length" class="text-grey py-2">
                    Повідомлень у діалозі немає
                  </div>
                </template>
              </v-sheet>

              <div class="text-subtitle-2 mb-2">Відповідь адміністратора / менеджера</div>
              <v-textarea
                v-model="resolvePanel.resolutionNote"
                label="Коментар при обробці скарги"
                rows="3"
                auto-grow
                variant="outlined"
                density="compact"
                maxlength="2000"
                counter
              />

              <div class="d-flex justify-end ga-2 mt-3">
                <v-btn variant="text" @click="closeResolvePanel">Скасувати</v-btn>
                <v-btn color="success" variant="flat" :loading="resolvePanel.loading" @click="confirmResolveFromPanel">
                  Обробити
                </v-btn>
              </div>
            </template>
          </template>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeReportsModerationDialog">Закрити</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="imagePreview.open" max-width="1100">
      <v-card class="bg-black">
        <v-toolbar density="comfortable" color="black">
          <v-spacer />
          <v-btn icon variant="text" @click="closeImagePreview">
            <v-icon color="white">mdi-close</v-icon>
          </v-btn>
        </v-toolbar>

        <v-card-text class="d-flex justify-center align-center pa-2" style="min-height: 60vh;">
          <v-img
            v-if="imagePreview.url"
            :src="imagePreview.url"
            contain
            max-height="80vh"
            class="w-100"
          />
        </v-card-text>
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
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useTheme } from 'vuetify';
import apiClient from '@/api';
import { useAuthStore } from '@/stores/authStore';

const theme = useTheme();
const isDark = computed(() => theme.global.current.value.dark);
const authStore = useAuthStore();

const CHAT_TIME_ZONE = 'Europe/Kyiv';

const loading = ref(false);
const error = ref('');
const conversations = ref([]);

const isAdminOrManager = computed(() => authStore.hasRole('admin') || authStore.hasRole('manager'));
const activeTab = ref('my');

const openedConversationId = ref(null);
const messagesMap = ref({});
const loadingMessagesMap = ref({});
const chatBodyRefs = ref({});
const seenUnreadIds = ref(new Set());

const drafts = ref({});
const sendingMap = ref({});
const selectedFiles = ref({});
const fileInputRefs = ref({});

const frozenOrder = ref(null);

const reportDialog = ref({
  open: false,
  loading: false,
  conversation: null,
  reason: '',
});

const reportsModerationDialog = ref({
  open: false,
  loading: false,
  conversationId: null,
  items: [],
  total_count: 0,
  open_count: 0,
});

const resolvePanel = ref({
  open: false,
  loading: false,
  chatLoading: false,
  report: null,
  conversation: null,
  chatMessages: [],
  resolutionNote: '',
});

const imagePreview = ref({
  open: false,
  url: '',
});

const snackbar = ref({
  open: false,
  text: '',
  color: 'info',
  timeout: 3200,
});

function showSnackbar(text, color = 'info', timeout = 3200) {
  snackbar.value.text = text;
  snackbar.value.color = color;
  snackbar.value.timeout = timeout;
  snackbar.value.open = true;
}

const isModerationAllTab = computed(() => isAdminOrManager.value && activeTab.value === 'all');

watch(activeTab, async () => {
  openedConversationId.value = null;
  frozenOrder.value = null;
  messagesMap.value = {};
  await loadConversations();
});

function setChatBodyRef(conversationId) {
  return (el) => {
    if (el) chatBodyRefs.value[conversationId] = el;
  };
}

function setFileInputRef(conversationId) {
  return (el) => {
    if (el) fileInputRefs.value[conversationId] = el;
  };
}

function reportsOpenCount(c) {
  return Number(c?.reports_open_count || 0);
}

function reportsTotalCount(c) {
  return Number(c?.reports_total_count || 0);
}

function hasOpenReports(c) {
  return reportsOpenCount(c) > 0;
}

function hasOnlyResolvedReports(c) {
  return reportsTotalCount(c) > 0 && reportsOpenCount(c) === 0;
}

function isResolveChatMine(m) {
  return m.sender_id === resolvePanel.value.conversation?.seller_id;
}

const baseSortedConversations = computed(() => {
  // Вкладка "Всі повідомлення" (модерация): приоритет по жалобам
  if (isModerationAllTab.value) {
    const rank = (c) => {
      const open = reportsOpenCount(c);      // не обработанные
      const total = reportsTotalCount(c);    // все жалобы

      if (open > 0) return 0;  // 1) вверху: есть открытые жалобы
      if (total > 0) return 1; // 2) далее: жалобы есть, но все обработаны
      return 2;                // 3) внизу: жалоб нет
    };

    return [...conversations.value].sort((a, b) => {
      const ra = rank(a);
      const rb = rank(b);
      if (ra !== rb) return ra - rb;

      // внутри группы: непрочитанные выше
      const aUnread = showUnreadHighlight(a) ? 1 : 0;
      const bUnread = showUnreadHighlight(b) ? 1 : 0;
      if (aUnread !== bUnread) return bUnread - aUnread;

      // затем по дате обновления
      const aTs = a.updated_at ? new Date(a.updated_at).getTime() : 0;
      const bTs = b.updated_at ? new Date(b.updated_at).getTime() : 0;
      return bTs - aTs;
    });
  }

  // Вкладка "Мої повідомлення": стара логика (приоритет непрочитанных)
  return [...conversations.value].sort((a, b) => {
    const aUnread = showUnreadHighlight(a) ? 1 : 0;
    const bUnread = showUnreadHighlight(b) ? 1 : 0;
    if (aUnread !== bUnread) return bUnread - aUnread;

    const aTs = a.updated_at ? new Date(a.updated_at).getTime() : 0;
    const bTs = b.updated_at ? new Date(b.updated_at).getTime() : 0;
    return bTs - aTs;
  });
});

const sortedConversations = computed(() => {
  if (!frozenOrder.value) return baseSortedConversations.value;

  const map = new Map(conversations.value.map(c => [c.id, c]));
  const ordered = frozenOrder.value.map(id => map.get(id)).filter(Boolean);
  const rest = baseSortedConversations.value.filter(c => !frozenOrder.value.includes(c.id));
  return [...ordered, ...rest];
});

function showUnreadHighlight(c) {
  return (c.unread_count || 0) > 0;
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

function formatOnlyTime(iso) {
  if (!iso) return '';
  return new Intl.DateTimeFormat('uk-UA', {
    timeZone: CHAT_TIME_ZONE,
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso));
}

function formatYMD(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: CHAT_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(d);

  const y = parts.find(p => p.type === 'year')?.value;
  const m = parts.find(p => p.type === 'month')?.value;
  const day = parts.find(p => p.type === 'day')?.value;
  return `${y}-${m}-${day}`;
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

function getCounterpartyName(c) {
  const me = authStore.user?.id;
  if (!me) return c?.seller?.name || c?.buyer?.name || 'Користувач';

  if (isModerationAllTab.value) {
    const buyer = c?.buyer?.name || `Покупець #${c?.buyer_id}`;
    const seller = c?.seller?.name || `Продавець #${c?.seller_id}`;
    return `${buyer} ↔ ${seller}`;
  }

  if (c.buyer_id === me) return c?.seller?.name || `Продавець #${c.seller_id}`;
  if (c.seller_id === me) return c?.buyer?.name || `Покупець #${c.buyer_id}`;
  return c?.seller?.name || c?.buyer?.name || 'Користувач';
}

function isMine(m, conversation) {
  if (isModerationAllTab.value) {
    return m.sender_id === conversation?.seller_id;
  }
  return m.sender_id === authStore.user?.id;
}

function getPublicProductUrl(c) {
  const publicUrl = (import.meta.env.VITE_PUBLIC_SITE_URL || '').replace(/\/$/, '');
  const id = c?.product?.id;
  const slug = c?.product?.slug;
  if (!id) return '#';
  return slug ? `${publicUrl}/product/${id}-${slug}` : `${publicUrl}/product/${id}`;
}

function normalizeImageUrl(url) {
  if (!url) return '';
  if (/^https?:\/\//i.test(url)) return url;

  const origin = window.location.origin;
  if (url.startsWith('/')) return `${origin}${url}`;
  if (url.startsWith('storage/')) return `${origin}/${url}`;
  if (url.startsWith('uploads/')) return `${origin}/storage/${url}`;

  return `${origin}/${url}`;
}

function getProductThumb(c) {
  const p = c?.product || {};

  if (p.image_variants && p.image_url && Array.isArray(p.image_url) && p.image_url.length) {
    const first = p.image_url[0];
    const v150 = p.image_variants?.[first]?.['150']?.webp;
    if (v150) return normalizeImageUrl(v150);
  }

  if (Array.isArray(p.image_url) && p.image_url.length) return normalizeImageUrl(p.image_url[0]);
  if (typeof p.image_url === 'string' && p.image_url.trim()) return normalizeImageUrl(p.image_url);

  return '';
}

function isProductActive(product) {
  if (!product) return false;

  const moderationOk = product.moderation_status === 'approved';
  const isPaidOk = Number(product.is_paid) === 1;
  const isVisibleOk = Number(product.is_visible) === 1;
  const notDeletedByUser = Number(product.deleted_by_user) === 0;
  const notDeletedByAdmin = Number(product.deleted_by_admin) === 0;

  return moderationOk && isPaidOk && isVisibleOk && notDeletedByUser && notDeletedByAdmin;
}

async function loadConversations() {
  loading.value = true;
  error.value = '';
  try {
    const params = {};
    if (isModerationAllTab.value) params.scope = 'all';

    const { data } = await apiClient.get('/conversations', { params });
    conversations.value = Array.isArray(data) ? data : (data.items || []);
  } catch {
    error.value = 'Не вдалося завантажити діалоги';
  } finally {
    loading.value = false;
  }
}

async function loadMessages(conversationId) {
  loadingMessagesMap.value[conversationId] = true;
  try {
    const params = { per_page: 100 };
    if (isModerationAllTab.value) params.scope = 'all';

    const { data } = await apiClient.get(`/conversations/${conversationId}/messages`, { params });
    const items = (data?.data || []).slice().reverse();
    messagesMap.value = { ...messagesMap.value, [conversationId]: items };
  } finally {
    loadingMessagesMap.value[conversationId] = false;
  }
}

async function toggleConversation(c) {
  if (openedConversationId.value === c.id) {
    openedConversationId.value = null;
    frozenOrder.value = null;
    return;
  }

  if (!frozenOrder.value) frozenOrder.value = sortedConversations.value.map(x => x.id);
  openedConversationId.value = c.id;

  if (!messagesMap.value[c.id]) await loadMessages(c.id);

  await nextTick();
  scrollToFirstUnread(c.id);

  if (!isModerationAllTab.value) {
    observeAndMarkRead(c.id);
  }
}

function scrollToFirstUnread(conversationId) {
  const list = messagesMap.value[conversationId] || [];
  const me = authStore.user?.id;
  const firstUnreadIncoming = list.find(m => !m.read_at && m.sender_id !== me);

  const body = chatBodyRefs.value[conversationId];
  if (!body) return;

  if (!firstUnreadIncoming) {
    body.scrollTop = body.scrollHeight;
    return;
  }

  const el = document.getElementById(`msg-${conversationId}-${firstUnreadIncoming.id}`);
  if (!el) return;

  body.scrollTo({ top: el.offsetTop - 12, behavior: 'smooth' });
}

function observeAndMarkRead(conversationId) {
  const body = chatBodyRefs.value[conversationId];
  if (!body) return;

  const unreadIncoming = (messagesMap.value[conversationId] || []).filter(
    m => !m.read_at && m.sender_id !== authStore.user?.id
  );
  if (!unreadIncoming.length) return;

  const onScroll = async () => {
    const visibleUnreadIds = unreadIncoming
      .filter(m => {
        if (seenUnreadIds.value.has(m.id)) return false;
        const el = document.getElementById(`msg-${conversationId}-${m.id}`);
        if (!el) return false;

        const elTop = el.offsetTop;
        const elBottom = elTop + el.offsetHeight;
        const viewTop = body.scrollTop;
        const viewBottom = body.scrollTop + body.clientHeight;
        return elBottom >= viewTop && elTop <= viewBottom;
      })
      .map(m => m.id);

    if (!visibleUnreadIds.length) return;

    const res = await markRead(conversationId, visibleUnreadIds);
    if (!res?.ok) return;

    const nowIso = new Date().toISOString();
    messagesMap.value = {
      ...messagesMap.value,
      [conversationId]: (messagesMap.value[conversationId] || []).map(m =>
        visibleUnreadIds.includes(m.id) ? { ...m, read_at: nowIso } : m
      ),
    };

    visibleUnreadIds.forEach(id => seenUnreadIds.value.add(id));
    await loadConversations();

    const hasUnreadLeft = (messagesMap.value[conversationId] || []).some(
      m => !m.read_at && m.sender_id !== authStore.user?.id
    );
    if (!hasUnreadLeft) body.removeEventListener('scroll', onScroll);
  };

  body.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}

async function markRead(conversationId, messageIds = null) {
  const payload = Array.isArray(messageIds) && messageIds.length ? { message_ids: messageIds } : {};
  await apiClient.getCsrfCookie();

  const params = {};
  if (isModerationAllTab.value) params.scope = 'all';

  const { data } = await apiClient.post(`/conversations/${conversationId}/read`, payload, { params });
  return data;
}

function pickImage(conversationId) {
  fileInputRefs.value[conversationId]?.click();
}

function onFileSelected(conversationId, e) {
  const file = e.target.files?.[0] || null;
  if (!file) return;

  const allowedMime = new Set(['image/jpeg', 'image/png', 'image/webp']);
  const allowedExt = new Set(['jpg', 'jpeg', 'png', 'webp']);
  const ext = (file.name.split('.').pop() || '').toLowerCase();

  if (!allowedMime.has(file.type) || !allowedExt.has(ext)) {
    showSnackbar('Можна прикріпити лише зображення (JPG, PNG або WEBP).', 'warning');
    e.target.value = '';
    return;
  }

  if (file.size > 4 * 1024 * 1024) {
    showSnackbar('Максимальний розмір фото: 4 MB', 'warning');
    e.target.value = '';
    return;
  }

  selectedFiles.value[conversationId] = file;
}

function resetSelectedFile(conversationId) {
  selectedFiles.value[conversationId] = null;
  const input = fileInputRefs.value[conversationId];
  if (input) input.value = '';
}

async function sendMessage(conversation) {
  const conversationId = conversation.id;
  const body = (drafts.value[conversationId] || '').trim();
  const file = selectedFiles.value[conversationId] || null;

  if (!body && !file) return;

  sendingMap.value[conversationId] = true;
  try {
    await apiClient.getCsrfCookie();

    const form = new FormData();
    if (body) form.append('body', body);

    const productId = conversation?.product_id ?? conversation?.product?.id;
    if (productId) {
      form.append('product_id', String(productId));
    }

    if (file) form.append('image', file);

    const { data: created } = await apiClient.post(
      `/conversations/${conversationId}/messages`,
      form,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    );

    const current = messagesMap.value[conversationId] || [];
    messagesMap.value = { ...messagesMap.value, [conversationId]: [...current, created] };

    drafts.value[conversationId] = '';
    resetSelectedFile(conversationId);

    await nextTick();
    const bodyEl = chatBodyRefs.value[conversationId];
    if (bodyEl) bodyEl.scrollTo({ top: bodyEl.scrollHeight, behavior: 'smooth' });

    await loadConversations();
  } catch (e) {
    const msg = e?.response?.data?.message || 'Не вдалося надіслати повідомлення';
    showSnackbar(msg, 'error', 4200);
  } finally {
    sendingMap.value[conversationId] = false;
  }
}

async function deleteMyMessage(messageId, conversationId) {
  try {
    await apiClient.getCsrfCookie();
    await apiClient.post(`/messages/${messageId}/delete-by-author`);
    await loadMessages(conversationId);
    await loadConversations();
  } catch {
    showSnackbar('Не вдалося видалити повідомлення', 'error', 4200);
  }
}

function openReportDialog(c) {
  reportDialog.value.conversation = c;
  reportDialog.value.reason = '';
  reportDialog.value.open = true;
}

function closeReportDialog() {
  reportDialog.value.open = false;
  reportDialog.value.conversation = null;
  reportDialog.value.reason = '';
}

async function confirmReport() {
  const c = reportDialog.value.conversation;
  if (!c?.id) return;

  reportDialog.value.loading = true;
  try {
    await apiClient.getCsrfCookie();
    await apiClient.post(`/conversations/${c.id}/report`, {
      reason: reportDialog.value.reason?.trim() || null,
    });

    await loadConversations();
    closeReportDialog();
    showSnackbar('Скаргу успішно надіслано', 'success');
  } catch (e) {
    const status = e?.response?.status;
    const data = e?.response?.data || {};

    if (status === 409 && data?.already_exists) {
      showSnackbar(data?.message || 'У вас вже є відкрита скарга по цьому діалогу', 'warning', 4200);
      closeReportDialog();
      return;
    }

    showSnackbar(data?.message || 'Не вдалося надіслати скаргу', 'error', 4200);
  } finally {
    reportDialog.value.loading = false;
  }
}

async function openReportsModerationDialog(c) {
  if (!c?.id) return;
  reportsModerationDialog.value.open = true;
  reportsModerationDialog.value.loading = true;
  reportsModerationDialog.value.conversationId = c.id;

  try {
    const { data } = await apiClient.get(`/conversations/${c.id}/reports`);
    reportsModerationDialog.value.items = Array.isArray(data?.items) ? data.items : [];
    reportsModerationDialog.value.total_count = Number(data?.total_count || 0);
    reportsModerationDialog.value.open_count = Number(data?.open_count || 0);
  } catch {
    showSnackbar('Не вдалося завантажити історію скарг', 'error', 4200);
  } finally {
    reportsModerationDialog.value.loading = false;
  }
}

function closeReportsModerationDialog() {
  reportsModerationDialog.value.open = false;
  reportsModerationDialog.value.loading = false;
  reportsModerationDialog.value.conversationId = null;
  reportsModerationDialog.value.items = [];
  reportsModerationDialog.value.total_count = 0;
  reportsModerationDialog.value.open_count = 0;
  closeResolvePanel();
}

async function openResolvePanel(report) {
  resolvePanel.value.open = true;
  resolvePanel.value.report = report;
  resolvePanel.value.resolutionNote = report?.resolution_note || '';
  resolvePanel.value.chatLoading = true;
  resolvePanel.value.chatMessages = [];

  const conversationId = reportsModerationDialog.value.conversationId;
  const conv = conversations.value.find(x => x.id === conversationId) || null;
  resolvePanel.value.conversation = conv;

  try {
    const { data } = await apiClient.get(`/conversations/${conversationId}/messages`, {
      params: { per_page: 100, scope: 'all' },
    });
    resolvePanel.value.chatMessages = (data?.data || []).slice().reverse();
  } catch {
    showSnackbar('Не вдалося завантажити повідомлення діалогу', 'error', 4200);
  } finally {
    resolvePanel.value.chatLoading = false;
  }
}

function closeResolvePanel() {
  resolvePanel.value.open = false;
  resolvePanel.value.loading = false;
  resolvePanel.value.chatLoading = false;
  resolvePanel.value.report = null;
  resolvePanel.value.conversation = null;
  resolvePanel.value.chatMessages = [];
  resolvePanel.value.resolutionNote = '';
}

async function confirmResolveFromPanel() {
  const report = resolvePanel.value.report;
  if (!report?.id) return;

  resolvePanel.value.loading = true;
  try {
    await apiClient.getCsrfCookie();
    await apiClient.post(`/conversation-reports/${report.id}/resolve`, {
      resolution_note: resolvePanel.value.resolutionNote?.trim() || null,
    });

    const cid = reportsModerationDialog.value.conversationId;
    const { data } = await apiClient.get(`/conversations/${cid}/reports`);
    reportsModerationDialog.value.items = Array.isArray(data?.items) ? data.items : [];
    reportsModerationDialog.value.total_count = Number(data?.total_count || 0);
    reportsModerationDialog.value.open_count = Number(data?.open_count || 0);

    await loadConversations();
    closeResolvePanel();
    showSnackbar('Скаргу оброблено', 'success');
  } catch {
    showSnackbar('Не вдалося обробити скаргу', 'error', 4200);
  } finally {
    resolvePanel.value.loading = false;
  }
}

function openImagePreview(url) {
  if (!url) return;
  imagePreview.value.url = url;
  imagePreview.value.open = true;
}

function closeImagePreview() {
  imagePreview.value.open = false;
  imagePreview.value.url = '';
}

onMounted(async () => {
  if (!isAdminOrManager.value) activeTab.value = 'my';
  await loadConversations();
});
</script>

<style scoped>
.conversation-row {
  border-bottom: 1px solid rgba(127, 127, 127, 0.18);
  cursor: pointer;
}
.conversation-row--unread-dark {
  background: #1f3a2a;
}
.conversation-row--unread-dark :deep(.v-list-item-title) {
  color: #e8f5e9 !important;
  font-weight: 600;
}
.subtitle-unread-dark {
  color: rgba(232, 245, 233, 0.82) !important;
}
.conversation-row--unread-light {
  background: #e8f5e9;
}
.conversation-row--unread-light :deep(.v-list-item-title) {
  color: #1b5e20 !important;
  font-weight: 600;
}
.subtitle-unread-light {
  color: rgba(27, 94, 32, 0.78) !important;
}

.product-link {
  text-decoration: underline;
  font-weight: 600;
}

.chat-expand {
  border-bottom: 1px solid rgba(127, 127, 127, 0.18);
}
.chat-shell {
  display: flex;
  flex-direction: column;
  height: 640px;
  overflow: hidden;
}
.chat-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  scroll-behavior: smooth;
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
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
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
  background: #1976d2;
  color: #fff;
}
.msg-author {
  font-size: 11px;
  opacity: 0.72;
  margin-bottom: 4px;
}
.message-row.mine .msg-author {
  text-align: right;
}
.images .v-img {
  background: white;
}
.meta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  opacity: 0.9;
  white-space: nowrap;
  width: 100%;
}
.message-row:not(.mine) .meta {
  justify-content: flex-start;
}
.message-row.mine .meta {
  justify-content: flex-end;
  color: rgba(255, 255, 255, 0.9);
}
.meta-time { font-weight: 600; }
.meta-status {
  margin-left: 6px;
  display: inline-flex;
  align-items: center;
}
.deleted-message {
  font-style: italic;
  opacity: 0.78;
}
.deleted-message--soft {
  color: #fca5a5;
  font-style: italic;
  font-size: 12px;
  margin-bottom: 6px;
}
.chat-image { cursor: zoom-in; }
.d-none { display: none; }

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

.product-link--inactive {
  opacity: 0.95;
}
.product-thumb--inactive {
  filter: grayscale(100%) brightness(1.18) contrast(0.85);
  opacity: 0.72;
}

.inactive-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 6px;
  background: #989ca3;
  color: #232a35;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  line-height: 1.2;
}

.between-arrow {
  display: inline-block;
  position: relative;
  top: -2px;
  margin: 0 4px;
  font-weight: 700;
  opacity: 0.9;
}

.report-badge {
  display: inline-flex;
  align-items: center;
  height: 22px;
  padding: 0 8px;
  border-radius: 6px;
  background: #d32f2f;
  color: #fff;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}
.report-badge--resolved {
  background: #2e7d32;
  color: #fff;
}
.report-badge-btn {
  min-width: auto;
  height: 22px !important;
  padding: 0 8px !important;
  font-size: 11px !important;
  font-weight: 800 !important;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}
.report-badge-btn--resolved {
  background-color: #2e7d32 !important;
  color: #fff !important;
}

.reports-chat-preview {
  max-height: 360px;
  overflow: auto;
  padding: 10px;
  background: rgba(127, 127, 127, 0.04);
}

.reports-chat-row {
  display: flex;
  justify-content: flex-start;
  margin-bottom: 10px;
}

.reports-chat-row--mine {
  justify-content: flex-end;
}

.reports-chat-bubble {
  max-width: 78%;
  padding: 10px 12px;
  border-radius: 12px;
  background: #f3f4f6;
  color: #111827;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.reports-chat-row--mine .reports-chat-bubble {
  background: #1976d2;
  color: #fff;
}

.reports-chat-meta {
  font-size: 12px;
  opacity: 0.78;
  margin-bottom: 4px;
}

.reports-chat-row--mine .reports-chat-meta {
  opacity: 0.9;
}

.reports-chat-deleted {
  color: #fca5a5;
  font-style: italic;
  font-size: 12px;
  margin-bottom: 6px;
}
</style>