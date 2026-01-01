<template>
  <v-app>
    <!-- Бокове ліве меню -->
    <v-navigation-drawer v-model="drawer" temporary>
      <v-list>
        <v-list-item title="Главная"></v-list-item>
        <v-list-item title="О нас"></v-list-item>
        <v-list-item title="Контакты"></v-list-item>
      </v-list>
    </v-navigation-drawer>

    <!-- Бокове праве меню -->
    <v-navigation-drawer v-model="isProfileDrawerOpen" location="right" temporary>
       <v-list nav>
        <v-list-item
          :title="authStore.user?.name"
          :subtitle="authStore.user?.email"
          class="mb-2"
        >
          <template v-slot:prepend>
            <v-avatar color="primary">
              <v-icon icon="mdi-account-circle"></v-icon>
            </v-avatar>
          </template>
        </v-list-item>
        <v-divider></v-divider>
        
        <template v-for="item in userMenuItems" :key="item.title">
          <v-list-item
            v-if="shouldShowItem(item)"
            :prepend-icon="item.icon"
            :title="item.title"
            :href="item.href" 
          ></v-list-item>
        </template>

        <v-divider></v-divider>

        <v-list-item
          prepend-icon="mdi-logout"
          title="Выход"
          @click="authStore.logout"
        ></v-list-item>
      </v-list>
    </v-navigation-drawer>

    <!-- Верхній тулбар с кнопкой створення об'яви і номерами телефонів -->
    <v-app-bar app :color="isDark ? 'grey-darken-3' : 'grey-lighten-4'" height="40" flat>
      <v-container class="d-flex align-center py-0">
        <v-btn 
          prepend-icon="mdi-plus-circle-outline" 
          variant="text" 
          size="small"
          @click="handleAddProductClick">
          Добавить товар
        </v-btn>
        <v-spacer></v-spacer>
        <v-menu open-on-hover>
          <template v-slot:activator="{ props }">
            <v-btn v-bind="props" variant="text" size="small" append-icon="mdi-chevron-down">
              <v-icon size="small" class="mr-1">mdi-phone</v-icon>
              {{ phoneNumbers[0] }}
            </v-btn>
          </template>
          <v-list dense>
            <v-list-item v-for="phone in phoneNumbers.slice(1)" :key="phone">
              <v-list-item-title>{{ phone }}</v-list-item-title>
            </v-list-item>
          </v-list>
        </v-menu>
      </v-container>
    </v-app-bar>

    <!-- Основна шапка -->
    <v-app-bar app :color="isDark ? 'black' : 'white'" flat class="border-b">
      <v-container class="d-flex align-center pa-0">
        <v-app-bar-nav-icon variant="text" @click.stop="drawer = !drawer"></v-app-bar-nav-icon>
        <v-app-bar-title class="font-weight-bold">E-Shop</v-app-bar-title>        
        <!-- Кнопка і меню з категоріями -->
        <v-menu 
          v-model="isMenuOpen"
          open-on-click 
          :close-on-content-click="false"
          offset="14"
        >
          <template v-slot:activator="{ props }">
            <v-btn 
              v-bind="props" 
              :color="isMenuOpen ? 'primary' : 'grey-darken-3'"
              class="rounded-lg mr-4"
              height="48"
              :prepend-icon="isMenuOpen ? 'mdi-close' : 'mdi-apps'"
              variant="flat"
            >
              Каталог
            </v-btn>
          </template>

          <v-card max-width="800" width="100vw">
            <v-row no-gutters>
              <v-col cols="4" class="border-e">
                <v-list density="compact" nav>
                  <v-list-item
                    v-for="category in rootCategories"
                    :key="category.id"
                    :title="category.title"
                    @mouseenter="hoveredCategory = category"
                    :active="hoveredCategory?.id === category.id"
                    active-color="primary"
                  ></v-list-item>
                </v-list>
              </v-col>

              <v-col cols="8">
                <v-list v-if="hoveredCategory && hoveredCategory.children.length" density="compact">
                  
                  <template v-for="(child, index) in hoveredCategory.children" :key="child.id">
                  
                    <v-list-item :ripple="false" class="font-weight-bold my-2">
                      <v-list-item-title>{{ child.title }}</v-list-item-title>
                    </v-list-item>

                    <v-list-item
                      v-for="grandChild in child.children"
                      :key="grandChild.id"
                      :title="grandChild.title"
                      class="subcategory-item"
                    ></v-list-item>
                    
                    <v-divider 
                      v-if="index < hoveredCategory.children.length - 1" 
                      class="my-2"
                    ></v-divider>

                  </template>
                </v-list>
                <div v-else class="d-flex align-center justify-center h-100 text-grey">
                  Нет подкатегорий
                </div>
              </v-col>
            </v-row>
          </v-card>
        </v-menu>

        <v-responsive max-width="500">
          <v-text-field
            density="compact"
            label="Я ищу..."
            variant="solo-filled"
            prepend-inner-icon="mdi-magnify"
            hide-details
            flat
          ></v-text-field>
        </v-responsive>
        
        <v-spacer></v-spacer>

          <!-- Кнопки изменения темы, сообщения, избранное и корзина  -->
          <v-btn
            :icon="isDark ? 'mdi-weather-sunny' : 'mdi-weather-night'"
            variant="text"
            @click="toggleTheme"
          ></v-btn>
          <v-btn icon><v-icon>mdi-bell-outline</v-icon></v-btn>
          <v-btn icon><v-icon>mdi-heart-outline</v-icon></v-btn>
          <CartDrawer />
          <v-divider vertical class="mx-2"></v-divider>
          
          <!-- Если пользователь не авторизирован -->
          <template v-if="!authStore.isAuthenticated">
            <v-btn variant="text" class="mx-1" @click="isLoginDialogOpen = true">
              Войти
            </v-btn>
            <v-btn variant="outlined" class="mx-1" @click="isRegisterDialogOpen = true">
              Зарегистрироваться
            </v-btn>
          </template>

          <!-- Если пользователь авторизирован -->
          <v-avatar v-if="authStore.isAuthenticated" color="primary" size="40" style="cursor: pointer;" @click="isProfileDrawerOpen = true">
            <v-icon icon="mdi-account-circle"></v-icon>
          </v-avatar>

        </v-container>
    </v-app-bar>

    <v-main class="bg-grey-lighten-3">
      <v-container>
        <router-view />
      </v-container>
    </v-main>

    <v-footer :color="isDark ? 'black' : 'grey-darken-4'" class="text-center d-flex flex-column py-4">
    </v-footer>

    <LoginDialog v-model="isLoginDialogOpen" @open-register="openRegisterDialog" @open-forgot-password="openForgotPasswordDialog"/>
    <RegisterDialog v-model="isRegisterDialogOpen" @open-login="openLoginDialog" @open-forgot-password="openForgotPasswordDialog"/>
    <ForgotPasswordDialog v-model="isForgotPasswordDialogOpen" @open-login="openLoginDialog" />
    
  </v-app>
</template>

<style scoped>
.subcategory-item {
  padding-inline-start: 32px !important;
}
</style>

<style>
/* Стили для оверлея диалоговых окон */
.v-overlay__scrim {
  /* Просто устанавливаем красивый полупрозрачный чёрный фон */
  background: rgba(0, 0, 0, 1) !important;
}

.v-overlay{
  --v-overlay-opacity: 0.86 !important;
}
</style>

<script setup>

import { ref, onMounted, onUnmounted, computed, watch} from 'vue';
import { useTheme } from 'vuetify';
import { useCategoryStore } from '@/stores/categoryStore';
import { useAuthStore } from '@/stores/authStore';
import { useCartStore } from '@/stores/cartStore';
import { useRouter, useRoute } from 'vue-router';
import LoginDialog from '@/components/LoginDialog.vue';
import RegisterDialog from '@/components/RegisterDialog.vue';
import ForgotPasswordDialog from '@/components/ForgotPasswordDialog.vue';
import CartDrawer from '@/components/CartDrawer.vue';

const categoryStore = useCategoryStore();
const cartStore = useCartStore();
const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const drawer = ref(false);
const isProfileDrawerOpen = ref(false);
const footerIcons = ref(['mdi-facebook', 'mdi-twitter', 'mdi-linkedin', 'mdi-instagram']);
const phoneNumbers = ref([
  '0 800 123-45-67',
  '044 123-45-67',
  '050 123-45-67',
]);

const isLoginDialogOpen = ref(false);
const isRegisterDialogOpen = ref(false);
const isForgotPasswordDialogOpen = ref(false);

const handleVisibilityChange = () => {
  // Если вкладка стала видимой, перепроверяем статус пользователя
  if (document.visibilityState === 'visible') {
    authStore.revalidateUser();
  }
};

const theme = useTheme();
const isDark = computed(() => theme.global.current.value.dark);

function toggleTheme() {
  theme.global.name.value = isDark.value ? 'light' : 'dark';
}

const isMenuOpen = ref(false);
const hoveredCategory = ref(null);

// --- ДАННЫЕ ДЛЯ МЕНЮ ПОЛЬЗОВАТЕЛЯ ---
const adminPanelUrl = import.meta.env.VITE_ADMIN_SITE_URL;
const userMenuItems = ref([
  {
    title: 'Мои Товары',
    icon: 'mdi-package-variant-closed',
    href: `${adminPanelUrl}/products`, // Ссылка на страницу товаров в админке
    requiredRoles: ['seller', 'user', 'manager', 'wholesaler', 'manufacturer', 'admin'], 
  },
  {
    title: 'Мои Заказы',
    icon: 'mdi-cart-outline',
    href: `${adminPanelUrl}/orders`, // Ссылка на страницу заказов в админке
    requiredRoles: [], // Видят все авторизованные
  },
  {
    title: 'Админ-панель',
    icon: 'mdi-shield-crown',
    href: adminPanelUrl, // Ссылка на корень админки
    requiredRoles: ['admin', 'manager'], 
  },
]);

// --- ФУНКЦИЯ ПРОВЕРКИ РОЛЕЙ (для меню) ---
function shouldShowItem(item) {
  if (!item.requiredRoles || item.requiredRoles.length === 0) {
    return true;
  }
  return item.requiredRoles.some(role => authStore.hasRole(role));
}

function openLoginDialog() {
  isForgotPasswordDialogOpen.value = false;
  isRegisterDialogOpen.value = false;
  isLoginDialogOpen.value = true;
}
function openRegisterDialog() {
  isForgotPasswordDialogOpen.value = false;
  isLoginDialogOpen.value = false;
  isRegisterDialogOpen.value = true;
}

function openForgotPasswordDialog() {
  isLoginDialogOpen.value = false;
  isRegisterDialogOpen.value = false;
  isForgotPasswordDialogOpen.value = true;
}

const rootCategories = computed(() => {
  const categories = categoryStore.categories;
  const categoryMap = {};
  categories.forEach(category => {
    categoryMap[category.id] = { ...category, children: [] };
  });
  categories.forEach(category => {
    if (category.parent_id && categoryMap[category.parent_id]) {
      categoryMap[category.parent_id].children.push(categoryMap[category.id]);
    }
  });
  return Object.values(categoryMap).filter(c => !c.parent_id);
});

// --- ИЗМЕНЁННАЯ ЛОГИКА КНОПКИ "ДОБАВИТЬ ТОВАР" ---
function handleAddProductClick() {
  // Формируем целевой URL для админки
  const adminCreateUrl = `${adminPanelUrl}/products?action=create`;

  if (authStore.isAuthenticated) {
    // Если залогинен - сразу перенаправляем в админку
    window.location.href = adminCreateUrl;
  } else {
    // Если гость - "запоминаем" этот внешний URL и открываем окно входа
    sessionStorage.setItem('redirectAfterLogin', adminCreateUrl);
    isLoginDialogOpen.value = true;
  }
}

watch(isMenuOpen, (isOpen) => {
  if (isOpen && rootCategories.value.length > 0 && !hoveredCategory.value) {
    hoveredCategory.value = rootCategories.value[0];
  }
});

// --- Новая логика для реакции на выход из системы ---
watch(() => authStore.isAuthenticated, (isAuth, wasAuth) => {
  if (wasAuth === true && isAuth === false) {
    if (route.meta.requiresAuth) { // <-- Теперь используется 'route'
      router.push({ name: 'home' });
    }
  }
});

onMounted(() => {
  categoryStore.fetchCategories();
  cartStore.loadCart(); // Загружаем корзину из localStorage при монтировании
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  if (prefersDark) {
    theme.global.name.value = 'dark';
  }
  // 👇 ДОБАВЛЯЕМ "СЛУШАТЕЛЯ" ПРИ ЗАГРУЗКЕ КОМПОНЕНТА 👇
  document.addEventListener('visibilitychange', handleVisibilityChange);
});

// 👇 УДАЛЯЕМ "СЛУШАТЕЛЯ" ПРИ УНИЧТОЖЕНИИ КОМПОНЕНТА (ЧТОБЫ НЕ БЫЛО УТЕЧЕК ПАМЯТИ) 👇
onUnmounted(() => {
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});

</script>