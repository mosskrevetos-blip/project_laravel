<template>
  <v-container>
    <div class="d-flex justify-space-between align-center mb-4">
      <h1 class="text-h4">Управление атрибутами</h1>
      <!-- Кнопка "Добавить" видна только админу и менеджеру -->
      <v-btn v-if="authStore.hasRole('admin') || authStore.hasRole('manager')" color="primary" @click="openDialog()">Добавить атрибут</v-btn>
    </div>

    <!-- Таблица атрибутов -->
    <v-data-table
      :headers="headers"
      :items="attributeStore.attributes"
      :loading="attributeStore.loading"
      class="elevation-1"
    >
      <template v-slot:item.categories="{ item }">
        <v-chip v-for="cat in item.categories" :key="cat.id" small class="mr-1">{{ cat.title }}</v-chip>
      </template>
      <template v-slot:item.actions="{ item }">
        <!-- 👇 ИЗМЕНЕНИЕ ЗДЕСЬ: Добавляем проверку на 'manager' 👇 -->
        <div v-if="authStore.hasRole('admin') || authStore.hasRole('manager')">
          <v-icon class="mr-2" color="primary" @click="openDialog(item)">mdi-pencil</v-icon>
          <v-icon color="error" @click="deleteItem(item)">mdi-delete</v-icon>
        </div>
      </template>
    </v-data-table>

    <!-- Диалоговое окно -->
    <v-dialog v-model="dialog" max-width="700px" persistent>
      <v-card>
        <v-form ref="form" @submit.prevent="saveItem">
          <v-card-title><span class="text-h5">{{ formTitle }}</span></v-card-title>
          <v-card-text>
            <v-container>
              <v-row>
                <v-col cols="12" md="6">
                  <v-text-field v-model="editedItem.name" label="Название (напр. 'Цвет')" :rules="[rules.required]"></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field v-model="editedItem.slug" label="Slug (напр. 'color')" :rules="[rules.required]"></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-select
                    v-model="editedItem.type"
                    :items="attributeTypes"
                    label="Тип атрибута"
                    :rules="[rules.required]"
                  ></v-select>
                </v-col>
                <v-col cols="12">
                  <v-select
                    v-model="editedItem.categories"
                    :items="categoryStore.categories"
                    item-title="title"
                    item-value="id"
                    label="Привязать к категориям"
                    multiple
                    chips
                    :rules="[rules.requiredArray]"
                  ></v-select>
                </v-col>

                <!-- Динамические поля для опций, если тип 'select' -->
                <v-col cols="12" v-if="editedItem.type === 'select'">
                  <v-divider class="my-4"></v-divider>
                  <h3 class="text-subtitle-1 mb-2">Опции для списка</h3>
                  <div v-for="(option, index) in editedItem.options" :key="index" class="d-flex align-center mb-2">
                    <v-text-field
                      v-model="option.value"
                      :label="`Опция #${index + 1}`"
                      variant="outlined"
                      density="compact"
                      hide-details
                      class="flex-grow-1"
                    ></v-text-field>
                    <v-btn icon="mdi-close" variant="text" size="small" @click="removeOption(index)"></v-btn>
                  </div>
                  <v-btn variant="text" size="small" prepend-icon="mdi-plus" @click="addOption">
                    Добавить опцию
                  </v-btn>
                </v-col>
                
              </v-row>
              <v-alert v-if="error" type="error" dense class="mt-4">{{ error }}</v-alert>
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
import { ref, onMounted, computed } from 'vue';
import { useAttributeStore } from '@/stores/attributeStore';
import { useAuthStore } from '@/stores/authStore';
import { useCategoryStore } from '@/stores/categoryStore';

const attributeStore = useAttributeStore();
const authStore = useAuthStore();
const categoryStore = useCategoryStore();

const form = ref(null);
const dialog = ref(false);
const isSaving = ref(false);
const error = ref(null);

const attributeTypes = ['text', 'number', 'boolean', 'select'];

const editedItem = ref({
  id: null,
  name: '',
  slug: '',
  type: 'text',
  categories: [],
  options: [],
});
const defaultItem = JSON.parse(JSON.stringify(editedItem.value)); 

const headers = [
  { title: 'Название', key: 'name' },
  { title: 'Slug', key: 'slug' },
  { title: 'Тип', key: 'type' },
  { title: 'Категории', key: 'categories', sortable: false },
  { title: 'Действия', key: 'actions', sortable: false, align: 'end' },
];

const rules = {
  required: v => !!v || 'Поле обязательно',
  requiredArray: v => (v && v.length > 0) || 'Выберите хотя бы один элемент',
};

const formTitle = computed(() => (editedItem.value.id ? 'Редактировать атрибут' : 'Новый атрибут'));

onMounted(() => {
  attributeStore.fetchAttributes();
  categoryStore.fetchCategories();
});

function openDialog(item) {
  error.value = null;
  if (item) {
    editedItem.value = JSON.parse(JSON.stringify({ 
      ...item,
      categories: item.categories.map(cat => cat.id),
      options: item.options.length ? item.options : [{ value: '' }],
    }));
  } else {
    editedItem.value = JSON.parse(JSON.stringify(defaultItem));
    addOption();
  }
  dialog.value = true;
}

function closeDialog() {
  dialog.value = false;
}

function addOption() {
  editedItem.value.options.push({ value: '' });
}

function removeOption(index) {
  editedItem.value.options.splice(index, 1);
  if (editedItem.value.options.length === 0) {
    addOption();
  }
}

async function saveItem() {
  error.value = null;
  const { valid } = await form.value.validate();
  if (!valid) return;

  isSaving.value = true;
  
  const dataToSend = JSON.parse(JSON.stringify(editedItem.value));
  
  if (dataToSend.type !== 'select') {
    dataToSend.options = [];
  } else {
    dataToSend.options = dataToSend.options.filter(opt => opt.value && opt.value.trim() !== '');
  }

  try {
    if (dataToSend.id) {
      await attributeStore.updateAttribute(dataToSend);
    } else {
      await attributeStore.addAttribute(dataToSend);
    }
    closeDialog();
  } catch (err) {
    if (err.response && err.response.status === 422) {
      error.value = Object.values(err.response.data.errors).flat().join('; ');
    } else {
      error.value = 'Произошла неизвестная ошибка.';
    }
  } finally {
    isSaving.value = false;
  }
}

async function deleteItem(item) {
  if (confirm(`Вы уверены, что хотите удалить атрибут "${item.name}"?`)) {
    try {
      await attributeStore.deleteAttribute(item.id);
    } catch (err) {
      alert('Ошибка при удалении атрибута.');
    }
  }
}
</script>
