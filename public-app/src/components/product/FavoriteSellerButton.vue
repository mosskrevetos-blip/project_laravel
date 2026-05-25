<template>
  <v-btn
    :prepend-icon="isFavoriteSeller ? 'mdi-star' : 'mdi-star-outline'"
    :color="isFavoriteSeller ? 'amber' : 'grey'"
    :variant="isFavoriteSeller ? 'flat' : 'outlined'"
    @click="toggleFavoriteSeller"
    :loading="loading"
  >
    {{ isFavoriteSeller ? 'Продавець в обраному' : 'Додати продавця в обране' }}
  </v-btn>

  <!-- Повідомлення -->
  <v-snackbar
    v-model="snackbar"
    :timeout="2000"
    :color="snackbarColor"
    location="top right"
    elevation="6"
  >
    <div class="d-flex align-center">
      <v-icon :icon="snackbarIcon" class="mr-2"></v-icon>
      <span>{{ snackbarText }}</span>
    </div>
  </v-snackbar>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useFavoriteSellerStore } from '@/stores/favoriteSellerStore';
import { useAuthStore } from '@/stores/authStore';

const props = defineProps({
  sellerId: {
    type: Number,
    required: true,
  },
});

const favoriteSellerStore = useFavoriteSellerStore();
const authStore = useAuthStore();

const loading = ref(false);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const snackbarIcon = ref('mdi-check-circle');

const isFavoriteSeller = computed(() => favoriteSellerStore.isFavoriteSeller(props.sellerId));

async function toggleFavoriteSeller() {
    // Якщо не авторизований — показуємо повідомлення
    if (!authStore.isAuthenticated) {
        snackbarText.value = 'Увійдіть, щоб додати продавця в обране';
        snackbarColor.value = 'warning';
        snackbarIcon.value = 'mdi-alert';
        snackbar.value = true;
        return;
    }

    loading.value = true;
  
    try {
        await favoriteSellerStore.toggleFavoriteSeller(props.sellerId);
        
        if (isFavoriteSeller.value) {
        snackbarText.value = 'Продавця додано в обране';
        snackbarColor.value = 'success';
        snackbarIcon.value = 'mdi-star';
        } else {
        snackbarText.value = 'Продавця видалено з обраного';
        snackbarColor.value = 'grey';
        snackbarIcon.value = 'mdi-star-outline';
        }
        snackbar.value = true;
    } catch (error) {
        console.error('Помилка при роботі з обраними продавцями:', error);
        snackbarText.value = 'Помилка. Спробуйте ще раз';
        snackbarColor.value = 'error';
        snackbarIcon.value = 'mdi-alert-circle';
        snackbar.value = true;
    } finally {
        loading.value = false;
    }
}
</script>