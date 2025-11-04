export const navItems = [
  {
    title: 'Товари',
    icon: 'mdi-package-variant-closed',
    to: { name: 'products' },
    // Пустой массив означает, что доступ есть у всех авторизованных
    requiredRoles: [], 
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
];