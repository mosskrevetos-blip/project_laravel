<template>
  <div class="rating-display d-flex align-center ga-2" :class="wrapperClass">
    <template v-if="safeCount > 0">
      <v-rating
        :model-value="safeRating"
        :length="length"
        :density="density"
        :size="size"
        :color="color"
        :empty-icon="emptyIcon"
        :full-icon="fullIcon"
        :readonly="readonly"
        :half-increments="halfIncrements"
      />
      <span v-if="showText" :class="textClass">
        {{ safeRating.toFixed(decimals) }} ({{ safeCount }})
      </span>
    </template>

    <template v-else>
      <router-link
        v-if="reviewLink"
        :to="reviewLink"
        class="write-review-link"
      >
        {{ noReviewsText }}
      </router-link>

      <span v-else :class="textClass">
        {{ noReviewsText }}
      </span>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  rating: { type: [Number, String], default: 0 },
  count: { type: [Number, String], default: 0 },

  // если нет отзывов — можно передать ссылку
  reviewLink: { type: [String, Object], default: null },
  noReviewsText: { type: String, default: 'Залишити відгук' },

  length: { type: Number, default: 5 },
  size: { type: [Number, String], default: 20 },
  density: { type: String, default: 'compact' },
  color: { type: String, default: 'amber' },

  emptyIcon: { type: String, default: 'mdi-star-outline' },
  fullIcon: { type: String, default: 'mdi-star' },

  readonly: { type: Boolean, default: true },
  halfIncrements: { type: Boolean, default: true },

  showText: { type: Boolean, default: true },
  decimals: { type: Number, default: 1 },

  wrapperClass: { type: [String, Array, Object], default: '' },
  textClass: { type: [String, Array, Object], default: 'text-body-2 text-medium-emphasis' },
});

const safeRating = computed(() => {
  const n = Number(props.rating || 0);
  if (!Number.isFinite(n)) return 0;
  return Math.max(0, Math.min(5, n));
});

const safeCount = computed(() => {
  const n = Number(props.count || 0);
  if (!Number.isFinite(n)) return 0;
  return Math.max(0, n);
});
</script>

<style scoped>
.write-review-link {
  font-size: 13px;
  font-weight: 500;
  color: rgb(var(--v-theme-primary));
  text-decoration: underline;
  text-decoration-style: dotted;
}
</style>