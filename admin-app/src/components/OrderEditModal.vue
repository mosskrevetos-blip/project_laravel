<template>
  <v-dialog v-model="localOpen" max-width="800">
    <v-card>
      <v-card-title>Редактировать заказ #{{ order?.id }}</v-card-title>
      <v-card-text>
        <v-form ref="formRef">
          <v-row>
            <v-col cols="12" md="6">
              <v-select
                label="Статус заказа"
                :items="statuses"
                v-model="form.status"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-select
                label="Статус оплаты"
                :items="paymentStatuses"
                v-model="form.payment_status"
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field label="Дата оплаты" v-model="form.paid_at" placeholder="YYYY-MM-DD HH:MM:SS" />
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                label="Метод доставки"
                :items="deliveryMethods"
                item-title="name"
                item-value="id"
                v-model="form.delivery_method_id"
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                label="Метод оплаты"
                :items="paymentMethods"
                item-title="name"
                item-value="id"
                v-model="form.payment_method_id"
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field label="Carrier" v-model="form.carrier" />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field label="Tracking number" v-model="form.tracking_number" />
            </v-col>

            <v-col cols="12">
              <v-text-field label="Estimated delivery date" v-model="form.estimated_delivery_date" placeholder="YYYY-MM-DD HH:MM:SS" />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn text @click="close">Отмена</v-btn>
        <v-btn color="primary" @click="save" :loading="saving">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import apiClient from '@/api';
import { useOrderStore } from '@/stores/orderStore';

const props = defineProps({
  open: { type: Boolean, default: false },
  order: { type: Object, default: null },
  deliveryMethods: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'saved']);

const orderStore = useOrderStore();

const localOpen = ref(props.open);
watch(() => props.open, (v) => localOpen.value = v);
watch(localOpen, (v) => { if (!v) emit('close'); });

const statuses = ['pending','processing','shipped','completed','cancelled'];
const paymentStatuses = ['pending','paid','failed'];

const formRef = ref(null);
const saving = ref(false);
const form = ref({
  status: '',
  payment_status: '',
  paid_at: null,
  tracking_number: '',
  carrier: '',
  estimated_delivery_date: null,
  delivery_method_id: null,
  payment_method_id: null,
});

watch(() => props.order, (o) => {
  if (o) {
    form.value.status = o.status;
    form.value.payment_status = o.payment_status || 'pending';
    form.value.paid_at = o.paid_at || null;
    form.value.tracking_number = o.tracking_number || '';
    form.value.carrier = o.carrier || '';
    form.value.estimated_delivery_date = o.estimated_delivery_date || null;
    form.value.delivery_method_id = o.delivery_method_id || null;
    form.value.payment_method_id = o.payment_method_id || null;
  }
});

function close() {
  localOpen.value = false;
  emit('close');
}

async function save() {
  if (!props.order) return;
  saving.value = true;
  const payload = { id: props.order.id, ...form.value };
  try {
    await orderStore.updateOrder(payload);
    emit('saved', payload);
    close();
  } catch (e) {
    console.error(e);
    alert('Ошибка при сохранении');
  } finally {
    saving.value = false;
  }
}
</script>