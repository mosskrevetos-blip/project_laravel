export const navItems = [
  {
    title: 'Товари',
    icon: 'mdi-package-variant-closed',
    to: { name: 'products' },
    // Пустой массив означает, что доступ есть у всех авторизованных
    requiredRoles: [], 
  },
  {
    title: 'Повідомлення',
    icon: 'mdi-message-text-outline',
    to: '/messages',
    requiredRoles: [], // или нужные роли
  },
  {
    title: 'Категорії',
    icon: 'mdi-format-list-bulleted-square',
    to: { name: 'categories' },
    // Этот пункт виден только админу и менеджеру
    requiredRoles: ['admin', 'manager'], 
  },
  {
    title: 'Користувачі',
    icon: 'mdi-account-group',
    to: { name: 'users' },
    // Этот пункт виден только админу и менеджеру
    requiredRoles: ['admin', 'manager'],
  },
  {
    title: 'Замовлення',
    icon: 'mdi-cart-outline',
    to: { name: 'orders' },
    requiredRoles: [], // Видят все
  },
  {
    title: 'Атрибути',
    icon: 'mdi-format-list-checks',
    to: { name: 'attributes' },
    // Этот пункт виден только админу и менеджеру
    requiredRoles: ['admin', 'manager'], 
  },
  {
    title: 'Обране',
    icon: 'mdi-heart-outline',
    to: { name: 'favorites' },
    requiredRoles: [], 
  },
  {
    title: 'Коментарі',
    icon: 'mdi-comment-text-multiple-outline',
    to: { name: 'my-comments' },
    // видят все, кроме admin/manager — фильтруется в UI через authStore
    requiredRoles: [],
    hideForRoles: ['admin', 'manager'],
  },
  {
  title: 'Модерація коментарів',
    icon: 'mdi-comment-alert-outline',
    to: { name: 'comment-moderation' },
    requiredRoles: ['admin', 'manager'],
  },
];