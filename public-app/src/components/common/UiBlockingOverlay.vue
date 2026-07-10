<template>
  <v-overlay
    :model-value="modelValue"
    class="align-center justify-center"
    persistent
    scrim="rgba(0,0,0,0.55)"
  >
    <div class="busy-box">
      <v-progress-circular indeterminate size="54" width="5" color="primary" />
      <div class="busy-title mt-3">{{ title }}</div>
      <div class="busy-text">{{ text }}</div>
    </div>
  </v-overlay>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Обробка...' },
  text: { type: String, default: 'Будь ласка, зачекайте.' },
});
</script>

<style scoped>
.busy-box {
  min-width: 280px;
  max-width: 420px;
  text-align: center;
  padding: 20px 24px;
  border-radius: 12px;
  background: rgba(20, 20, 20, 0.92);
  border: 1px solid rgba(255, 255, 255, 0.12);
}
.busy-title {
  font-size: 18px;
  font-weight: 700;
}
.busy-text {
  margin-top: 6px;
  opacity: 0.9;
  font-size: 14px;
}
</style>