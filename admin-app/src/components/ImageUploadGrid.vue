<!-- Файл: admin-app/src/components/ImageUploadGrid.vue -->
<template>
  <div>
    <!-- Скрытый input[type=file], который мы будем "нажимать" программно -->
    <input
      type="file"
      ref="fileInput"
      multiple
      :accept="accept"
      :disabled="isUploading"
      @change="onFilesSelected"
      style="display: none"
    />

    <v-row dense>
      <!-- Проходим по 8 слотам -->
      <v-col v-for="(slot, index) in imageSlots" :key="index" cols="3">
        <!-- 
          Слот-контейнер:
          - :draggable - разрешает перетаскивание
          - @dragstart - что делать, когда начали тащить
          - @dragover - обязательно, чтобы разрешить "бросание"
          - @drop - что делать, когда "бросили" на него
        -->
        <v-sheet
          border
          rounded
          class="d-flex align-center justify-center image-slot"
          :class="{ 'droppable': dragOverIndex === index }"
          height="120"
          :draggable="slot.type !== 'empty' && !isUploading"
          @dragstart="onDragStart(index)"
          @dragover.prevent="onDragOver(index)"
          @dragleave="dragOverIndex = null"
          @drop="onDrop(index)"
        >
          <!-- 1. Слот ПУСТОЙ (+) -->
          <v-btn
            v-if="slot.type === 'empty'"
            icon="mdi-plus"
            variant="text"
            color="grey"
            @click="triggerFileInput"
            :disabled="isUploading || totalImages >= maxFiles"
          ></v-btn>

          <!-- 2. Слот с ИЗОБРАЖЕНИЕМ (превью) -->
          <div v-if="slot.type !== 'empty'" class="image-preview-wrapper">
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

            <!-- 3. Крестик для удаления -->
            <v-btn
              icon="mdi-close-circle"
              color="black"
              size="x-small"
              variant="text"
              class="delete-btn"
              @click="removeImage(index)"
            ></v-btn>

            <!-- 4. Индикатор загрузки (для новых файлов) -->
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
import { ref, computed, watch } from 'vue';

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
  }
});

const adminSiteUrl = import.meta.env.VITE_ADMIN_SITE_URL;

const emit = defineEmits(['update:modelValue', 'update:newFiles']);

const fileInput = ref(null); // Ссылка на скрытый input
const isUploading = ref(false); // Для индикатора загрузки
const dragStartIndex = ref(null); // Индекс элемента, который тащим
const dragOverIndex = ref(null); // Индекс элемента, над которым тащим

// --- Локальные копии для управления ---
const existingImages = ref([...props.modelValue]);
const internalNewFiles = ref([...props.newFiles]);

// Наблюдаем за изменениями извне (например, при сбросе формы)
watch(() => props.modelValue, (newVal) => {
  existingImages.value = [...newVal];
});
watch(() => props.newFiles, (newVal) => {
  internalNewFiles.value = [...newVal];
});

// --- ГЛАВНАЯ ЛОГИКА ---

// 1. Создаём превью для новых файлов
const newFilePreviews = computed(() => 
  internalNewFiles.value.map(file => ({
    url: URL.createObjectURL(file),
    file: file,
    type: 'new'
  }))
);

// 2. Создаём слоты для существующих
const existingImageSlots = computed(() => 
  existingImages.value.map(url => ({
    url: `${adminSiteUrl}/storage/products/${url}`,
    name: url,
    type: 'existing'
  }))
);

// 3. Считаем общее число картинок
const totalImages = computed(() => existingImageSlots.value.length + newFilePreviews.value.length);

// 4. Собираем 8 слотов
const imageSlots = computed(() => {
  const items = [...existingImageSlots.value, ...newFilePreviews.value];
  const emptySlots = Array(props.maxFiles - items.length).fill({ type: 'empty' });
  return [...items, ...emptySlots];
});

// --- Обработка файлов ---

function triggerFileInput() {
  fileInput.value.click();
}

async function onFilesSelected(event) {
  const files = Array.from(event.target.files);
  if (!files.length) return;

  isUploading.value = true;
  const availableSlots = props.maxFiles - totalImages.value;
  const filesToUpload = files.slice(0, availableSlots);

  // Создаём массив File объектов
  const newFilesArray = [...internalNewFiles.value, ...filesToUpload];
  
  // Имитируем задержку на создание превью (похоже на индикатор загрузки)
  await new Promise(res => setTimeout(res, 500)); 

  // Обновляем родительский компонент
  emit('update:newFiles', newFilesArray);
  
  isUploading.value = false;
  fileInput.value.value = ''; // Сбрасываем input
}

// --- Удаление ---
function removeImage(index) {
  const item = imageSlots.value[index];

  if (item.type === 'existing') {
    const newExisting = existingImages.value.filter(url => url !== item.name);
    emit('update:modelValue', newExisting);
  } else if (item.type === 'new') {
    const newFiles = internalNewFiles.value.filter(file => file !== item.file);
    emit('update:newFiles', newFiles);
  }
}

// --- Логика Drag-and-Drop ---
function onDragStart(index) {
  dragStartIndex.value = index;
}

function onDragOver(index) {
  dragOverIndex.value = index;
}

function onDrop(dropIndex) {
  dragOverIndex.value = null;
  if (dragStartIndex.value === null || dragStartIndex.value === dropIndex) return;

  const dragItem = imageSlots.value[dragStartIndex.value];
  if (imageSlots.value[dropIndex].type !== 'empty') {
    // Если "бросили" на другое изображение, меняем их местами
    const dropItem = imageSlots.value[dropIndex];
    
    // Создаём два новых массива (существующие и новые)
    let newExisting = [...existingImages.value];
    let newFiles = [...internalNewFiles.value];

    // Находим реальные индексы в оригинальных массивах
    const dragExistingIndex = newExisting.indexOf(dragItem.name);
    const dragNewIndex = newFiles.indexOf(dragItem.file);
    const dropExistingIndex = newExisting.indexOf(dropItem.name);
    const dropNewIndex = newFiles.indexOf(dropItem.file);

    // Меняем местами, только если оба элемента одного типа
    if (dragItem.type === 'existing' && dropItem.type === 'existing') {
      [newExisting[dragExistingIndex], newExisting[dropExistingIndex]] = [newExisting[dropExistingIndex], newExisting[dragExistingIndex]];
    } else if (dragItem.type === 'new' && dropItem.type === 'new') {
      [newFiles[dragNewIndex], newFiles[dropNewIndex]] = [newFiles[dropNewIndex], newFiles[dragNewIndex]];
    }
    // (Для простоты, D&D между "новыми" и "существующими" пока не реализуем)

    emit('update:modelValue', newExisting);
    emit('update:newFiles', newFiles);

  } else {
    // Если "бросили" на пустой слот, просто перемещаем
    // (Эта логика сложнее, т.к. нужно "сдвинуть" массив. Оставим на будущее)
  }

  dragStartIndex.value = null;
}
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
/* Стили для "пустого" слота */
.image-slot:not(:has(.image-preview-wrapper)) {
  cursor: pointer;
}

/* Подсветка слота, над которым проносим картинку */
.droppable {
  border: 2px dashed #0D47A1; /* Vuetify primary color */
  background-color: #E3F2FD; /* Vuetify blue lighten-5 */
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