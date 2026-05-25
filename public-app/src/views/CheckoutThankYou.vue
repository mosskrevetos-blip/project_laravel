<template>
  <v-container>
    <v-card>
      <v-card-title>Спасибо за заказ</v-card-title>
      <v-card-text>
        <div v-if="orders && orders.length">
          <p>Ваши заказы созданы:</p>
          <ul>
            <li v-for="o in orders" :key="o.id">
              Заказ #{{ o.id }} — сумма: {{ o.total_price }} — Статус: {{ o.status }}
            </li>
          </ul>
        </div>
        <div v-else>
          <p>Данные о заказе не найдены. Возможно вы попали сюда напрямую.</p>
        </div>
        <v-btn color="primary" :to="{ name: 'home' }">На главную</v-btn>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
const orders = ref(null);

onMounted(() => {
  try {
    const raw = sessionStorage.getItem('last_created_orders');
    if (raw) {
      orders.value = JSON.parse(raw);
      sessionStorage.removeItem('last_created_orders');
    } else {
      orders.value = null;
    }
  } catch (e) {
    orders.value = null;
  }
});
</script>