<template>
  <v-card>
    <v-card-title>Оформлення замовлення</v-card-title>
    <v-card-text>
      <v-form ref="formRef" @submit.prevent="handleSubmit" v-slot="{ valid }">
        <h3>Дані покупця</h3>
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.buyer_first_name" label="Ім'я" :rules="[r.required]" required></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.buyer_last_name" label="Прізвище" :rules="[r.required]" required></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.buyer_middle_name" label="По батькові"></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.buyer_phone" label="Телефон" :rules="[r.required]" required></v-text-field>
          </v-col>
          <v-col cols="12">
            <v-text-field v-model="form.buyer_email" label="Email" :rules="[r.required, r.email]" required></v-text-field>
          </v-col>
        </v-row>

        <!-- Чекбокс "Інший отримувач" -->
        <v-row class="mt-4">
          <v-col cols="12">
            <v-checkbox
              v-model="isDifferentRecipient"
              label="Інший отримувач"
              hide-details
            ></v-checkbox>
          </v-col>
        </v-row>

        <!-- Поля отримувача (відображаються тільки якщо isDifferentRecipient === true) -->
        <transition name="expand">
          <div v-if="isDifferentRecipient">
            <h3 class="mt-4">Дані отримувача</h3>
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field v-model="form.recipient_first_name" label="Ім'я отримувача" :rules="[r.required]" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="form.recipient_last_name" label="Прізвище отримувача" :rules="[r.required]" required></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="form.recipient_middle_name" label="По батькові отримувача"></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="form.recipient_phone" label="Телефон отримувача" :rules="[r.required]" required></v-text-field>
              </v-col>
            </v-row>
          </div>
        </transition>

        <h3 class="mt-4">Доставка та оплата</h3>
        <v-row>
          <v-col cols="12" md="6">
            <v-select
              :items="deliveryMethods"
              item-title="name"
              item-value="id"
              v-model="form.delivery_method_id"
              label="Спосіб доставки"
              dense
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-select
              :items="paymentMethods"
              item-title="name"
              item-value="id"
              v-model="form.payment_method_id"
              label="Спосіб оплати"
              dense
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.city" label="Місто" :rules="[r.required]" required></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.address" label="Адреса (вулиця, будинок, кв/відділення)" :rules="[r.required]" required></v-text-field>
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12">
            <v-textarea
              v-model="form.comment"
              label="Коментар до замовлення (необов'язково)"
              placeholder="Залиште коментар, якщо потрібно"
              rows="3"
              counter="1000"
              maxlength="1000"
              variant="outlined"
              class="mt-4"
            ></v-textarea>
          </v-col>
        </v-row>

        <v-card-actions class="mt-4">
          <v-spacer />
          <v-btn color="primary" :loading="loading" @click="handleSubmit">Оформити замовлення</v-btn>
        </v-card-actions>
      </v-form>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  deliveryMethods: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
});

const emit = defineEmits(['submit']);

const formRef = ref(null);
const loading = ref(false);

// Чекбокс "Інший отримувач"
const isDifferentRecipient = ref(false);

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
  comment: '',
});

const r = {
  required: v => !!v || 'Обов\'язкове поле',
  email: v => /.+@.+\..+/.test(v) || 'Невірний email',
};

// Якщо чекбокс "Інший отримувач" вимкнено, очищаємо поля отримувача
watch(isDifferentRecipient, (newVal) => {
  if (!newVal) {
    form.value.recipient_first_name = '';
    form.value.recipient_last_name = '';
    form.value.recipient_middle_name = '';
    form.value.recipient_phone = '';
  }
});

function handleSubmit() {
  // basic client validation
  if (!form.value.buyer_first_name || !form.value.buyer_last_name || !form.value.buyer_phone || !form.value.buyer_email || !form.value.city || !form.value.address) {
    alert('Будь ласка, заповніть обов\'язкові поля.');
    return;
  }

  // Якщо "Інший отримувач" вибрано, перевіряємо обов'язкові поля отримувача
  if (isDifferentRecipient.value) {
    if (!form.value.recipient_first_name || !form.value.recipient_last_name || !form.value.recipient_phone) {
      alert('Будь ласка, заповніть дані отримувача.');
      return;
    }
  }

  loading.value = true;
  // Emit up to parent
  emit('submit', { ...form.value });
  loading.value = false;
}
</script>

<style scoped>
  /* Анімація плавного розкриття вниз */
  .expand-enter-active,
  .expand-leave-active {
    transition: all 0.5s ease;
    overflow: hidden;
  }

  .expand-enter-from,
  .expand-leave-to {
    max-height: 0;
    opacity: 0;
    transform: translateY(-20px);
  }

  .expand-enter-to,
  .expand-leave-from {
    max-height: 500px; /* Достатньо велике значення для всіх полів */
    opacity: 1;
    transform: translateY(0);
  }

  .recipient-fields {
    overflow: hidden;
  }
</style>