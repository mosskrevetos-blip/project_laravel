<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue')" max-width="500px">
    <v-card class="elevation-12 pa-4">
      <v-card-title class="text-h5 text-center">Реєстрація</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="handleRegister">
          <v-text-field
            label="Ім'я"
            prepend-inner-icon="mdi-account-outline"
            type="text"
            v-model="formData.name"
            variant="outlined"
            class="mb-3"
            required
          ></v-text-field>

          <v-text-field
            label="Email"
            prepend-inner-icon="mdi-email-outline"
            type="email"
            v-model="formData.email"
            @update:model-value="formData.email = $event.toLowerCase()"
            variant="outlined"
            class="mb-3"
            required
          ></v-text-field>

          <v-text-field
            label="Пароль"
            prepend-inner-icon="mdi-lock-outline"
            type="password"
            v-model="formData.password"
            variant="outlined"
            class="mb-3"
            required
          ></v-text-field>

          <v-text-field
            label="Підтвердіть пароль"
            prepend-inner-icon="mdi-lock-outline"
            type="password"
            v-model="formData.password_confirmation"
            variant="outlined"
            class="mb-3"
            required
          ></v-text-field>
          
          <v-alert v-if="error" type="error" dense class="mb-4">
            {{ error }}
          </v-alert>

          <v-btn
            :loading="loading"
            type="submit"
            color="primary"
            block
            size="large"
          >
            Зареєструватися
          </v-btn>
        </v-form>
      </v-card-text>
      <v-card-actions class="justify-center">
        <v-btn variant="text" size="small" @click="$emit('open-forgot-password')">Забули пароль?</v-btn>
        <v-divider vertical class="mx-2"></v-divider>
        <span class="text-grey">Вже є акаунт?</span>
        <v-btn variant="text" size="small" @click="$emit('open-login')">Увійти</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/authStore';

defineProps({
  modelValue: Boolean,
});
const emit = defineEmits(['update:modelValue', 'open-login', 'open-forgot-password']);

const formData = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});
const error = ref(null);
const loading = ref(false);

const authStore = useAuthStore();

const handleRegister = async () => {
  error.value = null;
  if (formData.value.password !== formData.value.password_confirmation) {
    error.value = 'Паролі не співпадають.';
    return;
  }

  loading.value = true;
  try {
    await authStore.register(formData.value);
    emit('update:modelValue', false);
  } catch (err) {
    // Обробка помилок валідації від Laravel
    const errors = err.response?.data?.errors;
    if (errors) {
      error.value = Object.values(errors).flat().join(' ');
    } else {
      error.value = 'Сталася помилка реєстрації.';
    }
  } finally {
    loading.value = false;
  }
};
</script>