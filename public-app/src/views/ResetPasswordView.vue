<template>
  <v-container class="fill-height">
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="6" lg="4">
        <v-card class="elevation-12 pa-4">
          <v-card-title class="text-h5 text-center">Установка нового пароля</v-card-title>
          <v-card-text>
            <div v-if="!success">
              <v-form @submit.prevent="handleResetPassword">
                <v-text-field
                  label="Email"
                  type="email"
                  v-model="formData.email"
                  variant="outlined"
                  class="mb-3"
                  readonly
                ></v-text-field>
                <v-text-field
                  label="Новый пароль"
                  type="password"
                  v-model="formData.password"
                  variant="outlined"
                  class="mb-3"
                  required
                ></v-text-field>
                <v-text-field
                  label="Подтвердите пароль"
                  type="password"
                  v-model="formData.password_confirmation"
                  variant="outlined"
                  class="mb-3"
                  required
                ></v-text-field>
                <v-alert v-if="error" type="error" dense class="mb-4">{{ error }}</v-alert>
                <v-btn :loading="loading" type="submit" color="primary" block size="large">
                  Сохранить пароль
                </v-btn>
              </v-form>
            </div>
            <div v-else>
              <v-alert type="success" text="Ваш пароль успешно изменен!"></v-alert>
              <v-btn @click="goToHome" color="primary" block size="large" class="mt-4">Перейти ко входу</v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const formData = ref({
  token: '',
  email: '',
  password: '',
  password_confirmation: '',
});
const error = ref(null);
const loading = ref(false);
const success = ref(false);

const goToHome = () => {
  router.push({ name: 'home' });
};

onMounted(() => {
  // 👇 Получаем токен из ПАРАМЕТРОВ пути, а email - из QUERY-строки
  formData.value.token = route.params.token || '';
  formData.value.email = route.query.email || '';

  if (!formData.value.token || !formData.value.email) {
    error.value = 'Неверная или устаревшая ссылка для сброса пароля.';
  }
});

const handleResetPassword = async () => {
  error.value = null;
  if (formData.value.password !== formData.value.password_confirmation) {
    error.value = 'Пароли не совпадают.';
    return;
  }
  loading.value = true;
  try {
    await authStore.resetPassword(formData.value);
    success.value = true;
  } catch (err) {
    const errors = err.response?.data?.errors;
    if (errors) {
      error.value = Object.values(errors).flat().join(' ');
    } else {
      error.value = 'Произошла ошибка. Возможно, ссылка устарела.';
    }
  } finally {
    loading.value = false;
  }
};
</script>