<template>
  <v-container>
    <v-row>
      <!-- Форма для оформлення замовлення -->
      <v-col cols="12" md="7">
        <checkout-form
          :delivery-methods="deliveryMethods"
          :payment-methods="paymentMethods"
          @submit="onSubmit"
        />
      </v-col>

      <!-- Підсумок замовлення -->
      <!--{{ checkoutGroups }}-->
      <v-col cols="12" md="5">
        <CartSidebar @update:groups="onGroupsUpdate"/>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '@/stores/cartStore';
import { useAuthStore } from '@/stores/authStore';
import CheckoutForm from '@/components/cart/CheckoutForm.vue';
import CartSidebar from '@/components/cart/CartSidebar.vue';
import apiClient from '@/api';


const cart = useCartStore();
const deliveryMethods = ref([]);
const paymentMethods = ref([]);
const router = useRouter();

// Данні з дочернього компонента CartSidebar
const checkoutGroups = ref([]);

// Функція для оновлення груп товарів з дочернього компонента
function onGroupsUpdate(data) {
  checkoutGroups.value = data;
}


onMounted(async () => {
  try {
    const [dResp, pResp] = await Promise.all([
      apiClient.get('/delivery-methods'), 
      apiClient.get('/payment-methods'),
    ]);
    deliveryMethods.value = dResp.data;
    paymentMethods.value = pResp.data;

  } catch (e) {
    console.error('Error loading delivery/payment methods', e);
    deliveryMethods.value = [];
    paymentMethods.value = [];
  }
});

async function onSubmit(formData) {

  const currentGroup = checkoutGroups.value[0];

  if (!currentGroup || !currentGroup.items) {
    alert('Кошик порожній або дані ще завантажуються');
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
    comment: formData.comment || null,
    // !! Товари з поточної групи продавця
    cart: currentGroup.items.map((item) => ({
      product_id: item.product_id,
      quantity: item.quantity,
    })),
  };

  try {
    //console.log(payload);
    
    const resp = await apiClient.post('/orders/public', payload);
    const result = resp.data;

    //Видалити товари вибраного продавця з корзини
    currentGroup.items.forEach((item) => cart.removeItem(item.product_id));

    // Якщо користувач авторизований, також очистити кошик на сервері
    const authStore = useAuthStore();
    if (authStore.isAuthenticated) {
      await cart.loadFromServer(); // Завантажити оновлений кошик
    }

    // Зберегти оформлені замовлення та перенаправити на сторінку подяки
    sessionStorage.setItem('last_created_orders', JSON.stringify(result.orders || result));
    router.push({ name: 'checkout.thankyou' });

  } catch (err) {
    console.error('Помилка при оформленні замовлення', err);
    alert(err.response?.data?.message || 'Помилка при оформленні замовлення');
  }
}

</script>