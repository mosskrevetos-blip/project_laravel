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
            :title="item.title"
            :to="item.to"
          ></v-list-item>
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
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useTheme } from 'vuetify';
import { useAuthStore } from '@/stores/authStore';
import { navItems } from '@/navigation/menu.js';

const authStore = useAuthStore();
const drawer = ref(true); 
const rail = ref(false); 
const router = useRouter();

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

watch(() => authStore.isAuthenticated, (isAuth, wasAuth) => {
  // Если произошло событие выхода (было true, стало false)
  if (wasAuth === true && isAuth === false) {
    // Проверяем, что мы не на странице входа
    if (router.currentRoute.value.name !== 'login') {
      // Выполняем тот же код, что и кнопка "Выход"
      authStore.logout();
    }
  }
});

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

onMounted(() => {
  // Логика темы
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  if (prefersDark) {
    theme.global.name.value = 'dark';
  }
  
  // Добавляем слушатель
  document.addEventListener('visibilitychange', handleVisibilityChange);
});

// Добавляем очистку слушателя
onUnmounted(() => {
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});

</script>