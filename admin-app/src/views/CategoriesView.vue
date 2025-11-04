<template>

  <div v-if="authStore.hasRole('admin') || authStore.hasRole('manager')">
    <v-container>
      <div class="d-flex justify-space-between align-center mb-4">
        <h1 class="text-h4">Управління категоріями</h1>
        <v-btn color="primary" @click="openDialog()">Додати категорію</v-btn>
      </div>

      <v-data-table
        :headers="headers"
        :items="hierarchicalCategories" :loading="categoryStore.loading"
        class="elevation-1"
        item-value="id"
      >
        <template v-slot:item.title="{ item }">
          <div :style="{ paddingLeft: `${item.depth * 24}px` }">
            <span v-if="item.depth > 0">— </span>
            {{ item.title }}
          </div>
        </template>

        <template v-slot:item.actions="{ item }">
          <v-icon class="mr-2" color="primary" @click="openDialog(item)">mdi-pencil</v-icon>
          <v-icon color="error" @click="deleteItem(item)">mdi-delete</v-icon>
        </template>
      </v-data-table>

      <v-dialog v-model="dialog" max-width="600px" persistent>
        <v-card>
          <v-card-title>
            <span class="text-h5">{{ formTitle }}</span>
          </v-card-title>
          <v-card-text>
            <v-container>
              <v-row>
                <v-col cols="12">
                  <v-select
                    label="Батьківська категорія"
                    v-model="editedItem.parent_id"
                    :items="availableParents"
                    item-title="title"
                    item-value="id"
                    clearable
                  ></v-select>
                </v-col>
              
                <v-col cols="12">
                  <v-text-field v-model="editedItem.title" label="Назва"></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-textarea v-model="editedItem.description" label="Опис"></v-textarea>
                </v-col>
                <v-col cols="12">
                  <v-text-field v-model="editedItem.image_url" label="URL зображення"></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-text-field v-model="editedItem.icon_url" label="URL іконки"></v-text-field>
                </v-col>
              </v-row>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn color="grey" text @click="closeDialog">Скасування</v-btn>
            <v-btn color="primary" @click="saveItem">Зберігти</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </div>

  <div v-else>
    <v-container>
      <v-alert type="error" title="Доступ заборонено" text="Ви не маєте прав для перегляду цього розділу."></v-alert>
    </v-container>
  </div>

</template>




<script setup>
import { ref, onMounted, computed } from 'vue';
import { useCategoryStore } from '@/stores/categoryStore';
import { useAuthStore } from '@/stores/authStore';

const authStore = useAuthStore();
const categoryStore = useCategoryStore();

// --- Состояние компонента (без изменений) ---
const dialog = ref(false);
const editedItem = ref({
  id: null,
  title: '',
  description: '',
  image_url: '',
  icon_url: '',
  parent_id: null,
});
const defaultItem = { ...editedItem.value };

// --- Конфигурация таблицы (без изменений) ---
const headers = [
  { title: 'Название', key: 'title' },
  // автоматично підтягую данні з ключа products_count, який був порахований в методі index в файлі backend/app/Http/Controllers/Api/CategoryController.php
  { title: 'Кол-во товаров', key: 'products_count', align: 'end' },
  { title: 'Действия', key: 'actions', sortable: false, align: 'end' },
];

// --- Вычисляемые свойства ---
const formTitle = computed(() => (editedItem.value.id ? 'Редактировать категорию' : 'Новая категория'));

const availableParents = computed(() => {
  if (!editedItem.value.id) {
    return categoryStore.categories;
  }
  return categoryStore.categories.filter(c => c.id !== editedItem.value.id);
});


// 3. НОВАЯ ЛОГИКА ДЛЯ ПОСТРОЕНИЯ ИЕРАРХИИ
const hierarchicalCategories = computed(() => {
  const categories = categoryStore.categories;
  const categoryMap = {};
  const result = [];

  // Сначала создаем карту для быстрого доступа
  categories.forEach(category => {
    categoryMap[category.id] = { ...category, children: [] };
  });

  // Затем строим дерево
  categories.forEach(category => {
    if (category.parent_id) {
      if (categoryMap[category.parent_id]) {
        categoryMap[category.parent_id].children.push(categoryMap[category.id]);
      }
    }
  });

  // Функция для "выпрямления" дерева в плоский список с отступами
  const flatten = (categories, depth = 0) => {
    categories.forEach(category => {
      result.push({ ...category, depth });
      if (category.children.length) {
        flatten(category.children, depth + 1);
      }
    });
  };

  // Начинаем с корневых элементов
  const rootCategories = Object.values(categoryMap).filter(c => !c.parent_id);
  flatten(rootCategories);

  return result;
});


// --- Жизненный цикл ---
onMounted(() => {
  categoryStore.fetchCategories();
});

// --- Методы ---
function openDialog(item) {
  if (item) {
    editedItem.value = { ...item };
  } else {
    editedItem.value = { ...defaultItem };
  }
  dialog.value = true;
}

function closeDialog() {
  dialog.value = false;
}

async function saveItem() {
  try {
    if (editedItem.value.id) {
      await categoryStore.updateCategory(editedItem.value);
    } else {
      await categoryStore.addCategory(editedItem.value);
    }
    closeDialog();
  } catch (error) {
    alert('Произошла ошибка!');
    console.error(error);
  }
}

async function deleteItem(item) {
  if (confirm(`Вы уверены, что хотите удалить категорию "${item.title}"?`)) {
    try {
      await categoryStore.deleteCategory(item.id);
    } catch (error) {
      alert('Ошибка при удалении категории.');
    }
  }
}
</script>