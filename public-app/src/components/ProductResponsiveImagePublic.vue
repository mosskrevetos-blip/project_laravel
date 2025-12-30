<template>
  <div class="product-responsive-image" :style="wrapperStyle">
    <picture v-if="variants && variantsSizes.length">
      <!-- AVIF source (if available) -->
      <source
        v-if="srcsetFor('avif')"
        type="image/avif"
        :srcset="srcsetFor('avif')"
        :sizes="sizesAttr"
      />
      <!-- WEBP source (if available) -->
      <source
        v-if="srcsetFor('webp')"
        type="image/webp"
        :srcset="srcsetFor('webp')"
        :sizes="sizesAttr"
      />
      <!-- Fallback source (jpeg/png) -->
      <img
        :src="fallbackUrl"
        :srcset="srcsetFor('fallback')"
        :sizes="sizesAttr"
        :alt="altText"
        loading="lazy"
        class="responsive-img"
        @error="onError"
        @load="onLoad"
        width="1"
        height="1"
        :style="imgStyle" 
      />
    </picture>

    <!-- If no variants available, show placeholder or absolute filename -->
    <img
      v-else
      :src="fallbackUrl"
      :alt="altText"
      loading="lazy"
      class="responsive-img"
      @error="onError"
      @load="onLoad"
      :style="imgStyle"
    />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  product: { type: Object, required: false }, // optional: pass entire product (preferred)
  productId: { type: [Number, String], required: false }, // fallback if product not provided
  filename: { type: String, required: false }, // base filename (e.g. "photo.webp")
  // sizes order (w values)
  sizesList: { type: Array, default: () => [150, 400, 800, 1200, 2000] },
  sizesAttr: { type: String, default: '(max-width:480px) 150px, (max-width:800px) 400px, (max-width:1200px) 800px, 1200px' },
  altText: { type: String, default: '' },
  baseUrl: { type: String, default: () => (import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_PUBLIC_SITE_URL || window.location.origin).replace(/\/$/, '') },
  placeholder: { type: String, default: 'https://cdn.vuetifyjs.com/images/cards/docks.jpg' },
  styleObject: { type: Object, default: () => ({ width: '100%', height: '100%' }) },
  // CHANGED: allow controlling object-fit behavior ('cover' | 'contain' etc.)
  imgFit: { type: String, default: 'cover' }, /* CHANGED */
});

const loaded = ref(false);
function onLoad() { loaded.value = true; }
function onError(e) {
  e.target.src = props.placeholder;
  e.target.removeAttribute('srcset');
}

// Helper: get variants object from product or props
const variants = computed(() => {
  if (props.product && props.product.image_variants) {
    if (props.filename && props.product.image_variants[props.filename]) {
      return props.product.image_variants[props.filename];
    }
    if (!props.filename && typeof props.product.image_variants === 'object' && Object.keys(props.product.image_variants).length) {
      const firstKey = Object.keys(props.product.image_variants)[0];
      return props.product.image_variants[firstKey];
    }
  }

  const fname = props.filename || (props.product && (Array.isArray(props.product.image_url) ? props.product.image_url[0] : props.product.image_url));
  if (!fname) return null;

  const dot = fname.lastIndexOf('.');
  const base = dot === -1 ? fname : fname.slice(0, dot);
  const ext = dot === -1 ? '' : fname.slice(dot);
  const pid = (props.product && props.product.id) || props.productId || '';
  const dir = pid ? `${props.baseUrl}/storage/products/${pid}` : `${props.baseUrl}/storage/products`;
  const map = {};
  props.sizesList.forEach((s) => {
    map[s] = { fallback: `${dir}/${base}_${s}${ext}` };
  });
  return map;
});

const variantsSizes = computed(() => {
  if (!variants.value) return [];
  return Object.keys(variants.value).filter(k => k !== 'placeholder').map(k => Number(k)).sort((a, b) => a - b);
});

function srcsetFor(formatKey) {
  if (!variants.value) return null;
  const parts = [];
  for (const size of props.sizesList) {
    const entry = variants.value[size];
    if (!entry) continue;
    let url = null;
    if (typeof entry === 'string') {
      if (formatKey === 'fallback') url = entry;
    } else if (entry && typeof entry === 'object') {
      if (formatKey === 'fallback') {
        url = entry.jpg || entry.jpeg || entry.png || entry.fallback || entry.webp;
      } else {
        url = entry[formatKey];
      }
    }
    if (url) parts.push(`${url} ${size}w`);
  }
  return parts.length ? parts.join(', ') : null;
}

const fallbackUrl = computed(() => {
  if (variantsSizes.value.length) {
    const prefer = 800;
    let chosenSize = variantsSizes.value.includes(prefer) ? prefer : variantsSizes.value[variantsSizes.value.length - 1];
    const entry = variants.value[chosenSize];
    if (entry) {
      if (typeof entry === 'string') return entry;
      return entry.fallback || entry.webp || entry.avif || Object.values(entry)[0];
    }
  }
  if (props.product && props.product.image_url) {
    const images = props.product.image_url;
    if (Array.isArray(images) && images.length) {
      const first = images[0];
      if (typeof first === 'string' && (/^https?:\/\//i.test(first) || first.startsWith('/'))) {
        return first.startsWith('/') ? `${props.baseUrl}${first}` : first;
      }
    } else if (typeof images === 'string' && images) {
      return images;
    }
  }
  return props.placeholder;
});

const wrapperStyle = computed(() => ({ ...props.styleObject }));
// CHANGED: imgStyle uses props.imgFit to allow contain for big image
const imgStyle = computed(() => ({ width: '100%', height: '100%', objectFit: props.imgFit, display: 'block' })); /* CHANGED */

</script>

<style scoped>
.product-responsive-image {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
}
.responsive-img {
  width: 100%;
  height: 100%;
  display: block;
  transition: opacity .18s ease-in;
  opacity: 1;
}
</style>