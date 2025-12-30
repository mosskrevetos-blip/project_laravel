<template>
  <v-container>
    <h1 class="text-h4 mb-4">Управление заказами</h1>

    <v-data-table
      :headers="headers"
      :items="orderStore.orders"
      :loading="orderStore.loading"
      class="elevation-1"
    >
      <template v-slot:item.purchaser="{ item }">
        {{ item.buyer_last_name ? (item.buyer_last_name + ' ' + item.buyer_first_name) : (item.customer_name || '—') }}
      </template>

      <template v-slot:item.products="{ item }">
        <ul class="pa-0">
          <li v-for="product in item.products" :key="product.id">
            {{ product.title }} ({{ product.pivot.quantity }} шт.)
          </li>
        </ul>
      </template>

      <template v-slot:item.status="{ item }">
        <v-chip :color="getStatusColor(item.status)" small>{{ item.status }}</v-chip>
      </template>

      <template v-slot:item.payment_status="{ item }">
        <v-chip :color="item.payment_status === 'paid' ? 'success' : (item.payment_status === 'failed' ? 'error' : 'grey')" small>{{ item.payment_status || 'pending' }}</v-chip>
      </template>

      <template v-slot:item.created_at="{ item }">
        {{ formatDate(item.created_at) }}
      </template>

      <template v-slot:item.actions="{ item }">
        <div v-if="authStore.hasRole('admin') || authStore.hasRole('manager')">
          <v-icon class="mr-2" color="primary" @click="editOrder(item)">mdi-pencil</v-icon>
          <v-icon color="error" @click="deleteOrder(item)">mdi-delete</v-icon>
        </div>
      </template>
    </v-data-table>

    <order-edit-modal
      v-if="selectedOrder"
      :open="isEditOpen"
      :order="selectedOrder"
      :delivery-methods="deliveryMethods"
      :payment-methods="paymentMethods"
      @close="closeEdit"
      @saved="onSaved"
    />
  </v-container>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useOrderStore } from '@/stores/orderStore';
import { useAuthStore } from '@/stores/authStore';
import { formatDate } from '@/utils/formatter';
import OrderEditModal from '@/components/OrderEditModal.vue';
import apiClient from '@/api';

const orderStore = useOrderStore();
const authStore = useAuthStore();

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Покупатель', key: 'purchaser' },
  { title: 'Товары', key: 'products', sortable: false },
  { title: 'Сумма', key: 'total_price' },
  { title: 'Статус', key: 'status' },
  { title: 'Оплата', key: 'payment_status' },
  { title: 'Дата', key: 'created_at' },
  { title: 'Действия', key: 'actions', sortable: false, align: 'end' },
];

const selectedOrder = ref(null);
const isEditOpen = ref(false);
const deliveryMethods = ref([]);
const paymentMethods = ref([]);

onMounted(async () => {
  await orderStore.fetchOrders();
  try {
    const [d, p] = await Promise.all([apiClient.get('/delivery-methods'), apiClient.get('/payment-methods')]);
    deliveryMethods.value = d.data;
    paymentMethods.value = p.data;
  } catch (e) {
    deliveryMethods.value = [];
    paymentMethods.value = [];
  }
});

const getStatusColor = (status) => {
  switch (status) {
    case 'completed': return 'success';
    case 'shipped': return 'info';
    case 'processing': return 'warning';
    case 'cancelled': return 'error';
    default: return 'grey';
  }
};

function editOrder(item) {
  selectedOrder.value = { ...item };
  isEditOpen.value = true;
}
function closeEdit() {
  isEditOpen.value = false;
  selectedOrder.value = null;
}
function onSaved() {
  // refresh list
  orderStore.fetchOrders();
}
function deleteOrder(item) {
  if (!confirm(`Удалить заказ #${item.id}?`)) return;
  orderStore.deleteProduct(item.id);
}
</script>