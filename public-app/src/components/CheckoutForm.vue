<template>
  <v-card>
    <v-card-title>Оформление заказа</v-card-title>
    <v-card-text>
      <v-form ref="formRef" @submit.prevent="handleSubmit" v-slot="{ valid }">
        <h3>Данные покупателя</h3>
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.buyer_first_name" label="Имя" :rules="[r.required]" required></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.buyer_last_name" label="Фамилия" :rules="[r.required]" required></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.buyer_middle_name" label="Отчество"></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.buyer_phone" label="Телефон" :rules="[r.required]" required></v-text-field>
          </v-col>
          <v-col cols="12">
            <v-text-field v-model="form.buyer_email" label="Email" :rules="[r.required, r.email]" required></v-text-field>
          </v-col>
        </v-row>

        <h3 class="mt-4">Данные получателя (если отличается)</h3>
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.recipient_first_name" label="Имя получателя"></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.recipient_last_name" label="Фамилия получателя"></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.recipient_middle_name" label="Отчество получателя"></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.recipient_phone" label="Телефон получателя"></v-text-field>
          </v-col>
        </v-row>

        <h3 class="mt-4">Доставка и оплата</h3>
        <v-row>
          <v-col cols="12" md="6">
            <v-select
              :items="deliveryMethods"
              item-title="name"
              item-value="id"
              v-model="form.delivery_method_id"
              label="Способ доставки"
              dense
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-select
              :items="paymentMethods"
              item-title="name"
              item-value="id"
              v-model="form.payment_method_id"
              label="Способ оплаты"
              dense
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.city" label="Город" :rules="[r.required]" required></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.address" label="Адрес (улица, дом, кв/отделение)" :rules="[r.required]" required></v-text-field>
          </v-col>
        </v-row>

        <v-card-actions class="mt-4">
          <v-spacer />
          <v-btn color="primary" :loading="loading" @click="handleSubmit">Оформить заказ</v-btn>
        </v-card-actions>
      </v-form>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  deliveryMethods: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
});
const emit = defineEmits(['submit']);

const formRef = ref(null);
const loading = ref(false);

const form = ref({
  buyer_first_name: '',
  buyer_last_name: '',
  buyer_middle_name: '',
  buyer_phone: '',
  buyer_email: '',
  recipient_first_name: '',
  recipient_last_name: '',
  recipient_middle_name: '',
  recipient_phone: '',
  delivery_method_id: null,
  payment_method_id: null,
  city: '',
  address: '',
});

const r = {
  required: v => !!v || 'Обязательное поле',
  email: v => /.+@.+\..+/.test(v) || 'Неверный email',
};

function handleSubmit() {
  // basic client validation
  if (!form.value.buyer_first_name || !form.value.buyer_last_name || !form.value.buyer_phone || !form.value.buyer_email || !form.value.city || !form.value.address) {
    alert('Пожалуйста, заполните обязательные поля.');
    return;
  }
  loading.value = true;
  // Emit up to parent
  emit('submit', { ...form.value });
  loading.value = false;
}
</script>