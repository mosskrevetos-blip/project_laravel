<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue')" max-width="500px">
    <v-card class="elevation-12 pa-4">
      <v-card-title class="text-h5 text-center">Вход в аккаунт</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="handleLogin">
          <v-text-field
            label="Email"
            prepend-inner-icon="mdi-email-outline"
            type="email"
            v-model="email"
            variant="outlined"
            class="mb-3"
            required
          ></v-text-field>

          <v-text-field
            ref="passwordField" label="Пароль"
            prepend-inner-icon="mdi-lock-outline"
            :type="isPasswordVisible ? 'text' : 'password'"
            v-model="password"
            variant="outlined"
            class="mb-3"
            required
            :append-inner-icon="isPasswordVisible ? 'mdi-eye-off' : 'mdi-eye'"
            @click:append-inner="togglePasswordVisibility" 
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
            Войти
          </v-btn>
        </v-form>
      </v-card-text>
      <v-card-actions class="justify-center">
        <v-btn variant="text" size="small" @click="$emit('open-forgot-password')">Забыли пароль?</v-btn>
        <v-divider vertical class="mx-2"></v-divider>
        <span class="text-grey">Нет аккаунта?</span>
        <v-btn variant="text" size="small" @click="$emit('open-register')">Зарегистрироваться</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, nextTick } from 'vue';
import { useAuthStore } from '@/stores/authStore';

defineProps({
  modelValue: Boolean,
});
const emit = defineEmits(['update:modelValue', 'open-register', 'open-forgot-password']);

const email = ref('');
const password = ref('');
const error = ref(null);
const loading = ref(false);
const isPasswordVisible = ref(false);
const passwordField = ref(null); // Ссылка на компонент v-text-field

const authStore = useAuthStore();

const togglePasswordVisibility = async () => {
  if (!passwordField.value) return;

  // 1. Получаем старый элемент и запоминаем позицию курсора
  const oldInputElement = passwordField.value.$el.querySelector('input');
  if (!oldInputElement) return;
  const cursorPosition = oldInputElement.selectionStart;

  // 2. Меняем состояние, что вызывает перерисовку
  isPasswordVisible.value = !isPasswordVisible.value;

  // 3. Ждём, пока Vue/браузер закончит перерисовку
  await nextTick();

  // 4. Получаем НОВУЮ, "свежую" ссылку на уже перерисованный элемент
  const newInputElement = passwordField.value.$el.querySelector('input');
  if (!newInputElement) return;

  // 5. Применяем фокус и позицию курсора к НОВОМУ элементу
  newInputElement.focus();
  newInputElement.setSelectionRange(cursorPosition, cursorPosition);
};

const handleLogin = async () => {
  error.value = null;
  loading.value = true;
  try {
    await authStore.login({
      email: email.value,
      password: password.value,
    });
    emit('update:modelValue', false);
  } catch (err) {
    error.value = 'Ошибка входа. Проверьте email и пароль.';
  } finally {
    loading.value = false;
  }
};
</script>