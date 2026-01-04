<template>
  <v-card>
    <v-card-title>Корзина ({{ totalItems }} товаров)</v-card-title>
    <v-card-text>
      <v-list dense>
        <v-list-item v-for="item in items" :key="item.product_id">
          <v-list-item-avatar v-if="item.image">
            <img :src="item.image" alt="" />
          </v-list-item-avatar>
          <v-list-item-content>
            <v-list-item-title>{{ item.title }}</v-list-item-title>
            <v-list-item-subtitle>{{ item.quantity }} × {{ item.price }} {{ currency }}</v-list-item-subtitle>
          </v-list-item-content>
          <v-list-item-action>
            <v-btn icon @click="$emit('remove-item', item.product_id)"><v-icon>mdi-delete</v-icon></v-btn>
          </v-list-item-action>
        </v-list-item>
      </v-list>
      <v-divider class="my-2" />
      <div class="text-right text-h6">Итого: {{ totalPrice }} {{ currency }}</div>
    </v-card-text>
    <v-card-actions>
      <v-btn text @click="$emit('checkout')">Перейти к оплате</v-btn>
    </v-card-actions>
  </v-card>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
  items: { type: Array, default: () => [] },
  totalPrice: { type: Number, default: 0 },
  currency: { type: String, default: '$' },
});
const totalItems = computed(() => props.items.reduce((s, i) => s + Number(i.quantity), 0));
</script>