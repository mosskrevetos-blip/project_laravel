<template>
  <v-container>
    <div class="d-flex justify-space-between align-center mb-4">
      <h1 class="text-h4">Управление товарами</h1>
      <v-btn color="primary" @click="openDialog()">Добавить товар</v-btn>
    </div>

    <v-data-table
      :headers="headers"
      :items="productStore.products"
      :loading="productStore.loading"
      class="elevation-1"
    >
      <template v-slot:item.category="{ item }">
        {{ item.category?.title || 'Без категории' }}
      </template>

      <template v-slot:item.actions="{ item }">
        
        <v-tooltip bottom>
          <template #activator="{ props }">
            <!-- CHANGED: добавил класс edit-icon, плавный переход цвета/opacity, и привязку opacity -->
            <v-icon
              class="mr-2 edit-icon"                         
              v-bind="props"
              :color="isLocked(item) ? 'grey' : 'primary'"  
              @click="onEditClick(item)"
              :title="editTitle(item)"
              :style="{ cursor: isLocked(item) ? 'not-allowed' : 'pointer', opacity: isLocked(item) ? 0.6 : 1 }"
            >
              mdi-pencil
            </v-icon>
          </template>
          <span>{{ editTooltip(item) }}</span>
        </v-tooltip>

        <v-icon color="error" @click="deleteItem(item)">mdi-delete</v-icon>
      </template>
    </v-data-table>

    <!-- Диалоговое окно -->
    <v-dialog v-model="dialog" max-width="800px" persistent>
      <v-card>
        <v-form ref="form" @submit.prevent="saveItem">

          <v-card-title class="cards">
            <span class="text-h5">{{ formTitle }}</span>
            <v-card-actions class="card">
              <v-btn color="grey" text @click="closeDialog" :disabled="isSaving">Отмена</v-btn>
              <v-btn color="primary" type="submit" :loading="isSaving">Сохранить</v-btn>
            </v-card-actions>
          </v-card-title>

          <v-card-text>
            <v-container>
              <v-row>
                <v-col cols="12">
                  <v-text-field
                    label="Название товара (начните вводить для подбора категории)"
                    v-model="editedItem.title"
                    variant="outlined"
                    class="mb-3"
                    :rules="[rules.required]"
                    :error-messages="errors.title"
                    @update:model-value="onTitleInput"
                  ></v-text-field>
                </v-col>
                
                <v-col cols="12">
                  <v-select
                    label="Основная категория"
                    v-model="editedItem.category_id"
                    :items="hierarchicalSuggestedList"
                    item-title="indentedTitle"
                    item-value="id"
                    variant="outlined"
                    class="mb-3"
                    @update:modelValue="onCategoryChange"
                    :rules="[rules.required]"
                    :loading="isLoadingCategories"
                    :error-messages="errors.category_id"
                  ></v-select>
                </v-col>

                <v-col cols="12" class="text-center py-0" v-if="!showAllCategoriesSelector">
                  <a href="#" @click.prevent="showAllCategoriesSelector = true" class="text-caption">Категория подобрана неверно? Показать все.</a>
                </v-col>

                <v-col cols="12" v-if="showAllCategoriesSelector">
                  <v-select
                    label="Дополнительная категория"
                    v-model="editedItem.secondary_category_id"
                    :items="hierarchicalAllList"
                    item-title="indentedTitle"
                    item-value="id"
                    variant="outlined"
                    class="mb-3"
                    clearable
                    :error-messages="errors.secondary_category_id"
                  ></v-select>
                </v-col>

                <v-col cols="12"><v-divider></v-divider></v-col>

                <!-- Динамические поля атрибутов -->
                <v-col cols="12" v-if="categoryStore.selectedCategoryAttributes.length > 0">
                  <h3 class="text-subtitle-1 mb-2">Характеристики</h3>
                  <template v-for="attribute in categoryStore.selectedCategoryAttributes" :key="attribute.id">
                    <v-text-field
                      v-if="attribute.type === 'text' || attribute.type === 'number'"
                      :label="attribute.name"
                      :type="attribute.type"
                      v-model="editedItem.properties[attribute.slug]"
                      variant="outlined"
                      class="mb-3"
                      :error-messages="errors[`properties.${attribute.slug}`]"
                    ></v-text-field>
                    <v-checkbox
                      v-if="attribute.type === 'boolean'"
                      :label="attribute.name"
                      v-model="editedItem.properties[attribute.slug]"
                      :error-messages="errors[`properties.${attribute.slug}`]"
                    ></v-checkbox>
                    <v-select
                      v-if="attribute.type === 'select'"
                      :label="attribute.name"
                      :items="attribute.options"
                      item-title="value"
                      item-value="value"
                      v-model="editedItem.properties[attribute.slug]"
                      variant="outlined"
                      class="mb-3"
                      :error-messages="errors[`properties.${attribute.slug}`]"
                    ></v-select>
                  </template>
                  <v-divider class="my-4"></v-divider>
                </v-col>
                
                <!-- Остальные общие поля -->
                <v-col cols="12"><h3 class="text-subtitle-1 mb-2">Основная информация</h3></v-col>
                <v-col cols="12" md="4">
                  <v-text-field v-model="editedItem.sku" label="Артикул (SKU)" variant="outlined" class="mb-3" :rules="[rules.required]" :error-messages="errors.sku"></v-text-field>
                </v-col>
                <v-col cols="12" md="4">
                  <v-text-field v-model="editedItem.price" label="Цена" type="number" variant="outlined" class="mb-3" :rules="[rules.required, rules.positive]" :error-messages="errors.price"></v-text-field>
                </v-col>
                <v-col cols="12" md="4">
                  <v-text-field v-model="editedItem.quantity" label="Количество" type="number" variant="outlined" class="mb-3" :rules="[rules.required, rules.integer]" :error-messages="errors.quantity"></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-text-field v-model="editedItem.city" label="Город продажи" variant="outlined" class="mb-3" :rules="[rules.required]" :error-messages="errors.city"></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-textarea v-model="editedItem.description" label="Описание" variant="outlined" class="mb-3"></v-textarea>
                </v-col>
                <!-- Блок вставки изображений -->
                <v-col cols="12"><v-divider></v-divider></v-col>
                <v-col cols="12">
                  <h3 class="text-subtitle-1 mb-2">Изображения (до 8 шт.)</h3>
                  
                  <ImageUploadGrid
                    v-model="editedItem.image_url"
                    v-model:newFiles="newImages"
                    :product-id="editedItem.id"
                    :image-variants="editedItem.image_variants"
                  />
                  
                </v-col>

                <!-- Блок для видео -->
                <v-col cols="12"><v-divider></v-divider></v-col>
                <v-col cols="12">
                   <h3 class="text-subtitle-1 mb-2">Видео</h3>
                  <div v-for="(videoUrl, index) in editedItem.video_urls" :key="index" class="d-flex align-center mb-2">
                    <v-text-field
                      :label="`Ссылка на видео #${index + 1}`"
                      v-model="editedItem.video_urls[index]"
                      density="compact"
                      hide-details
                      class="flex-grow-1"
                      variant="outlined"
                      :error-messages="errors[`video_urls.${index}`]"
                    ></v-text-field>
                    <v-btn 
                      icon="mdi-close" 
                      variant="text" 
                      size="small"
                      @click="removeVideoField(index)"
                      v-if="editedItem.video_urls.length > 1 || editedItem.video_urls[index]"
                    ></v-btn>
                  </div>
                  <v-btn variant="text" size="small" prepend-icon="mdi-plus" @click="addVideoField" class="mt-2">
                    Добавить еще одно видео
                  </v-btn>
                </v-col>
              </v-row>
            </v-container>
          </v-card-text>

          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn color="grey" text @click="closeDialog" :disabled="isSaving">Отмена</v-btn>
            <v-btn color="primary" type="submit" :loading="isSaving">Сохранить</v-btn>
          </v-card-actions>

        </v-form>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { useProductStore } from '@/stores/productStore';
import { useCategoryStore } from '@/stores/categoryStore';
import ImageUploadGrid from '@/components/ImageUploadGrid.vue';

const productStore = useProductStore();
const categoryStore = useCategoryStore();
const route = useRoute();

const form = ref(null);
// состояние окна
const dialog = ref(false);
// сохранение
const isSaving = ref(false);
const errors = ref({});

const newImages = ref([]);

const defaultItem = {
  id: null,
  title: '',
  description: '',
  price: 0,
  sku: '',
  quantity: 0,
  image_url: [],
  city: '',
  category_id: null,
  secondary_category_id: null,
  video_urls: [''],
  properties: {},
};
const editedItem = ref(JSON.parse(JSON.stringify(defaultItem)));

const headers = [
  { title: 'Название', key: 'title' },
  { title: 'Категория', key: 'category' },
  { title: 'Артикул', key: 'sku' },
  { title: 'Цена', key: 'price' },
  { title: 'Кол-во', key: 'quantity' },
  { title: 'Действия', key: 'actions', sortable: false, align: 'end' },
];

const rules = {
  required: value => !!value || 'Это поле обязательно.',
  positive: value => value > 0 || 'Значение должно быть больше нуля.',
  integer: value => Number.isInteger(Number(value)) || 'Значение должно быть целым числом.',
};

const formTitle = computed(() => (editedItem.value.id ? 'Редактировать товар' : 'Новый товар'));

const suggestedCategoryList = ref([]);
const allCategoriesList = ref([]);
const isLoadingCategories = ref(false);
const showAllCategoriesSelector = ref(false);

/**
 * Построение иерархии категорий.
 * Улучшено: явная проверка parent_id на null/undefined,
 * и нормализация id/parent_id не проводится здесь (чтобы не переопределять исходные объекты).
 */
const buildHierarchy = (categories) => {
  const categoryMap = {};
  const result = [];

  categories.forEach(c => {
    // Создаем копию, добавляем children
    categoryMap[c.id] = { ...c, children: [] };
  });

  categories.forEach(c => {
    // Если у категории есть родитель и он присутствует в карте — добавляем в children
    if (c.parent_id !== null && c.parent_id !== undefined && categoryMap[c.parent_id]) {
      categoryMap[c.parent_id].children.push(categoryMap[c.id]);
    }
  });

  const flatten = (cats, depth = 0) => {
    cats.forEach(cat => {
      result.push({ ...cat, indentedTitle: '— '.repeat(depth) + cat.title });
      if (cat.children && cat.children.length) flatten(cat.children, depth + 1);
    });
  };

  // Корни — те, у которых parent_id === null или undefined
  const roots = Object.values(categoryMap).filter(c => c.parent_id === null || c.parent_id === undefined);
  flatten(roots);
  return result;
};

// --- НОВЫЕ ХЕЛПЕРЫ: карта всех категорий и вычисление глубины для подсказок ---
const categoryById = computed(() => {
  const map = {};
  allCategoriesList.value.forEach(c => {
    // нормализуем ключи, но не мутируем оригинал
    map[c.id] = {
      id: c.id,
      parent_id: c.parent_id === null || c.parent_id === undefined ? null : c.parent_id,
      title: c.title,
    };
  });
  return map;
});

function getCategoryDepth(cat) {
  let depth = 0;
  let parentId = cat.parent_id === null || cat.parent_id === undefined ? null : cat.parent_id;
  while (parentId !== null && categoryById.value[parentId]) {
    depth++;
    parentId = categoryById.value[parentId].parent_id === null || categoryById.value[parentId].parent_id === undefined
      ? null
      : categoryById.value[parentId].parent_id;
  }
  return depth;
}

// -----------------------------
// Изменения: hierarchicalSuggestedList — если suggested содержит весь список, возвращаем buildHierarchy (правильный порядок сверху вниз).
// Раньше мы всегда мапили suggestedCategoryList (порядок API), из-за чего список "Основная категория" мог идти снизу вверх.
// Конец изменений
const hierarchicalSuggestedList = computed(() => {
  // Если suggested — полный список (т.е. до поиска) — строим иерархический, упорядоченный список
  if (suggestedCategoryList.value.length === allCategoriesList.value.length) {
    return buildHierarchy(suggestedCategoryList.value);
  }

  // Иначе (результат поиска) — добавляем indentedTitle на основе глубины в полном дереве
  return suggestedCategoryList.value.map(cat => {
    const normalized = {
      ...cat,
      parent_id: cat.parent_id === null || cat.parent_id === undefined ? null : cat.parent_id,
      id: cat.id,
    };
    const depth = getCategoryDepth(normalized);
    return {
      ...normalized,
      indentedTitle: '— '.repeat(depth) + (normalized.title || ''),
    };
  });
});
// -----------------------------

// Полный иерархический список (использует buildHierarchy)
const hierarchicalAllList = computed(() => buildHierarchy(allCategoriesList.value));

let debounceTimer;
const updateCategorySuggestions = async (searchTerm) => {
  isLoadingCategories.value = true;
  try {
    const suggestions = await categoryStore.fetchSuggestedCategories(searchTerm);
    suggestedCategoryList.value = suggestions; 

    if (suggestions.length === 1 && searchTerm !== '' && !editedItem.value.id) {
      editedItem.value.category_id = suggestions[0].id;
      loadAttributes(suggestions[0].id);
    }
  } finally {
    isLoadingCategories.value = false;
  }
};

// Улучшенная логика: используем весь ввод (trim), а не только первое слово
const onTitleInput = (title) => {
  if (editedItem.value.id) return; 
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    const trimmed = (title || '').trim();
    if (trimmed.length > 2) {
      updateCategorySuggestions(trimmed);
    } else if (trimmed.length === 0) {
      suggestedCategoryList.value = allCategoriesList.value;
    }
  }, 500);
};

onMounted(async () => {
  productStore.fetchProducts();
  
  const allCategories = await categoryStore.fetchSuggestedCategories('');
  allCategoriesList.value = allCategories;
  suggestedCategoryList.value = allCategories;
  
  if (route.query.action === 'create') {
    openDialog();
  }
});

function openDialog(item) {
  errors.value = {};
  showAllCategoriesSelector.value = false; 
  if (item) {
    editedItem.value = JSON.parse(JSON.stringify({ 
      ...item,
      video_urls: (item.video_urls && item.video_urls.length > 0) ? item.video_urls : [''],
      properties: item.properties || {}, // <-- Сюда загружаются сохраненные 'properties'
    }));
    loadAttributes(item.category_id); // <-- Вызываем 'loadAttributes'
    suggestedCategoryList.value = allCategoriesList.value;
    showAllCategoriesSelector.value = true;
  } else {
    editedItem.value = JSON.parse(JSON.stringify(defaultItem));
    // безопасная очистка selectedCategoryAttributes: если стор реализует метод - используем его
    if (typeof categoryStore.clearSelectedCategoryAttributes === 'function') {
      categoryStore.clearSelectedCategoryAttributes();
    } else {
      categoryStore.selectedCategoryAttributes = [];
    }
    suggestedCategoryList.value = allCategoriesList.value;
  }
  dialog.value = true;
}

function closeDialog() {
  dialog.value = false;
}

// 👇 ИСПРАВЛЕННАЯ ФУНКЦИЯ 'loadAttributes' 👇
function loadAttributes(categoryId) {
  if (!categoryId) {
      if (typeof categoryStore.clearSelectedCategoryAttributes === 'function') {
        categoryStore.clearSelectedCategoryAttributes();
      } else {
        categoryStore.selectedCategoryAttributes = [];
      }
      return;
  };
  // Мы больше НЕ ОЧИЩАЕМ 'properties' здесь
  categoryStore.fetchAttributesForCategory(categoryId);
}

// 👇 НОВАЯ ФУНКЦИЯ 'onCategoryChange' 👇
function onCategoryChange(categoryId) {
  // 1. Очищаем старые свойства
  editedItem.value.properties = {};
  // 2. Загружаем новые атрибуты
  loadAttributes(categoryId);
}


function addVideoField() {
  if (!editedItem.value.video_urls) {
    editedItem.value.video_urls = [];
  }
  editedItem.value.video_urls.push('');
}
function removeVideoField(index) {
  editedItem.value.video_urls.splice(index, 1);
  if (editedItem.value.video_urls.length === 0) {
    addVideoField();
  }
}

async function saveItem() {
  errors.value = {};
  const { valid } = await form.value.validate();
  if (!valid) return;

  isSaving.value = true;

  try {
    if (editedItem.value.id) {
      // При РЕДАКТИРОВАНИИ
      await productStore.updateProduct(editedItem.value, newImages.value);
    } else {
      await productStore.addProduct(editedItem.value, newImages.value);
    }
    newImages.value = [];
    closeDialog();
  } catch (err) {
    if (err.response && err.response.status === 422) {
      errors.value = err.response.data.errors;
    } else {
      errors.value = { general: ['Произошла непредвиденная ошибка.'] };
    }
  } finally {
    isSaving.value = false;
  }
}

async function deleteItem(item) {
  if (confirm(`Вы уверены, что хотите удалить товар "${item.title}"?`)) {
    try {
      await productStore.deleteProduct(item.id);
    } catch (err) {
      alert('Ошибка при удалении товара.');
    }
  }
}

// --- Edit lock helpers (client-side) ---
function isLocked(item) {
  return productStore.isEditLocked(item.id);
}
function editRemainingSeconds(item) {
  return Math.ceil(productStore.lockRemainingMs(item.id) / 1000);
}
function editTitle(item) {
  if (isLocked(item)) {
    return `Редактирование заблокировано (${editRemainingSeconds(item)} с)`;
  }
  return 'Редактировать';
}
function editTooltip(item) {
  if (isLocked(item)) {
    return `Редактирование заблокировано на ${editRemainingSeconds(item)} секунд`;
  }
  return 'Редактировать товар';
}
function onEditClick(item) {
  if (isLocked(item)) {
    // игнорируем клик; можно показать toast если нужно
    return;
  }
  openDialog(item);
}
</script>

<style scoped>

  .edit-icon {                                                   
    transition: color 200ms ease, opacity 200ms ease;             
    -webkit-transition: color 200ms ease, opacity 200ms ease;
  }

  .cards{
    display: flex;
    flex-direction: row;
    justify-content: space-between;
  }

  .card{
    display: flex;
    gap: 2px;
  }

</style>