import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = createRouter({
  // Підключаємо маршрутизатор
  history: createWebHistory(import.meta.env.BASE_URL),
  // Визначаємо маршрути
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('../views/HomeView.vue'),
    },
    {
      path: '/password-reset/:token', 
      name: 'password.reset',
      component: () => import('../views/ResetPasswordView.vue'),
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('../views/AboutView.vue'),
    },
    {
      path: '/product/:id(\\d+)-:slug?',
      name: 'product.show',
      component: () => import('../views/ProductPage.vue'),
    },
    // маршрути оформлення замовлення
    {
      path: '/checkout',
      name: 'checkout',
      component: () => import('../views/CheckoutPage.vue'),
      props: true,
    },
    {
      path: '/checkout/thank-you',
      name: 'checkout.thankyou',
      component: () => import('../views/CheckoutThankYou.vue'),
      props: (route) => ({ orders: route.params.orders || null }),
    },
  ],
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();
  await authStore.getUser();
  next();
});

export default router