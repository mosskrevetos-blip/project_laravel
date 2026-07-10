<template>
  <div>
    <input
      ref="fileInput"
      type="file"
      multiple
      accept="image/*"
      :disabled="isFull"
      @change="onFilesSelected"
      style="display:none"
    />

    <v-row dense>
      <v-col v-for="(slot, index) in imageSlots" :key="slot.key" cols="3">
        <v-sheet
          border
          rounded
          class="d-flex align-center justify-center image-slot"
          :class="{ droppable: dragOverIndex === index }"
          height="120"
          :draggable="slot.type !== 'empty'"
          @dragstart="onDragStart(index)"
          @dragover.prevent="onDragOver(index)"
          @dragleave="onDragLeave"
          @drop.prevent="onDrop(index)"
        >
          <v-btn
            v-if="slot.type === 'empty'"
            icon="mdi-plus"
            variant="text"
            color="grey"
            @click="triggerFileInput"
            :disabled="isFull"
          />

          <div v-else class="image-preview-wrapper">
            <img
              :src="slot.preview"
              alt="preview"
              style="width:100%;height:120px;object-fit:cover;display:block;"
            />

            <v-btn
              icon="mdi-close-circle"
              color="black"
              size="x-small"
              variant="text"
              class="delete-btn"
              @click.stop="removeImage(index)"
            />
          </div>
        </v-sheet>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { // existing images [{id, url}]
    type: Array,
    default: () => [],
  },
  newFiles: { // File[]
    type: Array,
    default: () => [],
  },
  maxFiles: {
    type: Number,
    default: 5,
  },
});

const emit = defineEmits(['update:modelValue', 'update:newFiles', 'reorder']);

const fileInput = ref(null);
const dragStartIndex = ref(null);
const dragOverIndex = ref(null);

const existing = ref([]); // [{id,url,type:'existing'}]
const locals = ref([]);   // [{file,preview,type:'new'}]

watch(
  () => props.modelValue,
  (v) => {
    existing.value = (Array.isArray(v) ? v : []).map((x, i) => ({
      id: x.id ?? null,
      url: x.url ?? '',
      type: 'existing',
      _k: `e-${x.id ?? i}`,
    }));
  },
  { immediate: true }
);

watch(
  () => props.newFiles,
  (v) => {
    // rebuild locals from files
    locals.value.forEach((l) => {
      try { URL.revokeObjectURL(l.preview); } catch {}
    });
    locals.value = (Array.isArray(v) ? v : []).map((f, i) => ({
      file: f,
      preview: URL.createObjectURL(f),
      type: 'new',
      _k: `n-${i}-${f.name}`,
    }));
  },
  { immediate: true }
);

onUnmounted(() => {
  locals.value.forEach((l) => {
    try { URL.revokeObjectURL(l.preview); } catch {}
  });
});

const items = computed(() => [...existing.value, ...locals.value]);
const isFull = computed(() => items.value.length >= props.maxFiles);

const imageSlots = computed(() => {
  const list = items.value.map((it, i) => ({
    ...it,
    key: it._k ?? `i-${i}`,
    preview: it.type === 'existing' ? it.url : it.preview,
  }));
  const rest = Math.max(0, props.maxFiles - list.length);
  for (let i = 0; i < rest; i++) {
    list.push({ type: 'empty', key: `empty-${i}` });
  }
  return list;
});

function triggerFileInput() {
  if (isFull.value) return;
  fileInput.value?.click();
}

function onFilesSelected(e) {
  const files = Array.from(e.target.files || []);
  if (!files.length) return;

  const free = props.maxFiles - items.value.length;
  const accepted = files.slice(0, free);

  const nextFiles = [...(props.newFiles || []), ...accepted];
  emit('update:newFiles', nextFiles);
  e.target.value = '';
}

function onDragStart(i) {
  dragStartIndex.value = i;
}
function onDragOver(i) {
  dragOverIndex.value = i;
}
function onDragLeave() {
  dragOverIndex.value = null;
}

function nonEmptyIndex(globalIdx) {
  let c = 0;
  for (let i = 0; i < globalIdx; i++) {
    if (imageSlots.value[i]?.type !== 'empty') c++;
  }
  return c;
}

function onDrop(dropIdx) {
  dragOverIndex.value = null;
  const start = dragStartIndex.value;
  dragStartIndex.value = null;
  if (start == null) return;

  const startItemIdx = nonEmptyIndex(start);
  const dropItemIdx = nonEmptyIndex(dropIdx);

  const arr = [...items.value];
  if (startItemIdx < 0 || startItemIdx >= arr.length) return;

  const [moved] = arr.splice(startItemIdx, 1);
  if (dropItemIdx >= arr.length) arr.push(moved);
  else arr.splice(dropItemIdx, 0, moved);

  const nextExisting = arr.filter((x) => x.type === 'existing').map((x) => ({ id: x.id, url: x.url }));
  const nextFiles = arr.filter((x) => x.type === 'new').map((x) => x.file);

  emit('update:modelValue', nextExisting);
  emit('update:newFiles', nextFiles);
  emit('reorder', arr.map((x) => (x.type === 'existing' ? { type: 'existing', id: x.id } : { type: 'new', name: x.file?.name })));
}

function removeImage(globalIdx) {
  const slot = imageSlots.value[globalIdx];
  if (!slot || slot.type === 'empty') return;

  if (slot.type === 'existing') {
    const next = existing.value.filter((x) => x.id !== slot.id);
    emit('update:modelValue', next.map((x) => ({ id: x.id, url: x.url })));
  } else {
    const next = (props.newFiles || []).filter((f) => f !== slot.file);
    emit('update:newFiles', next);
  }
}
</script>

<style scoped>
.image-slot { position: relative; cursor: grab; background-color: #f0f0f0; }
.image-slot:hover { background-color: #e0e0e0; }
.droppable { border: 2px dashed #0D47A1; background-color: #E3F2FD; }
.image-preview-wrapper { position: relative; width: 100%; height: 100%; }
.delete-btn { position: absolute; top: 0; right: 0; background: rgba(255,255,255,.7); }
</style>