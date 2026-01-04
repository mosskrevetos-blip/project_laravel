<template>
  <v-app>
    <!-- Левое меню -->
    <v-navigation-drawer v-model="drawer" temporary>
      <v-list>
        <v-list-item title="Главная"></v-list-item>
        <v-list-item title="О нас"></v-list-item>
        <v-list-item title="Контакты"></v-list-item>
      </v-list>
    </v-navigation-drawer>

    <!-- Профиль (правый drawer) -->
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

    <!-- КОРЗИНА: правый drawer (шире) -->
    <v-navigation-drawer
      v-model="isCartDrawerOpen"
      location="right"
      temporary
      width="480"
    >
      <v-sheet class="d-flex align-center justify-space-between pa-4" elevation="0">
        <div class="text-h6">Корзина</div>
        <v-btn icon @click="isCartDrawerOpen = false" aria-label="Закрыть">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-sheet>

      <v-divider></v-divider>

      <v-container class="py-3" style="width: 440px;">
        <div v-if="loadingCart">
          <v-skeleton-loader type="list-item-two-line" />
          <v-skeleton-loader type="list-item-two-line" />
        </div>

        <div v-else>
          <!-- Пустая корзина -->
          <div v-if="groups.length === 0" class="text-center pa-6 grey--text">
            Корзина пуста
          </div>

          <!-- Карточки заказов по продавцам -->
          <div v-for="group in groups" :key="group.sellerKey" class="mb-4">
            <v-card outlined class="pa-3">
              <!-- Header: seller avatar, name, rating -->
              <div class="d-flex align-center justify-space-between mb-3">
                <div class="d-flex align-center">
                  <v-avatar size="36" color="primary" class="me-3">
                    <span class="white--text">{{ sellerInitial(group.sellerName) }}</span>
                  </v-avatar>
                  <div>
                    <div class="font-weight-bold">{{ group.sellerName }}</div>
                    <div class="caption grey--text">95% отзывов</div>
                  </div>
                </div>
              </div>

              <v-divider></v-divider>

              <!-- Items -->
              <div v-for="item in group.items" :key="item.product_id" class="py-3">
                <div class="d-flex align-start">
                  <!-- Thumbnail -->
                  <div class="me-3">
                    <img :src="item.image || placeholderImage" alt="" class="cart-thumb" />
                  </div>

                  <!-- Main content -->
                  <div class="flex-grow-1">
                    <div class="d-flex align-start justify-space-between">
                      <div class="me-2" style="flex:1; min-width:0;">
                        <!-- ЗАМЕНЕНО: открыть товар в новой вкладке -->
                        <a
                          :href="router.resolve({ name: 'product.show', params: { id: item.product_id } }).href"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="cart-item-title"
                        >
                          {{ item.title }}
                        </a>
                      </div>

                      <!-- Price per unit -->
                      <div class="cart-item-price primary--text ms-3" style="white-space:nowrap;">
                        {{ formatPrice(item.price) }}
                      </div>

                      <!-- Delete icon on right -->
                      <div class="ms-3">
                        <v-btn icon small @click="removeItem(item.product_id)" title="Удалить">
                          <v-icon color="red">mdi-delete</v-icon>
                        </v-btn>
                      </div>
                    </div>

                    <!-- Quantity controls and line total -->
                    <div class="d-flex align-center justify-space-between mt-2">
                      <div class="d-flex align-center">
                        <v-btn icon small @click="decrement(item)"><v-icon>mdi-minus</v-icon></v-btn>
                        <div class="mx-2">{{ item.quantity }}</div>
                        <v-btn icon small @click="increment(item)"><v-icon>mdi-plus</v-icon></v-btn>
                      </div>

                      <div class="grey--text">{{ formatPrice(item.price * item.quantity) }}</div>
                    </div>
                  </div>
                </div>

                <v-divider class="my-3"></v-divider>
              </div>

              <!-- Actions: two buttons stacked -->
              <div class="d-flex flex-column">
                <v-btn color="primary" class="mb-2" @click="orderNow(group)">
                  Оформить заказ — {{ formatPrice(groupTotal(group)) }}
                </v-btn>
                <v-btn variant="outlined" color="primary" @click="addMore(group)">
                  Добавить другие товары продавца
                </v-btn>
              </div>
            </v-card>
          </div>
        </div>
      </v-container>
    </v-navigation-drawer>

    <!-- Верхний тулбар (информационный) -->
    <v-app-bar app :color="isDark ? 'grey-darken-3' : 'grey-lighten-4'" height="40" flat>
      <v-container class="d-flex align-center py-0">
        <v-btn
          prepend-icon="mdi-plus-circle-outline"
          variant="text"
          size="small"
          @click="handleAddProductClick"
        >
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

    <!-- Основная шапка и содержимое -->
    <v-app-bar app :color="isDark ? 'black' : 'white'" flat class="border-b">
      <v-container class="d-flex align-center pa-0">
        <v-app-bar-nav-icon variant="text" @click.stop="drawer = !drawer"></v-app-bar-nav-icon>
        <v-app-bar-title class="font-weight-bold">E-Shop</v-app-bar-title>

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

        <v-btn :icon="isDark ? 'mdi-weather-sunny' : 'mdi-weather-night'" variant="text" @click="toggleTheme"></v-btn>
        <v-btn icon><v-icon>mdi-bell-outline</v-icon></v-btn>
        <v-btn icon><v-icon>mdi-heart-outline</v-icon></v-btn>

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
            <v-btn icon @click="onCartIconClick" aria-label="Корзина" class="cart-btn">
              <v-icon>mdi-cart-outline</v-icon>
            </v-btn>
          </v-badge>

          <v-btn v-else icon @click="onCartIconClick" aria-label="Корзина" class="cart-btn">
            <v-icon>mdi-cart-outline</v-icon>
          </v-btn>
        </div>

        <v-divider vertical class="mx-2"></v-divider>

        <template v-if="!authStore.isAuthenticated">
          <v-btn variant="text" class="mx-1" @click="isLoginDialogOpen = true">Войти</v-btn>
          <v-btn variant="outlined" class="mx-1" @click="isRegisterDialogOpen = true">Зарегистрироваться</v-btn>
        </template>

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

    <v-footer :color="isDark ? 'black' : 'grey-darken-4'" class="text-center d-flex flex-column py-4"></v-footer>

    <!-- Диалог: корзина пуста -->
    <v-dialog v-model="emptyCartDialog" max-width="420">
      <v-card>
        <v-card-title>Корзина пуста</v-card-title>
        <v-card-text>Ваша корзина пока пуста. Добавьте товары, чтобы оформить заказ.</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn text @click="emptyCartDialog = false">Перейти к покупкам</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <LoginDialog v-model="isLoginDialogOpen" @open-register="openRegisterDialog" @open-forgot-password="openForgotPasswordDialog"/>
    <RegisterDialog v-model="isRegisterDialogOpen" @open-login="openLoginDialog" @open-forgot-password="openForgotPasswordDialog"/>
    <ForgotPasswordDialog v-model="isForgotPasswordDialogOpen" @open-login="openLoginDialog" />
  </v-app>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useTheme } from 'vuetify';
import { useCategoryStore } from '@/stores/categoryStore';
import { useAuthStore } from '@/stores/authStore';
import { useRouter, useRoute } from 'vue-router';
import LoginDialog from '@/components/LoginDialog.vue';
import RegisterDialog from '@/components/RegisterDialog.vue';
import ForgotPasswordDialog from '@/components/ForgotPasswordDialog.vue';
import { useCartStore } from '@/stores/cartStore';
import apiClient from '@/api';

const categoryStore = useCategoryStore();
const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const cart = useCartStore();

const drawer = ref(false);
const isProfileDrawerOpen = ref(false);
const isCartDrawerOpen = ref(false);
const footerIcons = ref(['mdi-facebook', 'mdi-twitter', 'mdi-linkedin', 'mdi-instagram']);
const phoneNumbers = ref(['0 800 123-45-67', '044 123-45-67', '050 123-45-67']);

const isLoginDialogOpen = ref(false);
const isRegisterDialogOpen = ref(false);
const isForgotPasswordDialogOpen = ref(false);

const emptyCartDialog = ref(false);

const loadingCart = ref(false);
const productMap = ref({});
const groups = ref([]);

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

const adminPanelUrl = import.meta.env.VITE_ADMIN_SITE_URL;
const userMenuItems = ref([
  { title: 'Мои Товары', icon: 'mdi-package-variant-closed', href: `${adminPanelUrl}/products`, requiredRoles: ['seller','user','manager','wholesaler','manufacturer','admin'] },
  { title: 'Мои Заказы', icon: 'mdi-cart-outline', href: `${adminPanelUrl}/orders`, requiredRoles: [] },
  { title: 'Админ-панель', icon: 'mdi-shield-crown', href: adminPanelUrl, requiredRoles: ['admin','manager'] },
]);

function shouldShowItem(item) {
  if (!item.requiredRoles || item.requiredRoles.length === 0) return true;
  return item.requiredRoles.some(role => authStore.hasRole(role));
}

function openLoginDialog() { isForgotPasswordDialogOpen.value = false; isRegisterDialogOpen.value = false; isLoginDialogOpen.value = true; }
function openRegisterDialog() { isForgotPasswordDialogOpen.value = false; isLoginDialogOpen.value = false; isRegisterDialogOpen.value = true; }
function openForgotPasswordDialog() { isLoginDialogOpen.value = false; isRegisterDialogOpen.value = false; isForgotPasswordDialogOpen.value = true; }

const rootCategories = computed(() => {
  const categories = categoryStore.categories;
  const categoryMap = {};
  categories.forEach(category => { categoryMap[category.id] = { ...category, children: [] }; });
  categories.forEach(category => {
    if (category.parent_id && categoryMap[category.parent_id]) categoryMap[category.parent_id].children.push(categoryMap[category.id]);
  });
  return Object.values(categoryMap).filter(c => !c.parent_id);
});

function handleAddProductClick() {
  const adminCreateUrl = `${adminPanelUrl}/products?action=create`;
  if (authStore.isAuthenticated) window.location.href = adminCreateUrl;
  else { sessionStorage.setItem('redirectAfterLogin', adminCreateUrl); isLoginDialogOpen.value = true; }
}

async function onCartIconClick() {
  if (cart.totalItems === 0) { emptyCartDialog.value = true; return; }
  isProfileDrawerOpen.value = false;
  isCartDrawerOpen.value = true;
  await loadCartDetails();
}

async function loadCartDetails() {
  loadingCart.value = true;
  productMap.value = {};
  groups.value = [];
  try {
    const ids = cart.items.map(i => i.product_id);
    const promises = ids.map(async id => {
      try {
        const resp = await apiClient.get(`/products/${id}`);
        const p = resp.data.data ? resp.data.data : resp.data;
        productMap.value[id] = p;
      } catch (e) {
        productMap.value[id] = { id, title: cart.items.find(it => it.product_id == id)?.title || 'Товар', user_id: null };
      }
    });
    await Promise.all(promises);

    const tmp = {};
    for (const it of cart.items) {
      const pid = it.product_id;
      const p = productMap.value[pid] || {};
      const sellerId = p.user_id ?? p.seller_id ?? null;
      const sellerKey = sellerId !== null ? `seller_${sellerId}` : 'seller_unknown';
      if (!tmp[sellerKey]) {
        tmp[sellerKey] = {
          sellerKey,
          sellerId,
          sellerName: sellerId ? (p.user?.name || (`Продавец #${sellerId}`)) : 'Продавец не указан',
          items: []
        };
      }
      tmp[sellerKey].items.push({
        product_id: pid,
        title: p.title || it.title || 'Товар',
        image: (Array.isArray(p.image_url) ? (p.image_url[0] || it.image) : (p.image_url || it.image)) || it.image || null,
        quantity: it.quantity,
        price: it.price,
      });
    }

    groups.value = Object.values(tmp);
  } catch (e) {
    console.error('Ошибка при загрузке деталей корзины', e);
    groups.value = [];
  } finally {
    loadingCart.value = false;
  }
}

function increment(item) { cart.updateQuantity(item.product_id, item.quantity + 1); item.quantity++; }
function decrement(item) {
  const newQty = item.quantity - 1;
  if (newQty <= 0) removeItem(item.product_id);
  else { cart.updateQuantity(item.product_id, newQty); item.quantity = newQty; }
}
function removeItem(productId) {
  cart.removeItem(productId);
  groups.value = groups.value.map(g => ({ ...g, items: g.items.filter(it => it.product_id !== productId) })).filter(g => g.items.length > 0);
}

function proceedToCheckout() { isCartDrawerOpen.value = false; router.push({ name: 'checkout' }); }
function orderNow(group) { isCartDrawerOpen.value = false; router.push({ name: 'checkout' }); }
function addMore(group) { alert('Функция добавления других товаров продавца пока не реализована.'); }

function sellerInitial(name) { if (!name) return '?'; return String(name).trim().charAt(0).toUpperCase(); }
function formatPrice(v) { if (v == null) return ''; return Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency; }
function groupTotal(group) { return group.items.reduce((s, it) => s + (Number(it.price) * Number(it.quantity || 0)), 0); }

watch(isMenuOpen, (isOpen) => { if (isOpen && rootCategories.value.length > 0 && !hoveredCategory.value) hoveredCategory.value = rootCategories.value[0]; });

watch(() => authStore.isAuthenticated, (isAuth, wasAuth) => {
  if (wasAuth === true && isAuth === false) { if (route.meta.requiresAuth) router.push({ name: 'home' }); }
});

onMounted(() => {
  categoryStore.fetchCategories();
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  if (prefersDark) theme.global.name.value = 'dark';
  document.addEventListener('visibilitychange', handleVisibilityChange);
});

onUnmounted(() => { document.removeEventListener('visibilitychange', handleVisibilityChange); });

const currency = '$';
const placeholderImage = 'https://cdn.vuetifyjs.com/images/cards/docks.jpg';
</script>

<style scoped>
.subcategory-item { padding-inline-start: 32px !important; }
.cart-container { position: relative; display: inline-flex; align-items: center; }
.cart-badge >>> .v-badge__badge, .cart-badge .v-badge__badge { transform: translate(-40%, 40%) !important; }
.cart-btn { width: 48px; height: 48px; }

/* Thumb in drawer */
.cart-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; }

/* Title/link in cart */
.cart-item-title { color: inherit; text-decoration: none; font-weight: 600; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
.cart-item-price { font-weight: 700; }

/* small spacing adjustments */
.v-navigation-drawer .v-card { box-shadow: none; }
</style>

<style>
.v-overlay__scrim { background: rgba(0, 0, 0, 1) !important; }
.v-overlay { --v-overlay-opacity: 0.86 !important; }
</style>