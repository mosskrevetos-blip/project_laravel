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
      // route level code-splitting
      // this generates a separate chunk (About.[hash].js) for this route
      // which is lazy-loaded when the route is visited.
      component: () => import('../views/AboutView.vue'),
    },
  ],
})

// навігаційний страж
// router.beforeEach(async (to, from, next) => {
//   const authStore = useAuthStore();
//   const requiresAuth = to.matched.some(record => record.meta.requiresAuth);

//   // Мы даём `main.js` время на первоначальную проверку пользователя.
//   // Но если пользователь уже в состоянии, `getUser` не будет делать лишний запрос.
//   await authStore.getUser();

//   if (requiresAuth && !authStore.isAuthenticated) {
//     // Если страница требует входа, а пользователь - гость,
//     // ПРИНУДИТЕЛЬНО перенаправляем его на главную страницу.
//     next({ name: 'home' });
//   } else {
//     // Во всех остальных случаях - разрешаем переход.
//     next();
//   }
// });

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();
  
  // Мы больше не проверяем requiresAuth, так как все страницы
  // на публичном сайте теперь доступны гостям.
  // Защищённая логика остаётся только на уровне кнопок.
  
  // Но мы по-прежнему проверяем, не залогинен ли пользователь,
  // чтобы `authStore.isAuthenticated` был актуален
  await authStore.getUser();

  next();
});

export default router
