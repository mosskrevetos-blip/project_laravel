<template>
  <v-container class="fill-height">
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="6" lg="4">
        <v-card class="elevation-12">
          <v-toolbar color="primary" dark flat>
            <v-toolbar-title>Вход в панель администратора</v-toolbar-title>
          </v-toolbar>
          <v-card-text>
            <v-form @submit.prevent="handleLogin">
              <v-text-field
                label="Email"
                name="email"
                prepend-icon="mdi-account"
                type="text"
                v-model="email"
                required
              ></v-text-field>
              <v-text-field
                id="password"
                label="Пароль"
                name="password"
                prepend-icon="mdi-lock"
                type="password"
                v-model="password"
                required
              ></v-text-field>
              <v-alert v-if="error" type="error" dense class="mb-4">
                {{ error }}
              </v-alert>
              <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn type="submit" color="primary" :loading="loading">Войти</v-btn>
              </v-card-actions>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/authStore';

const email = ref('admin@example.com');
const password = ref('password');
const error = ref(null);
const loading = ref(false);

const authStore = useAuthStore();

const handleLogin = async () => {
  error.value = null;
  loading.value = true;
  try {
    await authStore.login({
      email: email.value,
      password: password.value,
    });
  } catch (err) {
    error.value = 'Ошибка входа. Проверьте email и пароль.';
  } finally {
    loading.value = false;
  }
};
</script>