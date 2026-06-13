<template>
  <v-app>
  
    <!-- header -->
    <v-app-bar color="primary">
      <v-app-bar-title>Адміністративна панель</v-app-bar-title>
      <v-spacer></v-spacer>

      <v-btn
        :icon="theme.global.current.value.dark ? 'mdi-weather-sunny' : 'mdi-weather-night'"
        variant="text"
        @click="toggleTheme"
      ></v-btn>

      <v-btn v-if="authStore.isAuthenticated" @click="authStore.logout">
        Вихід
      </v-btn>
    </v-app-bar>

    <!-- Бокова панель -->
    <!-- v-if="authStore.isAuthenticated" - якщо користувач авторизован, то панель відображається-->
    <v-navigation-drawer
      v-if="authStore.isAuthenticated"
      v-model="drawer" 
      :rail="rail"
      permanent
    >
      <v-list-item
        prepend-icon="mdi-home-export-outline"
        title="Вернуться на сайт"
        @click="goToPublicSite"
      ></v-list-item>
      
      <v-divider></v-divider>
      <v-list-item
        prepend-icon="mdi-account-cog"
        :title="authStore.user?.name || 'Администратор'"
        nav
      ></v-list-item>

      <v-divider></v-divider>

      <v-list density="compact" nav>
        <template v-for="item in navItems" :key="item.title">
          <v-list-item
            v-if="shouldShowItem(item)"
            :prepend-icon="item.icon"
            :to="item.to"
          >
            <v-list-item-title class="d-flex align-center justify-space-between w-100">
              <span>{{ item.title }}</span>

              <v-badge
                v-if="item.title === 'Повідомлення' && !loadingConversations && unreadThreadsCount > 0"
                :content="unreadThreadsCount"
                color="red"
                inline
              />
            </v-list-item-title>
          </v-list-item>
        </template>
      </v-list>

      <template v-slot:append>
        <div class="pa-2">
          <v-btn
            block
            :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
            variant="text"
            @click.stop="rail = !rail"
          ></v-btn>
        </div>
      </template>

    </v-navigation-drawer>
    
    <v-main>
      <v-container>
        <router-view />
      </v-container>
    </v-main>

  </v-app>
</template>

<script setup>
import apiClient from '@/api';
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useTheme } from 'vuetify';
import { useAuthStore } from '@/stores/authStore';
import { navItems } from '@/navigation/menu.js';


const authStore = useAuthStore();
const drawer = ref(true); 
const rail = ref(false); 
const router = useRouter();
const conversations = ref([]);
const loadingConversations = ref(false);
const unreadThreadsCount = computed(() => {
  return conversations.value.filter(c => (c.unread_count || 0) > 0).length;
});

async function loadConversationsForBadge() {
  if (!authStore.isAuthenticated) {
    conversations.value = [];
    return;
  }

  loadingConversations.value = true;
  try {
    const { data } = await apiClient.get('/conversations');
    conversations.value = Array.isArray(data) ? data : (data.items || []);
  } catch (e) {
    // тихо игнорируем для UI
    conversations.value = [];
  } finally {
    loadingConversations.value = false;
  }
}

let conversationsTimer = null;

function startConversationsPolling() {
  if (conversationsTimer) return;
  conversationsTimer = window.setInterval(() => {
    loadConversationsForBadge();
  }, 30000); // каждые 30 сек
}

function stopConversationsPolling() {
  if (conversationsTimer) window.clearInterval(conversationsTimer);
  conversationsTimer = null;
}

// Получаем URL публичного сайта из переменных окружения
const publicSiteUrl = import.meta.env.VITE_PUBLIC_SITE_URL;

function goToPublicSite() {
  window.location.href = publicSiteUrl;
}

// --- Логика для синхронизации и выхода ---

const handleVisibilityChange = () => {
  // Если вкладка стала видимой, перепроверяем статус
  if (document.visibilityState === 'visible') {
    authStore.revalidateUser();
  }
};

watch(
  () => authStore.isAuthenticated,
  async (isAuth, wasAuth) => {
    if (wasAuth === true && isAuth === false) {
      conversations.value = [];
      stopConversationsPolling();

      if (router.currentRoute.value.name !== 'login') {
        authStore.logout();
      }
      return;
    }

    if (isAuth) {
      await loadConversationsForBadge();
      startConversationsPolling();
    }
  },
  { immediate: true }
);

// Функція для перевірки прав ---
function shouldShowItem(item) {
  // Если для пункта меню не указаны роли, показываем его всем
  if (!item.requiredRoles || item.requiredRoles.length === 0) {
    return true;
  }
  // Иначе, проверяем, есть ли у пользователя хотя бы одна из требуемых ролей
  return item.requiredRoles.some(role => authStore.hasRole(role));
}

//Кирування темою

const theme = useTheme(); // Отримую доступ до керування темою

// Функція для перемикання теми
function toggleTheme() {
  theme.global.name.value = theme.global.name.value === 'dark' ? 'light' : 'dark';
}

onMounted(async () => {
  // Логика темы
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  if (prefersDark) {
    theme.global.name.value = 'dark';
  }

  if (authStore.isAuthenticated) {
    await loadConversationsForBadge();
  }
  
  // Добавляем слушатель
  document.addEventListener('visibilitychange', handleVisibilityChange);
});

// Добавляем очистку слушателя
onUnmounted(() => {
  stopConversationsPolling();
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});

</script>