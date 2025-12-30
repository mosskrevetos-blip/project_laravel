<template>
  <v-container>
    <v-row>
      <!-- Выбор продавца -->
      <v-col cols="12" md="12" class="mb-4">
        <v-select
          v-model="selectedSellerId"
          :items="availableSellers"
          item-value="id"
          item-text="name"
          label="Выберите продавца"
          outlined
          dense
        />
      </v-col>

      <!-- Форма для оформления заказа -->
      <v-col cols="12" md="7">
        <checkout-form
          :delivery-methods="deliveryMethods"
          :payment-methods="paymentMethods"
          @submit="onSubmit"
        />
      </v-col>

      <!-- Сводка по заказу -->
      <v-col cols="12" md="5">
        <order-summary
          :items="filteredCartItems"
          :total-price="calculatedTotalPrice"
          @update-quantity="onUpdateQuantity"
          @remove-item="onRemoveItem"
        />
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import apiClient from '@/api';
import { useCartStore } from '@/stores/cartStore';
import CheckoutForm from '@/components/CheckoutForm.vue';
import OrderSummary from '@/components/OrderSummary.vue';
import { useRouter } from 'vue-router';

const cart = useCartStore();
const deliveryMethods = ref([]);
const paymentMethods = ref([]);
const router = useRouter();
const selectedSellerId = ref(null); // ID выбранного продавца (по умолчанию ничего не выбрано)

const availableSellers = computed(() => {
  // Генерация списка доступных продавцов на основе товаров в корзине
  const sellers = [];
  cart.items.forEach((item) => {
    if (!sellers.some((seller) => seller.id === item.seller_id)) {
      sellers.push({
        id: item.seller_id,
        name: item.seller_name || `Продавец #${item.seller_id}`,
      });
    }
  });
  return sellers;
});

const filteredCartItems = computed(() => {
  // Возврат отфильтрованных товаров только для выбранного продавца
  return cart.getItemsBySeller(selectedSellerId.value);
});

const calculatedTotalPrice = computed(() => {
  // Общая стоимость товаров выбранного продавца
  return filteredCartItems.value.reduce((sum, item) => sum + item.price * item.quantity, 0);
});

onMounted(async () => {
  try {
    const [dResp, pResp] = await Promise.all([
      apiClient.get('/delivery-methods'),
      apiClient.get('/payment-methods'),
    ]);
    deliveryMethods.value = dResp.data;
    paymentMethods.value = pResp.data;

    // Автоматически выбрать первого продавца при загрузке страницы
    if (availableSellers.value.length > 0) {
      selectedSellerId.value = availableSellers.value[0].id;
    }
  } catch (e) {
    console.error('Error loading delivery/payment methods', e);
    deliveryMethods.value = [];
    paymentMethods.value = [];
  }
});

async function onSubmit(formData) {
  // Подготовка данных для оформления заказа
  if (!selectedSellerId.value) {
    alert('Выберите продавца для оформления заказа.');
    return;
  }

  const payload = {
    buyer_first_name: formData.buyer_first_name,
    buyer_last_name: formData.buyer_last_name,
    buyer_middle_name: formData.buyer_middle_name || null,
    buyer_phone: formData.buyer_phone,
    buyer_email: formData.buyer_email,
    recipient_first_name: formData.recipient_first_name || null,
    recipient_last_name: formData.recipient_last_name || null,
    recipient_middle_name: formData.recipient_middle_name || null,
    recipient_phone: formData.recipient_phone || null,
    delivery_method_id: formData.delivery_method_id || null,
    payment_method_id: formData.payment_method_id || null,
    city: formData.city,
    address: formData.address,
    cart: filteredCartItems.value.map((item) => ({
      product_id: item.product_id,
      quantity: item.quantity,
    })),
  };

  try {
    const resp = await apiClient.post('/orders/public', payload);
    const result = resp.data;

    // Очистить товары выбранного продавца из корзины
    filteredCartItems.value.forEach((item) => cart.removeItem(item.product_id));

    // Сохранить оформленные заказы и перенаправить
    sessionStorage.setItem('last_created_orders', JSON.stringify(result.orders || result));
    router.push({ name: 'checkout.thankyou' });
  } catch (err) {
    console.error('Ошибка при оформлении заказа', err);
    alert(err.response?.data?.message || 'Ошибка при оформлении заказа');
  }
}

function onUpdateQuantity({ product_id, quantity }) {
  cart.updateQuantity(product_id, quantity);
}
function onRemoveItem(product_id) {
  cart.removeItem(product_id);
}
</script>