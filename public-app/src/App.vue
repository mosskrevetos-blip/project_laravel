<template>
  <v-app>
    <!-- Ліве меню -->
    <!-- temporary дозволяє тимчасово відображати меню -->
    <v-navigation-drawer v-model="drawer" temporary>
      <v-list>
        <router-link
            :to="{ name: 'home' }"
            class="no-decoration "
          ><v-list-item title="Головна"></v-list-item>
        </router-link>
        <v-list-item title="Про нас"></v-list-item>
        <v-list-item title="Контакти"></v-list-item>
      </v-list>
    </v-navigation-drawer>

    <!-- Профіль (праве меню) -->
    <v-navigation-drawer v-model="isProfileDrawerOpen" location="right" temporary>
      <v-list nav>
        <!-- Відображаються ім'я та електронна адреса поточного користувача 
        Якщо користувач не аутентифікований (authStore.user === null), то title та subtitle будуть порожніми. -->
        <v-list-item
          :title="authStore.user?.name"
          :subtitle="authStore.user?.email"
          class="mb-2"
        >
          <!-- Слот для відображення аватара -->
          <template v-slot:prepend>
            <v-avatar color="primary">
              <v-icon icon="mdi-account-circle"></v-icon>
            </v-avatar>
          </template>

        </v-list-item>
        <v-divider></v-divider>
        <!-- Проходим циклом по массиву userMenuItems. Кожен елемент масиву - це пункт меню. -->
        <template v-for="item in userMenuItems" :key="item.title">
          <!-- Якщо пункт меню (item) повинен відображатися (shouldShowItem), 
          він відображається з іконкою, заголовком і посиланням. -->
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
          title="Вихід"
          @click="authStore.logout"
        ></v-list-item>
      </v-list>
    </v-navigation-drawer>

    <!-- КОРЗИНА: правий drawer (шире) -->
    <v-navigation-drawer
      v-model="isCartDrawerOpen"
      location="right"
      temporary
      width="480"
    >
      <v-sheet class="d-flex align-center justify-space-between pa-4" elevation="0">
        <div class="text-h6">Кошик</div>
        <v-btn icon @click="isCartDrawerOpen = false" aria-label="Закрити">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-sheet>

      <v-divider></v-divider>

      <!-- Контейнер для відображення списку товарів у кошику -->
      <CartSidebar :isCartDrawerOpen="isCartDrawerOpen" />

    </v-navigation-drawer>

    <!-- Чат-бот -->
    <ChatDrawer />

    <!-- Верхний тулбар (информационный) -->
    <template v-if="route.name !== 'checkout'">
      <v-app-bar app :color="isDark ? 'grey-darken-3' : 'grey-lighten-4'" height="40" flat>
        <v-container class="d-flex align-center py-0">
          <v-btn
            prepend-icon="mdi-plus-circle-outline"
            variant="text"
            size="small"
            @click="handleAddProductClick"
          >
            Додати товар
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
    </template>
    

    <!-- Основна шапка і вміст -->
    <template v-if="route.name !== 'checkout'">
      <v-app-bar app :color="isDark ? 'black' : 'white'" flat class="border-b">
        <v-container class="d-flex align-center pa-0">
          <v-app-bar-nav-icon variant="text" @click.stop="drawer = !drawer"></v-app-bar-nav-icon>
          <!-- Посилання на головну сторінку -->
          <router-link
              :to="{ name: 'home' }"
              class="no-decoration "
            ><v-app-bar-title class="font-weight-bold pr-4">E-Shop</v-app-bar-title>
          </router-link>

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

                        <v-divider v-if="index < hoveredCategory.children.length - 1" class="my-2"></v-divider>
                      </template>
                    </v-list>
                    <div v-else class="d-flex align-center justify-center h-100 text-grey">
                      Нема підкатегорій
                    </div>
                  </v-col>
                </v-row>
              </v-card>
          </v-menu>

          <v-responsive max-width="500">
            <v-text-field
              density="compact"
              label="Я шукаю..."
              variant="solo-filled"
              prepend-inner-icon="mdi-magnify"
              hide-details
              flat
            ></v-text-field>
          </v-responsive>

          <v-spacer></v-spacer>

          <v-btn :icon="isDark ? 'mdi-weather-sunny' : 'mdi-weather-night'" variant="text" @click="toggleTheme"></v-btn>
          <v-btn icon><v-icon>mdi-bell-outline</v-icon></v-btn>
          <!-- Favorite icon -->
          <div class="favorite-container">
            <v-badge
              :content="favoriteStore.totalFavorites"
              color="red"
              overlap
              location="top end"
              v-if="favoriteStore.totalFavorites > 0"
              class="favorite-badge"
            >
              <v-btn icon @click="onFavoriteIconClick" aria-label="Обране" class="favorite-btn-header">
                <v-icon>mdi-heart</v-icon>
              </v-btn>
            </v-badge>

            <v-btn v-else icon @click="onFavoriteIconClick" aria-label="Обране" class="favorite-btn-header">
              <v-icon>mdi-heart-outline</v-icon>
            </v-btn>
          </div>

          <!-- Cart icon with badge -->
          <div class="cart-container">
            <v-badge
              :content="cart.totalItems"
              color="primary"
              overlap
              location="top end"
              v-if="cart.totalItems > 0"
              class="cart-badge"
            >
              <v-btn icon @click="onCartIconClick" aria-label="Кошик" class="cart-btn">
                <v-icon>mdi-cart-outline</v-icon>
              </v-btn>
            </v-badge>

            <v-btn v-else icon @click="onCartIconClick" aria-label="Кошик" class="cart-btn">
              <v-icon>mdi-cart-outline</v-icon>
            </v-btn>
          </div>

          <v-divider vertical class="mx-2"></v-divider>

          <template v-if="!authStore.isAuthenticated">
            <v-btn variant="text" class="mx-1" @click="authStore.openLoginDialog()">Увійти</v-btn>
            <v-btn variant="outlined" class="mx-1" @click="authStore.openRegisterDialog()">Зареєструватися</v-btn>
          </template>

          <v-avatar v-if="authStore.isAuthenticated" color="primary" size="40" style="cursor: pointer;" @click="isProfileDrawerOpen = true">
            <v-icon icon="mdi-account-circle"></v-icon>
          </v-avatar>
        </v-container>
      </v-app-bar>
    </template>
    
    <v-main class="bg-grey-lighten-3">
      <v-container>
        <router-view />
      </v-container>
    </v-main>

    <v-footer :color="isDark ? 'grey-darken-4' : 'grey-lighten-4'" app class="text-center">
      <!-- Верхня частина футера -->
      <v-container class="d-flex justify-space-between align-center">
        <!-- Логотип або назва -->
        <div class="font-weight-bold">
          {{ companyName }}
        </div>

        <!-- Соціальні іконки -->
        <v-btn
          v-for="icon in footerIcons"
          :key="icon"
          icon
          class="mx-1"
          size="small"
          :href="socialLinks[icon]"
          target="_blank"
          rel="noopener noreferrer"
          :aria-label="`Перейти на сайт ${icon}`"
        >
          <v-icon :icon="icon"></v-icon>
        </v-btn>
      </v-container>

      <!-- Нижня частина футера -->
      <v-divider></v-divider>
      <v-container>
        <div>
          &copy; {{ new Date().getFullYear() }} {{ companyName }}. Усі права захищені.
        </div>
      </v-container>
    </v-footer>

    <!-- Діалог: кошик порожній -->
    <v-dialog v-model="emptyCartDialog" max-width="420">
      <v-card>
        <v-card-title>Кошик порожній</v-card-title>
        <v-card-text>Ваш кошик поки порожній. Додайте товари, щоб оформити замовлення.</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn text @click="emptyCartDialog = false">Перейти до покупок</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <LoginDialog
      v-model="authStore.ui.loginDialogOpen"
      @open-register="authStore.openRegisterDialog"
      @open-forgot-password="authStore.openForgotPasswordDialog"
    />

    <RegisterDialog
      v-model="authStore.ui.registerDialogOpen"
      @open-login="authStore.openLoginDialog"
      @open-forgot-password="authStore.openForgotPasswordDialog"
    />

    <ForgotPasswordDialog
      v-model="authStore.ui.forgotPasswordDialogOpen"
      @open-login="authStore.openLoginDialog"
    />

  </v-app>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useTheme } from 'vuetify';
import { useCategoryStore } from '@/stores/categoryStore';
import { useAuthStore } from '@/stores/authStore';
import { useRouter, useRoute } from 'vue-router';
import { useCartStore } from '@/stores/cartStore';
import CartSidebar from './components/cart/CartSidebar.vue';
import LoginDialog from '@/components/LoginDialog.vue';
import RegisterDialog from '@/components/RegisterDialog.vue';
import ForgotPasswordDialog from '@/components/ForgotPasswordDialog.vue';
import { useFavoriteStore } from '@/stores/favoriteStore';
import { useFavoriteSellerStore } from '@/stores/favoriteSellerStore';
import apiClient from '@/api';

import ChatDrawer from '@/components/chat/ChatDrawer.vue';
import { useMessageStore } from '@/stores/messageStore';

const categoryStore = useCategoryStore();
const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
// Посилання на store кошика
const cart = useCartStore();
// Посилання на store обраного
const favoriteStore = useFavoriteStore();

// Посилання на store повідомлень (для чат-бота)
const messageStore = useMessageStore();

// Змінна для стану бічного лівого меню.
const drawer = ref(false);

// Змінна для стану бічного правого меню.
const isProfileDrawerOpen = ref(false);

// Змінна для стану бічного правого меню кошика.
const isCartDrawerOpen = ref(false);

// Назва компанії
const companyName = 'E-Shop';

// Посилання на соціальні мережі
const socialLinks = {
  'mdi-facebook': 'https://facebook.com',
  'mdi-twitter': 'https://twitter.com',
  'mdi-linkedin': 'https://linkedin.com',
  'mdi-instagram': 'https://instagram.com',
};

const phoneNumbers = ref(['0 800 123-45-67', '044 123-45-67', '050 123-45-67']);


const emptyCartDialog = ref(false);

const handleVisibilityChange = () => {
  if (document.visibilityState === 'visible') authStore.revalidateUser();
};

const theme = useTheme();
const isDark = computed(() => theme.global.current.value.dark);

function toggleTheme() {
  theme.global.name.value = isDark.value ? 'light' : 'dark';
}

const isMenuOpen = ref(false);
const hoveredCategory = ref(null);

// Праве меню
// Посилання на адмін-панель
const adminPanelUrl = import.meta.env.VITE_ADMIN_SITE_URL;
// Масив пунктів меню для правого меню
const userMenuItems = ref([
  { title: 'Мої Товари', icon: 'mdi-package-variant-closed', href: `${adminPanelUrl}/products`, requiredRoles: ['seller','user','manager','wholesaler','manufacturer','admin'] },
  { title: 'Мої Замовлення', icon: 'mdi-cart-outline', href: `${adminPanelUrl}/orders`, requiredRoles: [] },
  { title: 'Адмін-панель', icon: 'mdi-shield-crown', href: adminPanelUrl, requiredRoles: ['admin','manager'] },
]);

const favoriteSellerStore = useFavoriteSellerStore();

let presenceTimer = null;

async function pingPresenceGlobal() {
  try {
    await apiClient.getCsrfCookie();
    await apiClient.post('/presence/ping');
  } catch (e) {
    // игнор (401, сеть и т.п.)
  }
}

function startPresenceTimer() {
  if (presenceTimer) return;

  // сразу пингуем при старте
  pingPresenceGlobal();

  presenceTimer = window.setInterval(() => {
    // пингуем только если user реально загружен
    if (authStore.user?.id) pingPresenceGlobal();
  }, 60 * 1000);
}

function stopPresenceTimer() {
  if (presenceTimer) window.clearInterval(presenceTimer);
  presenceTimer = null;
}

watch(
  () => authStore.user?.id,
  (id) => {
    if (id) startPresenceTimer();
    else stopPresenceTimer();
  },
  { immediate: true }
);

// Функція для перевірки, чи повинен відображатися пункт для правого меню залежно від ролей користувача
function shouldShowItem(item) {
  // Якщо немає вимог по ролях, показуємо пункт
  if (!item.requiredRoles || item.requiredRoles.length === 0) return true;
  // Перевіряємо, чи є у користувача хоча б одна з потрібних ролей
  return item.requiredRoles.some(role => authStore.hasRole(role));
}

const rootCategories = computed(() => {
  const categories = categoryStore.categories;
  const categoryMap = {};
  categories.forEach(category => { categoryMap[category.id] = { ...category, children: [] }; });
  categories.forEach(category => {
    if (category.parent_id && categoryMap[category.parent_id]) categoryMap[category.parent_id].children.push(categoryMap[category.id]);
  });
  return Object.values(categoryMap).filter(c => !c.parent_id);
});

// Обробник кліку по кнопці "Додати товар"
function handleAddProductClick() {
  const adminCreateUrl = `${adminPanelUrl}/products?action=create`;
  if (authStore.isAuthenticated) window.location.href = adminCreateUrl;
  else { sessionStorage.setItem('redirectAfterLogin', adminCreateUrl); authStore.openLoginDialog(); }
}

// Обробник кліку по іконці кошика
async function onCartIconClick() {
  if (cart.totalItems === 0) { emptyCartDialog.value = true; return; }
  isProfileDrawerOpen.value = false;
  isCartDrawerOpen.value = true;
}

// Обробник кліку по іконці обраного
function onFavoriteIconClick() {
  if (favoriteStore.totalFavorites === 0) {
    alert('Ваше обране поки порожнє');
    return;
  }
  
  // TODO: Перенаправлення на сторінку обраного (буде реалізовано в admin-app)
  alert(`У вас ${favoriteStore.totalFavorites} товарів в обраному`);
}


// Автоматичне встановлення першої категорії при відкритті меню
watch(isMenuOpen, (isOpen) => { if (isOpen && rootCategories.value.length > 0 && !hoveredCategory.value) hoveredCategory.value = rootCategories.value[0]; });

// Перенаправлення користувача на головну сторінку, якщо він вийшов з системи на сторінці, що вимагає аутентифікації
watch(() => authStore.isAuthenticated, (isAuth, wasAuth) => {
  if (wasAuth === true && isAuth === false) { if (route.meta.requiresAuth) router.push({ name: 'home' }); }
});


// Ініціалізація при монтуванні компонента
onMounted(async () => {
  categoryStore.fetchCategories();

  const prefersDark =
    window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  if (prefersDark) theme.global.name.value = 'dark';

  // ✅ ВАЖНО: подтянуть текущего пользователя (иначе presenceTimer не стартует)
  try {
    await authStore.getUser();

    // Якщо користувач авторизований — favorites мають братися з сервера
    if (authStore.isAuthenticated) {
      await favoriteStore.loadFromServer();
      await favoriteSellerStore.loadFromServer();
    }

  } catch (e) {
    // ignore (если не залогинен)
  }

  // Слухач для зміни видимості сторінки
  document.addEventListener('visibilitychange', handleVisibilityChange);
});

// Очистка при розмонтуванні компонента
onUnmounted(() => {
  stopPresenceTimer();
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});

</script>

<style scoped>
  .cart-container { position: relative; display: inline-flex; align-items: center; }
  .cart-badge >>> .v-badge__badge, .cart-badge .v-badge__badge { transform: translate(-40%, 40%) !important; }
  .cart-btn { width: 48px; height: 48px; }

  .favorite-container { position: relative; display: inline-flex; align-items: center; }
  .favorite-badge >>> .v-badge__badge, .favorite-badge .v-badge__badge { transform: translate(-40%, 40%) !important; }
  .favorite-btn-header { width: 48px; height: 48px; }
</style>

<style>
  .v-overlay__scrim { background: rgba(0, 0, 0, 1) !important; }
  .v-overlay { --v-overlay-opacity: 0.86 !important; }
</style>