<template>
  <v-btn
    :class="[
      overlay ? 'favorite-overlay-btn' : 'favorite-inline-btn',
      buttonClass,
      { 'favorite-active': isActive }
    ]"
    :icon="iconOnly"
    :variant="variant"
    :size="size"
    :color="buttonColorComputed"
    :loading="favoriteLoading"
    :aria-label="ariaLabelComputed"
    @click.prevent.stop="onClick"
  >
    <template v-if="!iconOnly && prependIcon">
      <v-icon start>{{ iconName }}</v-icon>
    </template>

    <v-icon v-else-if="iconOnly" :class="iconClass" :color="iconColorComputed">
      {{ iconName }}
    </v-icon>

    <template v-if="!iconOnly && labelMode !== 'none'">
      {{ buttonText }}
    </template>
  </v-btn>

  <v-snackbar
    v-model="snackbar"
    :timeout="2000"
    :color="snackbarColor"
    location="top right"
    elevation="6"
  >
    <div class="d-flex align-center">
      <v-icon :icon="snackbarIcon" class="mr-2" />
      <span>{{ snackbarText }}</span>
    </div>
  </v-snackbar>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useProductActions } from '@/composables/useProductActions';

const props = defineProps({
  productId: { type: [Number, String], required: true },

  // UI mode
  iconOnly: { type: Boolean, default: true },
  labelMode: { type: String, default: 'none' }, // none | short | full
  size: { type: String, default: 'small' },
  variant: { type: String, default: 'flat' },

  // positioning mode
  overlay: { type: Boolean, default: false },

  // styles / classes
  buttonClass: { type: [String, Array, Object], default: '' },
  iconClass: { type: [String, Array, Object], default: '' },

  // behavior
  showSnackbar: { type: Boolean, default: true },
});

const emit = defineEmits(['changed', 'error']);

const { favoriteLoading, toggleFavorite, isFavorite } = useProductActions();

const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const snackbarIcon = ref('mdi-check-circle');

const pid = computed(() => Number(props.productId));
const isActive = computed(() => isFavorite(pid.value));

const iconName = computed(() => (isActive.value ? 'mdi-heart' : 'mdi-heart-outline'));
const iconColorComputed = computed(() => (isActive.value ? 'white' : 'grey-darken-2'));

const buttonColorComputed = computed(() => {
  if (props.iconOnly) return undefined;
  return isActive.value ? 'primary' : undefined;
});

const ariaLabelComputed = computed(() =>
  isActive.value ? 'Видалити з обраного' : 'Додати в обране'
);

const prependIcon = computed(() => !props.iconOnly);

const buttonText = computed(() => {
  if (props.labelMode === 'short') return isActive.value ? 'В обраному' : 'В обране';
  if (props.labelMode === 'full') return isActive.value ? 'Видалити з обраного' : 'Додати в обране';
  return '';
});

function showSnack(payload) {
  if (!props.showSnackbar) return;
  snackbarText.value = payload.text;
  snackbarColor.value = payload.color;
  snackbarIcon.value = payload.icon;
  snackbar.value = true;
}

async function onClick() {
  try {
    const result = await toggleFavorite(pid.value);
    showSnack(result);
    emit('changed', { productId: pid.value, isFavorite: isActive.value, result });
  } catch (error) {
    emit('error', error);
  }
}
</script>

<style scoped>
.favorite-inline-btn {
  position: relative !important;
}

/* overlay mode for product card */
.favorite-overlay-btn {
  position: absolute !important;
  top: 8px !important;
  right: 8px !important;
  left: auto !important;
  z-index: 30 !important;

  min-width: 32px !important;
  width: 32px !important;
  height: 32px !important;
  padding: 0 !important;

  border-radius: 999px !important;
  background-color: rgba(255, 255, 255, 0.9) !important;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
}

.favorite-overlay-btn.favorite-active,
.favorite-inline-btn.favorite-active {
  background-color: #2196f3 !important;
}

.favorite-overlay-btn:hover {
  background-color: rgba(255, 255, 255, 1) !important;
}

.favorite-overlay-btn.favorite-active:hover,
.favorite-inline-btn.favorite-active:hover {
  background-color: #1976d2 !important;
}
</style>