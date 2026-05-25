// public-app/src/components/product/ProductImageGallery.vue
<template>
  <div class="product-image-gallery">
    <v-sheet class="gallery-sheet" elevation="1">
      <v-row>
        <v-col cols="12">
          <!-- BIG IMAGE (сверху) -->
          <div class="big-image-wrapper" @click="openLightbox">
            <ProductResponsiveImagePublic
              :product="product"
              :filename="currentFilename"
              :style-object="{ height: bigImageHeight, width: '100%' }"
              :sizes-list="[400,800,1200,2000]"
              :sizes-attr="'(max-width:600px) 400px, 800px'"
              :img-fit="'contain'"
              @load="onImageLoaded"
            />

            <!-- overlay prev/next buttons -->
            <div class="overlay-controls">
              <v-btn icon color="white" class="control-btn" @click.stop="prev">
                <v-icon>mdi-chevron-left</v-icon>
              </v-btn>
              <v-btn icon color="white" class="control-btn" @click.stop="next">
                <v-icon>mdi-chevron-right</v-icon>
              </v-btn>
            </div>

            <!-- zoom hint (всплывающий значок) -->
            <div class="zoom-hint" aria-hidden="true">
              <v-icon small color="white">mdi-magnify</v-icon>
            </div>
          </div>

          <!-- THUMBNAILS (ниже, всегда) -->
          <v-row class="mt-3" dense>
            <v-col
              v-for="(fname, idx) in filenames"
              :key="'thumb-' + idx"
              cols="3"
              class="d-flex"
            >
              <div
                class="thumb-wrapper-mobile"
                :class="{ 'thumb-active': idx === currentIndex }"
                @click="selectIndex(idx)"
              >
                <ProductResponsiveImagePublic
                  :product="product"
                  :filename="fname"
                  :style-object="{ height: '72px', width: '100%' }"
                  :sizes-list="[150,400]"
                  :sizes-attr="'72px'"
                />
              </div>
            </v-col>
          </v-row>
        </v-col>
      </v-row>
    </v-sheet>

    <!-- Lightbox dialog: показываем самый большой доступный URL -->
    <v-dialog v-model="lightbox" width="92%" max-width="1400">
      <template #default>
        <v-card>
          <v-card-text class="pa-0 d-flex align-center justify-center" style="background:#000;">
            <!-- CHANGED: добавлен @click для закрытия лайтбокса при клике по изображению -->
            <img
              v-if="largestImageUrl"
              :src="largestImageUrl"
              alt="photo"
              style="max-height:80vh; width:100%; object-fit:contain; display:block; cursor:zoom-out;"
              @click="lightbox = false"
            />
            <img
              v-else
              :src="currentFallbackUrl"
              alt="photo"
              style="max-height:80vh; width:100%; object-fit:contain; display:block; cursor:zoom-out;"
              @click="lightbox = false"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn text @click="lightbox = false">Закрыть</v-btn>
          </v-card-actions>
        </v-card>
      </template>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import ProductResponsiveImagePublic from '@/components/product/ProductResponsiveImagePublic.vue';

const props = defineProps({
  product: { type: Object, required: true },
  initialIndex: { type: Number, default: 0 },
});

const currentIndex = ref(props.initialIndex || 0);
const lightbox = ref(false);

watch(() => props.initialIndex, (v) => {
  if (typeof v === 'number') currentIndex.value = v;
});

// helper: get filenames array from product.image_url (array or json string)
function parseImageArray(product) {
  if (!product) return [];
  const images = product.image_url;
  if (!images) return [];
  if (Array.isArray(images)) return images;
  if (typeof images === 'string') {
    try {
      const parsed = JSON.parse(images);
      if (Array.isArray(parsed)) return parsed;
      return [images];
    } catch (e) {
      // plain string filename
      return [images];
    }
  }
  return [];
}

const filenames = computed(() => parseImageArray(props.product));
const currentFilename = computed(() => filenames.value[currentIndex.value] || filenames.value[0] || null);

// Размер большой области (можно быстро настроить)
const bigImageHeight = '520px';

// Поменять текущую миниатюру
function selectIndex(idx) {
  if (idx < 0 || idx >= filenames.value.length) return;
  currentIndex.value = idx;
}

function prev() {
  if (!filenames.value.length) return;
  currentIndex.value = (currentIndex.value - 1 + filenames.value.length) % filenames.value.length;
}

function next() {
  if (!filenames.value.length) return;
  currentIndex.value = (currentIndex.value + 1) % filenames.value.length;
}

function openLightbox() {
  lightbox.value = true;
}

function onImageLoaded() {
  // placeholder hook if needed
}

// --- вычисляем самый большой доступный URL из manifest (image_variants) ---
const largestImageUrl = computed(() => {
  const fname = currentFilename.value;
  if (!props.product || !fname) return '';

  const manifestRoot = props.product.image_variants || {};
  const manifest = manifestRoot[fname] || null;
  if (manifest && typeof manifest === 'object') {
    const sizeKeys = Object.keys(manifest)
      .filter(k => k !== 'placeholder')
      .map(k => Number(k))
      .filter(n => !isNaN(n))
      .sort((a,b) => b - a); // descending
    if (sizeKeys.length) {
      const entry = manifest[String(sizeKeys[0])];
      if (entry) {
        // prefer avif -> webp -> fallback -> any
        return entry.avif || entry.webp || entry.fallback || Object.values(entry)[0] || '';
      }
    }
  }

  // fallback: try to construct largest-suffixed filename (2000)
  const dot = fname.lastIndexOf('.');
  const base = dot === -1 ? fname : fname.slice(0, dot);
  const ext = dot === -1 ? '' : fname.slice(dot);
  const pid = props.product.id || '';
  if (pid) {
    const baseUrl = (import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_PUBLIC_SITE_URL || window.location.origin).replace(/\/$/, '');
    return `${baseUrl}/storage/products/${pid}/${base}_2000${ext}`;
  }

  return '';
});

// currentFallbackUrl - mid/large fallback used if largestImageUrl empty
const currentFallbackUrl = computed(() => {
  const fname = currentFilename.value;
  const pid = props.product?.id || '';
  if (!fname) return '';
  const dot = fname.lastIndexOf('.');
  const base = dot === -1 ? fname : fname.slice(0, dot);
  const ext = dot === -1 ? '' : fname.slice(dot);
  if (pid) {
    const baseUrl = (import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_PUBLIC_SITE_URL || window.location.origin).replace(/\/$/, '');
    return `${baseUrl}/storage/products/${pid}/${base}_1200${ext}`;
  }
  return '';
});
</script>

<style scoped>
.big-image-wrapper {
  position: relative;
  width: 100%;
  height: 520px;
  overflow: hidden;
  cursor: zoom-in;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f6f6f6;
}

.overlay-controls {
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between;
  transform: translateY(-50%);
  pointer-events: none;
  padding: 0 8px;
}

.control-btn {
  pointer-events: auto;
  background: rgba(0, 0, 0, 0.35);
  min-width: 40px;
  min-height: 40px;
}

.zoom-hint {
  position: absolute;
  right: 12px;
  bottom: 12px;
  background: rgba(0,0,0,0.35);
  padding: 6px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.thumb-wrapper-mobile {
  cursor: pointer;
  border-radius: 4px;
  overflow: hidden;
  border: 2px solid transparent;
  width: 100%;
  height: 72px;
}
.thumb-wrapper-mobile.thumb-active {
  border-color: var(--v-theme-primary, #0D47A1);
}
</style>