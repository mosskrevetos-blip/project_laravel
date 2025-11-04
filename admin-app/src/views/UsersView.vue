<template>
  <v-container>
    <div class="d-flex justify-space-between align-center mb-4">
      <h1 class="text-h4">Управление пользователями</h1>
      <v-btn v-if="authStore.hasRole('admin')" color="primary" @click="openDialog()">Добавить пользователя</v-btn>
    </div>

    <v-data-table
      :headers="headers"
      :items="userStore.users"
      :loading="userStore.loading"
      class="elevation-1"
    >
      <template v-slot:item.roles="{ item }">
        <v-chip v-if="item.roles" v-for="role in item.roles" :key="role.id" class="mr-1" size="small">
          {{ role.name }}
        </v-chip>
      </template>

      <template v-slot:item.actions="{ item }">
        <div v-if="authStore.hasRole('admin')">
          <v-icon class="mr-2" color="primary" @click="openDialog(item)">mdi-pencil</v-icon>
          <v-icon v-if="authStore.user?.id !== item.id" color="error" @click="deleteItem(item)">mdi-delete</v-icon>
        </div>
      </template>
    </v-data-table>

    <v-dialog v-model="dialog" max-width="600px" persistent>
      <v-card>
        <v-card-title><span class="text-h5">{{ formTitle }}</span></v-card-title>
        <v-card-text>
          <v-container>
            <v-row>
              <v-col cols="12">
                <v-text-field v-model="editedItem.name" label="Имя" required></v-text-field>
              </v-col>
              <v-col cols="12">
                <v-text-field v-model="editedItem.email" label="Email" type="email" required></v-text-field>
              </v-col>
              <v-col cols="12">
                <v-text-field v-model="editedItem.password" label="Пароль" type="password" :placeholder="editedItem.id ? 'Оставьте пустым, чтобы не менять' : ''"></v-text-field>
              </v-col>
              <v-col cols="12">
                <v-select
                  v-model="editedItem.roles"
                  :items="roleStore.roles"
                  item-title="name"
                  item-value="id"
                  label="Роли"
                  multiple
                  chips
                ></v-select>
              </v-col>
            </v-row>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="grey" text @click="closeDialog">Отмена</v-btn>
          <v-btn color="primary" @click="saveItem">Сохранить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useUserStore } from '@/stores/userStore';
import { useAuthStore } from '@/stores/authStore';
import { useRoleStore } from '@/stores/roleStore';

const userStore = useUserStore();
const authStore = useAuthStore();
const roleStore = useRoleStore();

const dialog = ref(false);
const editedItem = ref({
  id: null,
  name: '',
  email: '',
  password: '',
  roles: [], // Будем хранить массив ID ролей
});
const defaultItem = { ...editedItem.value };

const headers = [
  { title: 'Имя', key: 'name' },
  { title: 'Email', key: 'email' },
  { title: 'Роли', key: 'roles', sortable: false },
  { title: 'Действия', key: 'actions', sortable: false, align: 'end' },
];

const formTitle = computed(() => (editedItem.value.id ? 'Редактировать пользователя' : 'Новый пользователь'));

onMounted(() => {
  // Загружаем и пользователей, и роли при монтировании
  userStore.fetchUsers();
  roleStore.fetchRoles();
});

function openDialog(item) {
  if (item) {
    editedItem.value = { 
      ...item,
      // Преобразуем массив объектов ролей в массив их ID для v-select
      roles: item.roles.map(role => role.id)
    };
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
    const dataToSend = { ...editedItem.value };
    // Не отправляем пустой пароль при редактировании
    if (dataToSend.id && !dataToSend.password) {
      delete dataToSend.password;
    }

    if (dataToSend.id) {
      await userStore.updateUser(dataToSend);
    } else {
      await userStore.addUser(dataToSend);
    }
    closeDialog();
  } catch (error) {
    alert('Произошла ошибка!');
    console.error(error);
  }
}

async function deleteItem(item) {
  if (confirm(`Вы уверены, что хотите удалить пользователя "${item.name}"?`)) {
    try {
      await userStore.deleteUser(item.id);
    } catch (error) {
      alert('Ошибка при удалении пользователя.');
    }
  }
}
</script>