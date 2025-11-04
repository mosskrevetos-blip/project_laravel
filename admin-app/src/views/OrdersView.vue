<template>
  <v-container>
    <h1 class="text-h4 mb-4">Управление заказами</h1>

    <v-data-table
      :headers="headers"
      :items="orderStore.orders"
      :loading="orderStore.loading"
      class="elevation-1"
    >
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
  </v-container>
</template>

<script setup>
import { onMounted } from 'vue';
import { useOrderStore } from '@/stores/orderStore';
import { useAuthStore } from '@/stores/authStore';
import { formatDate } from '@/utils/formatter'; // <-- ИМПОРТИРУЕМ ФУНКЦИЮ

const orderStore = useOrderStore();
const authStore = useAuthStore();

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Покупатель', key: 'customer_name' },
  { title: 'Товары', key: 'products', sortable: false },
  { title: 'Сумма', key: 'total_price' },
  { title: 'Статус', key: 'status' },
  { title: 'Дата', key: 'created_at' },
  { title: 'Действия', key: 'actions', sortable: false, align: 'end' },
];

onMounted(() => {
  orderStore.fetchOrders();
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
  console.log('Редактировать заказ:', item);
  alert('Функция редактирования заказов еще не реализована.');
}
function deleteOrder(item) {
  console.log('Удалить заказ:', item);
  alert('Функция удаления заказов еще не реализована.');
}
</script>