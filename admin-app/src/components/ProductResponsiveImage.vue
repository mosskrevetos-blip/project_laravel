<template>
  <div class="product-responsive-image" :style="wrapperStyle">
    <!-- CHANGED: если есть manifest для данного filename — рендерим <picture> с webp и fallback -->
    <picture v-if="manifest">
      <source
        v-if="webpSrcset"
        :srcset="webpSrcset"
        type="image/webp"
        :sizes="sizesAttr"
      />
      <source
        v-if="fallbackSrcset"
        :srcset="fallbackSrcset"
        :sizes="sizesAttr"
      />
      <img
        :src="placeholder || largestFallback || srcFallback"
        :alt="altText"
        loading="lazy"
        class="responsive-img"
        :style="imgStyle"
      />
    </picture>

    <!-- CHANGED: если manifest отсутствует — fallback на прежнюю логику (img с srcset по суффиксам) -->
    <img
      v-else
      :srcset="legacySrcset"
      :sizes="sizesAttr"
      :src="srcFallback"
      :alt="altText"
      class="responsive-img"
      :style="imgStyle"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  productId: {
    type: [Number, String],
    required: true,
  },
  filename: {
    type: String,
    required: true, // базовое имя, например 'photo.webp'
  },
  altText: {
    type: String,
    default: '',
  },
  // можно переопределить размеры, но по умолчанию используем набор проекта
  sizesList: {
    type: Array,
    default: () => [150, 400, 800, 1200, 2000],
  },
  sizesAttr: {
    type: String,
    default: '(max-width: 600px) 480px, (max-width: 1000px) 800px, 1200px',
  },
  styleObject: {
    type: Object,
    default: () => ({})
  },
  // CHANGED: принимаем manifest-объект image_variants (весь объект для продукта)
  imageVariants: {                 /* CHANGED */
    type: Object,                  /* CHANGED */
    default: () => ({}),           /* CHANGED */
  },                                /* CHANGED */
});

// helper: вставляет суффикс перед расширением
function withSuffix(filename, suffix) {
  const dotIndex = filename.lastIndexOf('.');
  if (dotIndex === -1) {
    return `${filename}_${suffix}`;
  }
  const name = filename.substring(0, dotIndex);
  const ext = filename.substring(dotIndex + 1);
  return `${name}_${suffix}.${ext}`;
}

const baseUrl = import.meta.env.VITE_ADMIN_SITE_URL ? import.meta.env.VITE_ADMIN_SITE_URL.replace(/\/$/, '') : '';

// CHANGED: manifest для данного filename (если есть)
const manifest = computed(() => {
  if (!props.imageVariants) return null;
  return props.imageVariants[props.filename] || null;
}); // CHANGED

// build srcset from manifest when available
function buildSrcsetFromManifest(man, useWebp = true) {
  if (!man) return '';
  const sizeKeys = Object.keys(man).filter(k => k !== 'placeholder').map(k => Number(k)).filter(n => !isNaN(n));
  sizeKeys.sort((a,b)=>a-b);
  const parts = [];
  for (const s of sizeKeys) {
    const entry = man[String(s)];
    if (!entry) continue;
    const url = useWebp ? (entry.webp || entry.fallback) : (entry.fallback || entry.webp);
    if (url) parts.push(`${url} ${s}w`);
  }
  return parts.length ? parts.join(', ') : '';
}

const webpSrcset = computed(() => buildSrcsetFromManifest(manifest.value, true));
const fallbackSrcset = computed(() => buildSrcsetFromManifest(manifest.value, false));
const placeholder = computed(() => (manifest.value && manifest.value.placeholder) ? manifest.value.placeholder : null);

// choose largest fallback or original as img src fallback
const largestFallback = computed(() => {
  if (!manifest.value) return null;
  const sizeKeys = Object.keys(manifest.value).filter(k => k !== 'placeholder').map(k => Number(k)).filter(n => !isNaN(n));
  if (!sizeKeys.length) return null;
  sizeKeys.sort((a,b)=>b-a); // descending
  for (const s of sizeKeys) {
    const entry = manifest.value[String(s)];
    if (entry && (entry.fallback || entry.webp)) return entry.fallback || entry.webp;
  }
  return null;
});

// LEGACY behavior (if no manifest) - keep old logic to construct urls by suffix & ext
const legacySrcset = computed(() => {
  return props.sizesList.map(size => {
    const fname = withSuffix(props.filename, size);
    const url = `${baseUrl}/storage/products/${props.productId}/${fname}`;
    return `${url} ${size}w`;
  }).join(', ');
});

const srcFallback = computed(() => {
  // Возвращаем средний размер в качестве src
  const fallbackSize = 800;
  const fallbackName = withSuffix(props.filename, fallbackSize);
  return `${baseUrl}/storage/products/${props.productId}/${fallbackName}`;
});

const sizesAttr = computed(() => props.sizesAttr);
const altText = computed(() => props.altText || props.filename);

const wrapperStyle = computed(() => {
  // Позволяет задать высотуthumb в ImageUploadGrid: default height 120px
  return { width: '100%', height: '120px', overflow: 'hidden', ...props.styleObject };
});
const imgStyle = { width: '100%', height: '100%', objectFit: 'cover', display: 'block' };

</script>

<style scoped>
.responsive-img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.product-responsive-image {
  position: relative;
}
</style>