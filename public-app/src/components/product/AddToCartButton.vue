<template>
  <div v-if="mode === 'icon'" class="cart-btn-wrapper">
    <v-btn
      icon
      size="small"
      color="red"
      :disabled="disabledComputed"
      :loading="loadingComputed"
      @click.prevent.stop="onClick"
      class="cart-btn"
      :aria-label="inCartComputed ? 'Товар у кошику' : 'Додати в кошик'"
    >
      <v-icon>mdi-cart-plus</v-icon>
    </v-btn>

    <v-icon v-if="inCartComputed" class="check-icon" color="success">
      mdi-check-circle
    </v-icon>
  </div>

  <v-btn
    v-else
    color="primary"
    :loading="loadingComputed"
    :disabled="disabledComputed"
    @click.prevent="onClick"
  >
    {{ buttonText }}
  </v-btn>

  <v-snackbar
    v-if="showSnackbar"
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
  product: { type: Object, required: true },

  // icon | full
  mode: { type: String, default: 'full' },

  // если true — внутри компонента показываем snackbar
  showSnackbar: { type: Boolean, default: true },

  // кастомный текст для full-режима
  label: { type: String, default: 'Додати в кошик' },
});

const emit = defineEmits(['added', 'already-in-cart', 'error']);

const {
  isInCart,
  addToCart,
  isCartLoading,
} = useProductActions();

const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');
const snackbarIcon = ref('mdi-check-circle');

const productId = computed(() => Number(props.product?.id || 0));

const inCartComputed = computed(() => isInCart(productId.value));
const loadingComputed = computed(() => isCartLoading(productId.value));
const disabledComputed = computed(() => inCartComputed.value || loadingComputed.value);

const buttonText = computed(() => {
  if (inCartComputed.value) return 'У кошику';
  return props.label;
});

function showSnack(payload) {
  if (!props.showSnackbar) return;
  snackbarText.value = payload.text;
  snackbarColor.value = payload.color;
  snackbarIcon.value = payload.icon;
  snackbar.value = true;
}

async function onClick() {
  const p = props.product;
  const payload = {
    id: p.id,
    title: p.title,
    price: p.price,
    image: (Array.isArray(p.image_url) ? p.image_url[0] : p.image_url) || p.image || null,
    quantity: p.quantity,
  };

  const result = await addToCart(payload, 1, p);

  if (result.alreadyInCart) {
    emit('already-in-cart', result);
    return;
  }

  if (result.ok) {
    showSnack(result);
    emit('added', result);
  } else {
    showSnack(result);
    emit('error', result);
  }
}
</script>

<style scoped>
.cart-btn-wrapper { position: relative; display: inline-block; }
.cart-btn:disabled { opacity: .5; filter: blur(1px); }

.check-icon {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 10;
  font-size: 24px !important;
  pointer-events: none;
}
</style>