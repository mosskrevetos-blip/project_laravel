<template>
<!-- Компонент кошика -->

<v-container class="py-3" :class="{ 'checkout': group_sellerKey !== null }" style="width: 440px;">
        <!-- Анімація завантаження кошика -->
        <div v-if="loadingCart">
            <!-- Скелетон-загрузчик відображається, якщо кошик завантажується -->
            <v-skeleton-loader type="list-item-two-line" />
            <v-skeleton-loader type="list-item-two-line" />
        </div>

        <div v-else>
            <!-- Порожній кошик -->
            <div v-if="groups.length === 0" class="text-center pa-6 grey--text">
                Кошик порожній
            </div>

            <!-- Картки замовлень по продавцях -->
            <!-- Перебір групп продавців -->
            <div v-for="group in groups" :key="group.sellerKey" class="mb-4">
                <v-card outlined class="pa-3">

                    <v-divider></v-divider>

                <!-- Хедер: аватар продавця, ім'я, рейтинг -->
                <div class="d-flex align-center justify-space-between mb-3">
                    <div class="d-flex align-center">

                    <!-- Замінити аватар продавця на аватар з БД -->
                    <v-avatar size="36" color="primary" class="me-3">
                        <span class="white--text">{{ sellerInitial(group.sellerName) }}</span>
                    </v-avatar>
                    <!-- Кінець аватара продавця -->

                    <div>
                        <div class="font-weight-bold">{{ group.sellerName }}</div>
                        <div class="caption grey--text">95% відгуків</div>
                    </div>
                </div>
            </div>

              <v-divider></v-divider>

              <!-- Перебір товарів у групі -->
              <div v-for="item in group.items" :key="item.product_id" class="py-3">
                
                <div class="py-3 position-relative" :class="{ 'unavailable-item-wrap': isItemUnavailable(item) }">
  
                  <div v-if="isItemUnavailable(item)" class="unavailable-overlay d-flex align-center justify-center">
                    
                    <!-- Червона плашка з повідомленням і кнопкою видалення -->
                    <div 
                      :class="getStatusClass(item)" 
                      class="d-flex align-center pa-1 ps-4 rounded-pill shadow-lg bg-error" 
                      style="pointer-events: auto;"
                    >
                      <div v-if="isItemPermanentlyRemoved(item)">
                        <v-icon color="white" size="small" class="me-2">mdi-alert-circle</v-icon>
                        <span class="text-caption white--text font-weight-bold me-2">Товар знято з продажу</span>
                        
                        <v-btn 
                          icon 
                          variant="outlined" 
                          size="small" 
                          color="white" 
                          class="yellow-border ms-2"
                          @click.stop="removeItem(item.product_id)" 
                          title="Видалити з кошика"
                        >
                          <v-icon size="small" color="yellow">mdi-delete</v-icon>
                        </v-btn>
                      </div>
                      <div v-else-if="isItemTemporarilyUnavailable(item)">
                        <v-icon color="white" size="small" class="me-2">mdi-alert-circle</v-icon>
                        <span class="text-caption white--text font-weight-bold me-2">Товар тимчасово недоступний</span>

                        <v-btn 
                          icon 
                          variant="outlined" 
                          size="small" 
                          color="white" 
                          class="yellow-border ms-2"
                          @click.stop="removeItem(item.product_id)" 
                          title="Видалити з кошика"
                        >
                          <v-icon size="small" color="yellow">mdi-delete</v-icon>
                        </v-btn>
                      </div>
                      <div v-else-if="isItemOutOfStock(item)">
                        <v-icon color="white" size="small" class="me-2">mdi-alert-circle</v-icon>
                        <span class="text-caption white--text font-weight-bold me-2">Товар закінчився</span>

                        <v-btn 
                          icon 
                          variant="outlined" 
                          size="small" 
                          color="white" 
                          class="yellow-border ms-2"
                          @click.stop="removeItem(item.product_id)" 
                          title="Видалити з кошика"
                        >
                          <v-icon size="small" color="yellow">mdi-delete</v-icon>
                        </v-btn>
                      </div>
                    </div>
                  </div>

                  <div class="d-flex align-start" :class="{ 'is-blocked': isItemUnavailable(item) }">
                    <div class="me-3">
                      <img :src="item.cart_image || placeholderImage" alt="" class="cart-thumb" />
                    </div>

                    <div class="flex-grow-1">
                      <div class="d-flex align-start justify-space-between">
                        <div class="me-2" style="flex:1; min-width:0;">
                          <span v-if="isItemUnavailable(item)" class="grey--text text-decoration-line-through">
                            {{ item.title }}
                          </span>
                          <router-link
                            v-else
                            :to="{ name: 'product.show', params: { id: item.product_id, slug: item.slug } }"
                            class="no-decoration" 
                            target="_blank"
                          >
                            {{ item.title }}
                          </router-link>
                        </div>

                        <div class="cart-item-price primary--text ms-3" style="white-space:nowrap;">
                          {{ formatPrice(item.price) }}
                        </div>

                        <div class="ms-3">
                          <v-btn icon small @click="removeItem(item.product_id)" :disabled="isItemUnavailable(item)">
                            <v-icon color="red">mdi-delete</v-icon>
                          </v-btn>
                        </div>
                      </div>

                      <!--Блок керування кількістю товару-->
                      <div class="d-flex align-center justify-space-between mt-2">
                        <div>
                          <div class="d-flex align-center">
                            <!-- Кнопка зменшення кількості -->
                            <v-btn icon small @click="decrement(item)" :disabled="item.quantity <= 1 || isItemUnavailable(item)">
                              <v-icon>mdi-minus</v-icon>
                            </v-btn>
                            <!-- Поле вводу кількості -->
                            <v-text-field
                              v-model.number="item.quantity"
                              type="number"
                              class="mx-2 small-input"
                              hide-details
                              variant="outlined"
                              density="compact"
                              :style="{ color: item.error ? 'red' : (isItemUnavailable(item) ? '#666' : 'white') }"
                              :rules="[validateQuantity(item)]"
                              :readonly="isItemUnavailable(item)"
                              :disabled="isItemUnavailable(item)"
                              @input="onInputQuantity(item, $event.target.value)"
                              @blur="onBlurQuantity(item)"
                            />
                            <!-- Кнопка збільшення кількості -->
                            <v-btn icon small @click="increment(item)" :disabled="isItemUnavailable(item)">
                              <v-icon>mdi-plus</v-icon>
                            </v-btn>
                          </div>

                          <!-- Повідомлення про перевищення кількості -->
                          <div v-if="item.error" class="text-caption red--text mt-1">
                            {{ item.errorMessage }}
                          </div>

                        </div>
                        <div class="grey--text">
                          {{ formatPrice(item.price * item.quantity) }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <v-divider class="my-3"></v-divider>
              </div>

              <!-- Actions: two buttons stacked -->
              <div class="d-flex flex-column">
                <template v-if="!group_sellerKey">
                  <!--Кнопка оформлення замовлення -->
                  <v-btn  
                    color="primary" 
                    class="mb-2" 
                    @click="orderNow(group)"
                    :disabled="!canOrder(group)"
                  >
                    Оформити замовлення — {{ formatPrice(groupTotal(group)) }}
                  </v-btn>
                </template>
                <template v-else>
                  <div class="summary-box d-flex justify-space-between align-center mb-4 pa-4 rounded-lg">
                    <span class="text-subtitle-1 grey--text text--lighten-1">Загальна сума:</span>
                    <span class="text-h5 font-weight-black primary--text">
                        {{ formatPrice(groupTotal(group)) }}
                    </span>
                  </div>
                </template>
                <v-btn variant="outlined" color="primary" @click="addMore(group)">
                  Додати інші товари продавця
                </v-btn>
              </div>
                </v-card>
          </div>
        </div>
      </v-container>

</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useCartStore } from '@/stores/cartStore';
import { useRouter, useRoute } from 'vue-router';
import apiClient from '@/api';

const router = useRouter();
const route = useRoute();

const emit = defineEmits(['update:groups']);

const props = defineProps({
  isCartDrawerOpen: {
    type: Boolean,
    required: false,
  },
  otherProducts: {
    type: Boolean,
    required: false,
  },
});

// отримання sellerKey з параметрів маршруту
const group_sellerKey = ref(null);

// Посилання на магазин кошика
const cart = useCartStore();

// Стан завантаження кошика
const loadingCart = ref(false);
const productMap = ref({});

// Групування товарів у кошику за продавцями
const groups = ref([]);

// Валюта
const currency = 'грн';

// Запасне зображення для товарів без зображення
const placeholderImage = 'https://cdn.vuetifyjs.com/images/cards/docks.jpg';

onMounted(async () => {
  // Якщо ми на сторінці оформлення замовлення завантажуємо деталі кошика
  if (route.name === 'checkout') {
    group_sellerKey.value = route.query.sellerKey || null;
    await loadCartDetails();
  }
});

watch(
  () => props.isCartDrawerOpen,
  async (newVal) => {
    if (newVal) {
      await loadCartDetails();
    }
  }
);


// Функція для визначення класу статусу товару
function getStatusClass(item) {
  if (item.moderation_status === 'rejected' || item.is_unavailable || item.deleted_by_user || item.deleted_by_admin) return 'bg-error-darken-1';
  if (item.moderation_status === 'pending' || !item.is_paid || !item.is_visible) return 'bg-error-darken-2';
  if (item.in_stock <= 0) return 'bg-error-darken-3';
  return ''; // По замовчуванню без класу
}


// Функція перевірки доступності товару
function isItemAvailable(item) {
  return !(
    item.moderation_status === 'rejected' || 
    item.moderation_status === 'pending' || 
    !item.is_paid || 
    !item.is_visible || 
    item.deleted_by_user || 
    item.deleted_by_admin || 
    item.is_unavailable || 
    item.in_stock <= 0
  );
}


// Функція перевірки, чи товар повністю знятий з продажу (не можна відновити)
function isItemPermanentlyRemoved(item) {
  return (
    item.moderation_status === 'rejected' || 
    item.is_unavailable || 
    item.deleted_by_user || 
    item.deleted_by_admin
  );
}


// Функція перевірки, чи товар тимчасово недоступний
function isItemTemporarilyUnavailable(item) {
  return (
    item.moderation_status === 'pending' || 
    !item.is_paid || 
    !item.is_visible
  );
}


// Функція перевірки, чи товар закінчився на складі
function isItemOutOfStock(item) {
  return item.in_stock <= 0;
}


// Об'єднана функція для визначення недоступності (інверсія isItemAvailable)
function isItemUnavailable(item) {
  return !isItemAvailable(item);
}


// Функція для завантаження деталей кошика
async function loadCartDetails() {
  // Встановлюємо стан завантаження кошика
  loadingCart.value = true;
  // Очищаємо попередні дані
  productMap.value = {};
  // Очищаємо групи
  groups.value = [];

  try {
    // Отримуємо унікальні ідентифікатори продуктів з кошика
    const ids = cart.items.map(i => i.product_id);
    // Завантажуємо деталі кожного продукту
    const promises = ids.map(async id => {
      try {
        // Запит до API для отримання деталей продукту
        const resp = await apiClient.get(`/products/${id}`);
        // Збереження отриманих даних у productMap
        const p = resp.data.data ? resp.data.data : resp.data;
        productMap.value[id] = p;
      } catch (e) {
        if (e.response && e.response.status === 404) {
          // Якщо 404 — товар фізично відсутній у базі
          console.warn(`Товар с ID ${id} не знайдено в БД і буде видалений з відображення.`);
        } else {
          // Логируем технічні помилки (500, проблеми з мережею, таймаути)
          console.error(`Помилка завантаження товару ID ${id}:`, {
            status: e.response?.status,
            message: e.message,
            data: e.response?.data
          });

          // Залишаємо тимчасову заглушку, щоб товар не зник через секундний збій мережі
          productMap.value[id] = { 
            id, 
            title: cart.items.find(it => it.product_id == id)?.title || 'Тимчасово недоступний', 
            is_unavailable: true 
          };
        }
      }
    });
    // Чекаємо завершення завантаження всіх деталей продуктів
    await Promise.all(promises);

    // Групуємо товари за продавцями
    const tmp = {};
    for (const it of cart.items) {
      
      // Ідентифікатор продукту
      const pid = it.product_id;

      // Якщо даних про товар немає (був 404), пропускаємо цей ітератор
      if (!productMap.value[pid]) {
        continue; 
      }

      // Деталі продукту з раніше завантажених даних
      const p = productMap.value[pid] || {};
      // Ідентифікатор продавця
      const sellerId = p.user_id ?? null;
      // Ключ для групування за продавцем
      const sellerKey = sellerId !== null ? `seller_${sellerId}` : 'seller_unknown';
      
      // Якщо група для цього продавця ще не створена, створюємо її
      if (!tmp[sellerKey]) {
        tmp[sellerKey] = {
          sellerKey,
          sellerId,
          sellerName: sellerId ? (p.user?.name || (`Продавець #${sellerId}`)) : 'Продавець не вказаний',
          items: []
        };
      }
      
      // Додаємо товар до відповідної групи продавця
      tmp[sellerKey].items.push({
        product_id: pid,
        title: p.title || it.title || 'Товар',
        slug: p.slug || '',
        image: (Array.isArray(p.image_url) ? (p.image_url[0] || it.image) : (p.image_url || it.image)) || it.image || null,
        // посилання на зображення для кошика (150px webp)
        cart_image: p.image_variants?.[p.image_url?.[0]]?.['150']?.webp || null,
        quantity: it.quantity,
        // кількість на складі, для перевірки доступності
        in_stock: p.quantity || 0,
        price: it.price,
        moderation_status: p.moderation_status || 'pending',
        is_paid: p.is_paid ?? false,
        is_visible: p.is_visible ?? true,
        deleted_by_user: p.deleted_by_user ?? false,
        deleted_by_admin: p.deleted_by_admin ?? false,
        is_unavailable: p.is_unavailable ?? false // прапорець недоступності товару для випадків технічних помилок при завантаженні деталей
      });
    }

    groups.value = Object.values(tmp);
    emit('update:groups', groups.value);

  } catch (e) {
    console.error('Помилка при завантаженні деталей кошика', e);
    groups.value = [];
    //emit('update:groups', []);
  } finally {
    // Фільтрація груп за sellerKey, якщо він вказаний
    if(group_sellerKey.value) {
      // Якщо вказано otherProducts, показуємо всі групи, крім поточної
      if (props.otherProducts) {
        groups.value = groups.value.filter(g => g.sellerKey !== group_sellerKey.value);
        emit('update:groups', groups.value);
      } else {
        // Фільтруємо групи, залишаючи лише ту, що відповідає sellerKey
        groups.value = groups.value
          .filter(g => g.sellerKey === group_sellerKey.value)
          .map(g => ({
            ...g,
            items: g.items.filter(item => 
              !(item.moderation_status === 'rejected' || 
                item.moderation_status === 'pending' || 
                !item.is_paid || 
                !item.is_visible || 
                item.deleted_by_user || 
                item.deleted_by_admin || 
                item.is_unavailable || 
                item.in_stock <= 0)
            )
          }))
          .filter(g => g.items.length > 0); // Видаляю группу, якщо в ній немає доступних товарів

        emit('update:groups', groups.value);
      };
    };

    emit('update:groups', groups.value);
    loadingCart.value = false;
  }
}


// Збільшення кількості товару в кошику
function increment(item) {
  // Перевіряємо, чи є достатня кількість товару в наявності
  if (item.quantity >= item.in_stock) {
    // Товару більше немає в наявності — показуємо помилку
    item.error = true; // Індикатор помилки для виділення червоним у візуальному інтерфейсі
    item.errorMessage = 'Товару більше немає в наявності.'; // Повідомлення для юзера

    // Прибираємо помилку через 3 секунди
    setTimeout(() => {
      item.error = false;
      item.errorMessage = '';
    }, 3000);
    return; // Завершуємо виконання функції без збільшення кількості
  }

  // Якщо кількість товару в наявності достатня — збільшуємо кількість
  cart.updateQuantity(item.product_id, item.quantity + 1);
  item.quantity++;
}


// Зменшення кількості товару в кошику
function decrement(item) {
  // Нове значення кількості
  const newQty = item.quantity - 1;
  // Якщо кількість менша або дорівнює нулю, видаляємо товар з кошика
  if (newQty <= 0){ 
    removeItem(item.product_id);
  } else { 
    cart.updateQuantity(item.product_id, newQty); 
    item.quantity = newQty; 
  }
}


// Видалення товару з кошика
function removeItem(productId) {
  cart.removeItem(productId);
  groups.value = groups.value.map(g => ({ ...g, items: g.items.filter(it => it.product_id !== productId) })).filter(g => g.items.length > 0);
}

// Перевірка та оновлення кількості товару при введенні в поле вводу
function onInputQuantity(item, value) {
  const newQuantity = Number(value);

  // Перевірка на мінімальні та максимальні межі
  if (newQuantity < 0) {
    item.quantity = 0; // Встановлюємо мінімальне значення
  } else if (newQuantity > item.in_stock) {
    // Якщо перевищує складський ліміт, повертаємо старе значення
    item.quantity = item.in_stock;
    item.error = true;
    item.errorMessage = 'Кількість перевищує наявність товару.';
    setTimeout(() => {
      item.error = false;
      item.errorMessage = '';
    }, 3000);
  } else {
    // Інакше оновлюємо кількість
    cart.updateQuantity(item.product_id, newQuantity);
    item.quantity = newQuantity;
  }
}

// Перевірка кількості при втраті фокусу
function onBlurQuantity(item) {
  // Переконуємося, що кількість в межах доступного діапазону
  if (item.quantity < 0) item.quantity = 0;
  if (item.quantity > item.in_stock) item.quantity = item.in_stock;
}

// Правила для валідації в Vuetify (опціонально)
function validateQuantity(item) {
  return value => {
    if (value < 0) return 'Кількість не може бути менше 0.';
    if (value > item.in_stock) return 'Перевищена кількість на складі.';
    return true;
  };
}

// Оформлення замовлення
function orderNow(group) { 
  let sellerKey = group.sellerKey; 
  router.push({ name: 'checkout', query: { sellerKey } }); 
}

// Додавання інших товарів продавця
function addMore(group) { alert('Функція додавання інших товарів продавця поки не реалізована.'); }

// Допоміжні функції форматування
function sellerInitial(name) { if (!name) return '?'; return String(name).trim().charAt(0).toUpperCase(); }

// Форматування ціни
function formatPrice(v) { 
  if (v == null) return ''; 
  return Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency; 
}

// Загальна сума групи товарів
function groupTotal(group) { 
  return group.items.reduce((s, it) => {
    return s + (it.moderation_status === 'rejected' || it.moderation_status === 'pending' || it.is_unavailable || it.deleted_by_user || it.deleted_by_admin || !it.is_paid || !it.is_visible || it.in_stock <= 0 ? 0 : (Number(it.price) * Number(it.quantity || 0)));
  }, 0); 
}

// Перевірка, чи можна оформити замовлення для групи (є хоча б один товар, який не видалений)
function canOrder(group) {
  return group.items.some(item => !(item.moderation_status === 'rejected' || item.moderation_status === 'pending' || item.is_unavailable || item.deleted_by_user || item.deleted_by_admin || !item.is_paid || !item.is_visible || item.in_stock <= 0));
}


</script>

<style scoped>

  /* Thumb in drawer */
  .cart-thumb { 
    width: 48px; 
    height: 48px; 
    object-fit: cover; 
    border-radius: 6px; 
  }

  /* Title/link in cart */
  .cart-item-title { 
    color: inherit; 
    text-decoration: none; 
    font-weight: 600; 
    display: inline-block; 
    overflow: hidden; 
    text-overflow: ellipsis; 
    white-space: nowrap; 
    max-width: 220px; 
  }
  
  .cart-item-price { font-weight: 700; }

  /* Прибираємо стрілки з input type="number" в v-text-field */
  .small-input :deep(input[type='number']::-webkit-inner-spin-button),
  .small-input :deep(input[type='number']::-webkit-outer-spin-button) {
    -webkit-appearance: none;
    margin: 0;
  }

  .small-input :deep(input[type='number']) {
    -moz-appearance: textfield; /* Для Firefox */
  }

  .small-input {
    max-width: 55px;
  }

    /* Центрируем текст внутри input */
  .small-input :deep(input) {
    text-align: center !important;
  }

  .v-card.outlined {
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    background-color: #121212 !important;
  }

  .checkout {
    padding: 0px !important;
  }

  .item-unavailable {
    background-color: rgba(255, 255, 255, 0.05); /* Легке висвітлення для темної теми */
    pointer-events: none; /* Блокуємо кліки по всій області, крім іконки видалення */
  }

  .item-unavailable .v-btn {
    pointer-events: auto; /* Дозволяємо клікати по кнопці видалення */
  }

  .unavailable-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2;
    background: rgba(255, 255, 255, 0.1); /* Ефект забіленості */
    pointer-events: none;
  }

  .position-relative {
    position: relative;
  }

  /* Картка товару при видаленні */
  .is-blocked {
    opacity: 0.4;
    filter: grayscale(0.9);
    pointer-events: none; /* Забороняємо будь-які кліки по основній картці */
    user-select: none;
  }

  /* Оверлей-забілення */
  .unavailable-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    z-index: 5;
    pointer-events: none; /* Сам фон пропускает клики сквозь себя... */
  }

  /* Червона плашка з текстом і кнопкою */
  .bg-error {
    pointer-events: auto !important; /* ...але сама плашка і кнопка в ній клікабельні! */
    z-index: 6;
  }
  .bg-error-darken-1 {
    background-color: #d32f2f !important;
  }
  .bg-error-darken-2 {
    background-color: #0a3996 !important;
  }
  .bg-error-darken-3 {
    background-color: #af39f8 !important;
  }

  .text-decoration-line-through {
    text-decoration: line-through !important;
  }

  .yellow-border {
    /* Встановлюємо колір і товщину рамки */
    border: 2px solid #FFEB3B !important; 
    /* Додаємо невелике світіння для ще більшої помітності */
    box-shadow: 0 0 8px rgba(255, 235, 59, 0.5);
  }

  /* При наведенні робимо кнопку ще яскравішою */
  .yellow-border:hover {
    background-color: rgba(255, 235, 59, 0.1) !important;
    box-shadow: 0 0 12px rgba(255, 235, 59, 0.8);
  }

</style>