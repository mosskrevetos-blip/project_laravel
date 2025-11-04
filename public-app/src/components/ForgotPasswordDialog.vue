<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue')" max-width="500px">
    <v-card class="elevation-12 pa-4">
      <v-card-title class="text-h5 text-center">Восстановление пароля</v-card-title>
      <v-card-text>
        <p class="text-center mb-4">Введите ваш email, и мы отправим вам ссылку для сброса пароля.</p>
        <div v-if="!emailSent">
          <v-form @submit.prevent="handleForgotPassword">
            <v-text-field
              label="Email"
              prepend-inner-icon="mdi-email-outline"
              type="email"
              v-model="email"
              variant="outlined"
              required
            ></v-text-field>
            <v-alert v-if="error" type="error" dense class="my-3">{{ error }}</v-alert>
            <v-btn :loading="loading" type="submit" color="primary" block size="large">
              Отправить ссылку
            </v-btn>
          </v-form>
        </div>
        <div v-else>
          <v-alert type="success" text="Ссылка для сброса пароля отправлена на ваш email."></v-alert>
        </div>
      </v-card-text>
      <v-card-actions class="justify-center">
        <v-btn variant="text" size="small" @click="$emit('open-login')">Вернуться ко входу</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/authStore';

defineProps({ modelValue: Boolean });
const emit = defineEmits(['update:modelValue', 'open-login']);

const email = ref('');
const error = ref(null);
const loading = ref(false);
const emailSent = ref(false);

const authStore = useAuthStore();

const handleForgotPassword = async () => {
  error.value = null;
  loading.value = true;
  emailSent.value = false;
  try {
    await authStore.forgotPassword(email.value);
    emailSent.value = true;
  } catch (err) {
    error.value = 'Не удалось отправить ссылку. Проверьте email.';
  } finally {
    loading.value = false;
  }
};
</script>