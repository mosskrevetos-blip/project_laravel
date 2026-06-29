import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import ProductsView from '../views/ProductsView.vue';
import CategoriesView from '../views/CategoriesView.vue';
import AttributesView from '../views/AttributesView.vue';
import LoginView from '../views/LoginView.vue';
import UsersView from '../views/UsersView.vue';
import OrdersView from '../views/OrdersView.vue';
import FavoritesView from '../views/FavoritesView.vue';
import MessagesView from '../views/MessagesView.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/products',
      name: 'products',
      component: ProductsView,
      meta: { requiresAuth: true }, // <-- Защищаем роут
    },
    {
      path: '/categories',
      name: 'categories',
      component: CategoriesView,
      meta: { requiresAuth: true },
    },
    {
      path: '/users',
      name: 'users',
      component: UsersView,
      meta: { requiresAuth: true }, 
    },
    {
      path: '/orders',
      name: 'orders',
      component: OrdersView,
      meta: { requiresAuth: true },
    },
    {
      path: '/attributes',
      name: 'attributes',
      component: AttributesView,
      meta: { requiresAuth: true },
    },
    {
      path: '/favorites',
      name: 'favorites',
      component: FavoritesView,
      meta: { requiresAuth: true },
    },
    {
      path: '/messages',
      name: 'messages',
      component: MessagesView,
      meta: { requiresAuth: true },
    },
    // Редирект с главной страницы на страницу товаров
    {
      path: '/',
      redirect: '/products',
    },
    {
      path: '/comment-moderation',
      name: 'comment-moderation',
      component: () => import('../views/CommentModerationView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'manager'] },
    },
  ],
});

// Навигационный страж (Navigation Guard)
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  await authStore.getUser();

  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);

  if (requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login' });
  }

  if (to.name === 'login' && authStore.isAuthenticated) {
    return next({ name: 'products' });
  }

  // NEW: role-check
  const requiredRoles = to.meta?.roles || [];
  if (requiredRoles.length > 0) {
    // authStore.hasRole('admin') / hasRole('manager')
    const allowed = requiredRoles.some(role => authStore.hasRole?.(role));
    if (!allowed) {
      return next({ name: 'products' }); // или на 403-страницу
    }
  }

  next();
});

export default router;