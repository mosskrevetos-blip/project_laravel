<template>
  <div>
    <!-- Добавления -->
    <!-- Скрытый input[type=file], который мы будем "нажимать" программно -->
    <!-- конец добавлений -->

    <input
      type="file"
      ref="fileInput"
      multiple
      :accept="accept"
      :disabled="isUploading || isFull"
      @change="onFilesSelected"
      style="display: none"
    />

    <v-row dense>
      <!-- Проходим по слотам -->
      <v-col v-for="(slot, index) in imageSlots" :key="index" cols="3">
        <v-sheet
          border
          rounded
          class="d-flex align-center justify-center image-slot"
          :class="{ 'droppable': dragOverIndex === index }"
          height="120"
          :draggable="slot.type !== 'empty' && !isUploading"
          @dragstart="onDragStart(index)"
          @dragover.prevent="onDragOver(index)"
          @dragleave="onDragLeave"
          @drop="onDrop(index)"
        >
          <!-- Пустой слот -->
          <v-btn
            v-if="slot.type === 'empty'"
            icon="mdi-plus"
            variant="text"
            color="grey"
            @click="triggerFileInput"
            :disabled="isUploading || isFull"
          ></v-btn>

          <!-- Слот с превью -->
          <div v-else class="image-preview-wrapper">
            <v-img
              :src="slot.url"
              height="120"
              width="100%"
              cover
              class="rounded"
            >
              <template v-slot:placeholder>
                <div class="d-flex align-center justify-center fill-height">
                  <v-progress-circular indeterminate color="grey-lighten-1"></v-progress-circular>
                </div>
              </template>
            </v-img>

            <!-- Кнопка удаления -->
            <v-btn
              icon="mdi-close-circle"
              color="black"
              size="x-small"
              variant="text"
              class="delete-btn"
              @click="removeImage(index)"
            ></v-btn>

            <!-- Индикатор загрузки (если нужно) -->
            <div v-if="slot.loading" class="loading-overlay">
              <v-progress-circular indeterminate color="white" size="32"></v-progress-circular>
            </div>
          </div>
        </v-sheet>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';

/* Добавления
   Пояснение: теперь компонент эмитит событие 'reorder' с комбинированным порядком,
   чтобы родитель мог при необходимости сохранить точную позицию (existing/new).
   Также обновляем update:modelValue и update:newFiles после перестановки.
   конец добавлений */

const props = defineProps({
  // Массив СУЩЕСТВУЮЩИХ имён файлов (с бэкенда)
  modelValue: {
    type: Array,
    default: () => [],
  },
  // Массив НОВЫХ ФАЙЛОВ (File объекты)
  newFiles: {
    type: Array,
    default: () => [],
  },
  maxFiles: {
    type: Number,
    default: 8,
  },
  accept: {
    type: String,
    default: 'image/jpeg,image/png,image/gif,image/bmp,image/tiff,image/webp'
  },
  // При редактировании можем получать product id (опционально)
  productId: {
    type: [Number, String],
    default: null,
  }
});

// Изменения: добавлен emit 'reorder'
const emit = defineEmits(['update:modelValue', 'update:newFiles', 'reorder']);
// Конец изменений

const adminSiteUrl = import.meta.env.VITE_ADMIN_SITE_URL || '';

const fileInput = ref(null);
const isUploading = ref(false);
const dragStartIndex = ref(null); // индекс по imageSlots (включая пустые)
const dragOverIndex = ref(null);

// Локальные реактивные копии
const existingImages = ref([...props.modelValue]); // массив строк (имён файлов)
const newPreviews = ref([]); // массив объектов { file, url, loading }
const internalNewFiles = ref([...props.newFiles]); // массив File

// Синхронизируемся с внешними пропсами
watch(() => props.modelValue, (v) => {
  existingImages.value = Array.isArray(v) ? [...v] : [];
});
watch(() => props.newFiles, (v) => {
  internalNewFiles.value = Array.isArray(v) ? [...v] : [];
  rebuildPreviewsFromFiles();
});

// --- Помощники для валидации ---
const MAX_IMAGE_SIZE_MB = Number(import.meta.env.VITE_MAX_IMAGE_SIZE_MB || 2);
const allowedTypes = computed(() => {
  return props.accept.split(',').map(s => s.trim()).filter(Boolean);
});

// Флаги
const isFull = computed(() => (existingImages.value.length + internalNewFiles.value.length) >= props.maxFiles);

function createPreviewForFile(file) {
  return {
    file,
    url: URL.createObjectURL(file),
    loading: false,
  };
}

function rebuildPreviewsFromFiles() {
  // Удаляем старые URL'ы
  newPreviews.value.forEach(p => {
    try { URL.revokeObjectURL(p.url); } catch (e) {}
  });
  newPreviews.value = internalNewFiles.value.map(f => createPreviewForFile(f));
}

// --- Формирование слотов: объединяем existing + newPreviews, сохраняем типы и источники ---
// Изменения: создаём комбинированный список слотов, который используется для DnD и визуализации.
// Конец изменений
const existingImageSlots = computed(() =>
  existingImages.value.map(url => {
    const base = adminSiteUrl.replace(/\/$/, '');
    let fullUrl;
    if (props.productId !== null && props.productId !== undefined && props.productId !== '') {
      fullUrl = `${base}/storage/products/${props.productId}/${url}`;
    } else {
      fullUrl = `${base}/storage/products/${url}`;
    }
    return {
      url: fullUrl,
      name: url,
      type: 'existing',
      loading: false,
    };
  })
);

const newFilePreviews = computed(() =>
  newPreviews.value.map(p => ({
    url: p.url,
    file: p.file,
    type: 'new',
    loading: p.loading || false,
  }))
);

// combinedItems — массив объектов { type: 'existing'|'new' }
const combinedItems = computed(() => {
  return [...existingImageSlots.value, ...newFilePreviews.value];
});

const totalImages = computed(() => combinedItems.value.length);

const imageSlots = computed(() => {
  const items = [...combinedItems.value];
  const emptySlotsCount = Math.max(0, props.maxFiles - items.length);
  const emptySlots = Array.from({ length: emptySlotsCount }, () => ({ type: 'empty' }));
  return [...items, ...emptySlots];
});

// Очистка object URL при размонтировании
onUnmounted(() => {
  newPreviews.value.forEach(p => {
    try { URL.revokeObjectURL(p.url); } catch (e) {}
  });
});

// --- Обработка выбора файлов ---
function triggerFileInput() {
  if (isFull.value) return;
  fileInput.value && fileInput.value.click();
}

async function onFilesSelected(event) {
  const files = Array.from(event.target.files || []);
  if (!files.length) return;

  isUploading.value = true;

  // Количество доступных слотов
  const availableSlots = props.maxFiles - totalImages.value;
  const filesToProcess = files.slice(0, availableSlots);

  const accepted = [];
  const errors = [];

  for (const f of filesToProcess) {
    // Проверка типа
    if (allowedTypes.value.length && !allowedTypes.value.includes(f.type)) {
      errors.push(`${f.name}: неподдерживаемый формат (${f.type})`);
      continue;
    }
    // Проверка размера (в байтах)
    if (f.size > MAX_IMAGE_SIZE_MB * 1024 * 1024) {
      errors.push(`${f.name}: превышен максимальный размер ${MAX_IMAGE_SIZE_MB}MB`);
      continue;
    }
    accepted.push(f);
  }

  if (errors.length) {
    alert('Ошибки при добавлении файлов:\n' + errors.join('\n'));
  }

  if (!accepted.length) {
    isUploading.value = false;
    if (fileInput.value) fileInput.value.value = '';
    return;
  }

  internalNewFiles.value = [...internalNewFiles.value, ...accepted];
  accepted.forEach(f => newPreviews.value.push(createPreviewForFile(f)));

  // Эмитим в родительский компонент только File объекты
  emit('update:newFiles', [...internalNewFiles.value]);
  // Эмитим также reorder чтобы родитель видел текущее сочетание
  emitReorderEvent();

  isUploading.value = false;
  if (fileInput.value) fileInput.value.value = '';
}

// --- Удаление изображения ---
function removeImage(slotIndex) {
  const slot = imageSlots.value[slotIndex];
  if (!slot) return;

  if (slot.type === 'existing') {
    existingImages.value = existingImages.value.filter(n => n !== slot.name);
    emit('update:modelValue', [...existingImages.value]);
  } else if (slot.type === 'new') {
    const previewIndex = newPreviews.value.findIndex(p => p.url === slot.url);
    if (previewIndex !== -1) {
      try { URL.revokeObjectURL(newPreviews.value[previewIndex].url); } catch (e) {}
      newPreviews.value.splice(previewIndex, 1);
      const fileIndex = internalNewFiles.value.findIndex(f => f === slot.file);
      if (fileIndex !== -1) internalNewFiles.value.splice(fileIndex, 1);
      emit('update:newFiles', [...internalNewFiles.value]);
    }
  }

  // После удаления — уведомляем родителя о новом порядке
  emitReorderEvent();
}

// --- Drag & Drop: реализуем перестановку по комбинированному списку ---
// Изменения: реализована поведение SWAP при дропе на существующий элемент,
// и перемещение в конец, если дропнули на пустой слот.
// Конец изменений
function onDragStart(index) {
  // dragStartIndex хранит глобальный индекс по imageSlots (включая пустые)
  dragStartIndex.value = index;
}
function onDragOver(index) {
  dragOverIndex.value = index;
}
function onDragLeave() {
  dragOverIndex.value = null;
}

/* Изменения
   Проблема: ранее start и drop использовались как индексы в combinedItems,
   но на самом деле эти индексы — по imageSlots (включая пустые слоты).
   Решение: переводим глобальные индексы в индексы по массиву непустых элементов
   с помощью helper globalIndexToItemsIndex, и выполняем SWAP или move-to-end.
*/
// Добавления: helper для перевода индексов
function globalIndexToItemsIndex(globalIndex) {
  // Считаем, сколько непустых слотов расположено до позиции globalIndex (не включая globalIndex)
  const displayed = imageSlots.value; // текущие слоты (включая empty)
  let count = 0;
  for (let i = 0; i < Math.min(globalIndex, displayed.length); i++) {
    if (displayed[i].type && displayed[i].type !== 'empty') count++;
  }
  return count; // индекс в items (перед ним)
}
// Конец изменений

function onDrop(dropIndex) {
  dragOverIndex.value = null;
  const globalStart = dragStartIndex.value;
  if (globalStart === null || globalStart === undefined) return;

  // Переводим глобальные индексы (по imageSlots) в индексы по items (non-empty)
  const startIdx = globalIndexToItemsIndex(globalStart);
  const targetIdx = globalIndexToItemsIndex(dropIndex);

  // items - текущий массив непустых элементов (existing + new)
  const items = [...combinedItems.value];

  // защита: если start выходит за пределы items (например, drag с пустого слота) — ничего не делаем
  if (startIdx < 0 || startIdx >= items.length) {
    dragStartIndex.value = null;
    return;
  }

  // Если target equals items.length => дроп на пустой слот после всех элементов -> move to end
  if (targetIdx >= items.length) {
    const [moved] = items.splice(startIdx, 1);
    items.push(moved);
  } else {
    // SWAP: поменяем местами элементы startIdx и targetIdx
    const tmp = items[targetIdx];
    items[targetIdx] = items[startIdx];
    items[startIdx] = tmp;
  }

  // Пересобираем existingImages и internalNewFiles в новом порядке
  const newExisting = [];
  const newFiles = [];

  for (const it of items) {
    if (it.type === 'existing') {
      newExisting.push(it.name);
    } else if (it.type === 'new') {
      // Найдём соответствующий File в internalNewFiles по preview url или по объекту
      // Сначала пробуем по preview url
      const idx = newPreviews.value.findIndex(p => p.url === it.url);
      if (idx !== -1 && internalNewFiles.value[idx]) {
        newFiles.push(internalNewFiles.value[idx]);
      } else {
        // fallback: match by file identity if available
        if (it.file) {
          const fi = internalNewFiles.value.findIndex(f => f === it.file);
          if (fi !== -1) newFiles.push(internalNewFiles.value[fi]);
        }
      }
    }
  }

  // Применяем новые массивы
  existingImages.value = newExisting;
  internalNewFiles.value = [...newFiles];

  // Перстроим превью в том же порядке: пересоздадим newPreviews из internalNewFiles
  newPreviews.value.forEach(p => {
    try { URL.revokeObjectURL(p.url); } catch (e) {}
  });
  newPreviews.value = internalNewFiles.value.map(f => createPreviewForFile(f));

  // Эмитим обновления в родителя
  emit('update:modelValue', [...existingImages.value]);
  emit('update:newFiles', [...internalNewFiles.value]);

  // Эмитим комбинированный порядок для удобства сохранения (родитель может принять решение, как сохранить)
  emitReorderEvent();

  dragStartIndex.value = null;
}
/* Конец изменений */

// Добавления: вспомогательная функция для эмита комбинированного порядка
function emitReorderEvent() {
  // Формируем массив вида [{type:'existing', name:'a.jpg'}, {type:'new', fileIndex:0, fileName:'...'}, ...]
  const items = [];
  // Составляем порядок по текущему отображаемому списку (без пустых)
  const displayed = imageSlots.value.filter(i => i.type !== 'empty').map(i => i);

  for (const d of displayed) {
    if (d.type === 'existing') {
      items.push({ type: 'existing', name: d.name });
    } else if (d.type === 'new') {
      const idx = internalNewFiles.value.findIndex(f => {
        const prev = newPreviews.value.find(p => p.file === f);
        return prev && prev.url === d.url;
      });
      items.push({ type: 'new', fileIndex: idx, fileName: internalNewFiles.value[idx]?.name || null });
    }
  }
  emit('reorder', items);
}
// конец добавлений
</script>

<style scoped>
.image-slot {
  position: relative;
  cursor: grab;
  background-color: #f0f0f0;
}
.image-slot:hover {
  background-color: #e0e0e0;
}
.droppable {
  border: 2px dashed #0D47A1;
  background-color: #E3F2FD;
}

.image-preview-wrapper {
  position: relative;
  width: 100%;
  height: 100%;
}
.delete-btn {
  position: absolute;
  top: 0;
  right: 0;
  background-color: rgba(255, 255, 255, 0.7);
}
.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
}
</style>