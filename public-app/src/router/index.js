import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import ResetPasswordView from '../views/ResetPasswordView.vue'
import { useAuthStore } from '@/stores/authStore'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/password-reset/:token', 
      name: 'password.reset',
      component: ResetPasswordView,
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('../views/AboutView.vue'),
    },
    {
      path: '/product/:id',
      name: 'product.show',
      component: () => import('../views/ProductPage.vue'),
      props: true,
    },
    // CHANGED: checkout routes
    {
      path: '/checkout',
      name: 'checkout',
      component: () => import('../views/CheckoutPage.vue'),
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