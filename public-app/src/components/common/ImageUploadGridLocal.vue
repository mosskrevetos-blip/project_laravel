<template>
  <div>
    <input
      ref="fileInput"
      type="file"
      multiple
      accept="image/*"
      class="d-none"
      @change="onFilesSelected"
    />

    <div class="image-grid">
      <template v-for="(slot, index) in imageSlots" :key="index">
        <!-- empty -->
        <div
          v-if="slot.type === 'empty'"
          class="image-slot empty"
          @click="triggerFileInput"
        >
          <v-icon size="34" color="grey">mdi-plus</v-icon>
        </div>

        <!-- image -->
        <div
          v-else
          class="image-slot filled"
          draggable="true"
          @dragstart="onDragStart(index)"
          @dragover.prevent
          @drop.prevent="onDrop(index)"
          @dragend="onDragEnd"
        >
          <img :src="slot.preview" alt="preview" class="slot-image" />

          <v-btn
            icon
            size="x-small"
            color="error"
            class="remove-btn"
            @click.stop="removeImage(index)"
          >
            <v-icon size="16">mdi-delete</v-icon>
          </v-btn>
        </div>
      </template>
    </div>

    <div class="text-caption text-medium-emphasis mt-2">
      Можна перетягувати фото для зміни порядку. Дублікати не додаються.
    </div>
  </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] }, // File[]
  maxFiles: { type: Number, default: 5 },
});

const emit = defineEmits(['update:modelValue', 'duplicates-skipped']);

const fileInput = ref(null);
const dragIndex = ref(null);

// internal: [{ file, key, preview }]
const items = ref([]);

function buildFileKey(file) {
  return `${file.name}__${file.size}__${file.lastModified}`;
}

function createItem(file) {
  return {
    file,
    key: buildFileKey(file),
    preview: URL.createObjectURL(file),
  };
}

function syncOut() {
  emit('update:modelValue', items.value.map(i => i.file));
}

function resetFromModel(files) {
  items.value.forEach(i => URL.revokeObjectURL(i.preview));
  items.value = (files || []).map(createItem);
}

watch(
  () => props.modelValue,
  (files) => {
    // синхронизируем только если длина/ключи отличаются
    const incoming = (files || []).map(buildFileKey).join('|');
    const current = items.value.map(i => i.key).join('|');
    if (incoming !== current) resetFromModel(files || []);
  },
  { immediate: true }
);

const imageSlots = computed(() => {
  const filled = items.value.map(i => ({ type: 'file', ...i }));
  const empties = Array.from(
    { length: Math.max(0, props.maxFiles - filled.length) },
    () => ({ type: 'empty' })
  );
  return [...filled, ...empties];
});

function triggerFileInput() {
  if (items.value.length >= props.maxFiles) return;
  fileInput.value?.click();
}

function onFilesSelected(e) {
  const selected = Array.from(e.target.files || []);
  if (!selected.length) return;

  const existingKeys = new Set(items.value.map(i => i.key));
  let skipped = 0;

  for (const file of selected) {
    if (items.value.length >= props.maxFiles) break;

    const key = buildFileKey(file);
    if (existingKeys.has(key)) {
      skipped++;
      continue;
    }

    items.value.push(createItem(file));
    existingKeys.add(key);
  }

  if (skipped > 0) emit('duplicates-skipped', skipped);

  syncOut();
  e.target.value = '';
}

function removeImage(index) {
  const item = items.value[index];
  if (!item) return;
  URL.revokeObjectURL(item.preview);
  items.value.splice(index, 1);
  syncOut();
}

function onDragStart(index) {
  dragIndex.value = index;
}

function onDrop(targetIndex) {
  if (dragIndex.value === null) return;

  const from = dragIndex.value;
  const to = targetIndex;

  if (from === to || !items.value[from] || !items.value[to]) {
    dragIndex.value = null;
    return;
  }

  const tmp = items.value[from];
  items.value.splice(from, 1);
  items.value.splice(to, 0, tmp);

  dragIndex.value = null;
  syncOut();
}

function onDragEnd() {
  dragIndex.value = null;
}

onUnmounted(() => {
  items.value.forEach(i => URL.revokeObjectURL(i.preview));
});
</script>

<style scoped>
.image-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px;
}
.image-slot {
  position: relative;
  height: 110px;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}
.image-slot.empty {
  border: 1px dashed rgba(255, 255, 255, 0.25);
  background: rgba(255, 255, 255, 0.04);
  cursor: pointer;
}
.image-slot.filled {
  border: 1px solid rgba(255, 255, 255, 0.2);
  cursor: grab;
}
.slot-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.remove-btn {
  position: absolute !important;
  top: 6px;
  right: 6px;
  z-index: 2;
  background: rgba(0, 0, 0, 0.55) !important;
  color: #fff !important;
}
@media (max-width: 960px) {
  .image-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>