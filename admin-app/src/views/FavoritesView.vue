<template>
  <v-container>
    <div class="d-flex justify-space-between align-center mb-4">
      <h1 class="text-h4">Обране</h1>
    </div>

    <v-alert v-if="favoriteReportStore.error" type="error" class="mb-4">
      {{ favoriteReportStore.error }}
    </v-alert>

    <v-progress-linear
      v-if="favoriteReportStore.loading"
      indeterminate
      color="primary"
      class="mb-4"
    />

    <!-- Блок: обрані товари -->
    <v-card class="mb-6" elevation="2">
      <v-card-title>Обрані товари</v-card-title>
      <v-card-text>
        <div
          v-for="userBlock in favoriteReportStore.favoriteProductsByUser"
          :key="`products-${userBlock.id}`"
          class="mb-6"
        >
          <div class="mb-2">
            <strong>{{ userBlock.name }}</strong>
            <span class="text-medium-emphasis"> ({{ userBlock.email }})</span>
          </div>

          <div v-if="userBlock.roles?.length" class="mb-2">
            <v-chip
              v-for="role in userBlock.roles"
              :key="`products-role-${userBlock.id}-${role.id}`"
              class="mr-1 mb-1"
              size="small"
            >
              {{ role.name }}
            </v-chip>
          </div>

          <v-table density="comfortable" class="mb-2">
            <thead>
              <tr>
                <th>ID</th>
                <th>Назва</th>
                <th>Категорія</th>
                <th>Продавець</th>
                <th>Ціна</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!userBlock.favorite_products || userBlock.favorite_products.length === 0">
                <td colspan="5" class="text-medium-emphasis">Немає обраних товарів</td>
              </tr>
              <tr
                v-for="product in userBlock.favorite_products"
                :key="`favorite-product-${userBlock.id}-${product.id}`"
              >
                <td>{{ product.id }}</td>
                <td>{{ product.title }}</td>
                <td>{{ product.category?.title || '—' }}</td>
                <td>{{ product.user?.name || '—' }}</td>
                <td>{{ product.price }}</td>
              </tr>
            </tbody>
          </v-table>

          <v-divider />
        </div>
      </v-card-text>
    </v-card>

    <!-- Блок: обрані продавці -->
    <v-card class="mb-6" elevation="2">
      <v-card-title>Обрані продавці</v-card-title>
      <v-card-text>
        <div
          v-for="userBlock in favoriteReportStore.favoriteSellersByUser"
          :key="`sellers-${userBlock.id}`"
          class="mb-6"
        >
          <div class="mb-2">
            <strong>{{ userBlock.name }}</strong>
            <span class="text-medium-emphasis"> ({{ userBlock.email }})</span>
          </div>

          <div v-if="userBlock.roles?.length" class="mb-2">
            <v-chip
              v-for="role in userBlock.roles"
              :key="`sellers-role-${userBlock.id}-${role.id}`"
              class="mr-1 mb-1"
              size="small"
            >
              {{ role.name }}
            </v-chip>
          </div>

          <v-table density="comfortable" class="mb-2">
            <thead>
              <tr>
                <th>ID</th>
                <th>Ім’я</th>
                <th>Email</th>
                <th>Ролі</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!userBlock.favorite_sellers || userBlock.favorite_sellers.length === 0">
                <td colspan="4" class="text-medium-emphasis">Немає обраних продавців</td>
              </tr>
              <tr
                v-for="seller in userBlock.favorite_sellers"
                :key="`favorite-seller-${userBlock.id}-${seller.id}`"
              >
                <td>{{ seller.id }}</td>
                <td>{{ seller.name }}</td>
                <td>{{ seller.email }}</td>
                <td>
                  <v-chip
                    v-for="role in seller.roles || []"
                    :key="`seller-role-${seller.id}-${role.id}`"
                    class="mr-1 mb-1"
                    size="x-small"
                  >
                    {{ role.name }}
                  </v-chip>
                </td>
              </tr>
            </tbody>
          </v-table>

          <v-divider />
        </div>
      </v-card-text>
    </v-card>

    <!-- Блок тільки для seller / wholesale_seller / manufacturer -->
    <v-card
      v-if="favoriteReportStore.mode === 'seller'"
      class="mb-6"
      elevation="2"
    >
      <v-card-title>Користувачі, які додали мої товари в обране</v-card-title>
      <v-card-text>
        <div
          v-for="item in favoriteReportStore.favoritedMyProducts"
          :key="`my-product-favorites-${item.product.id}`"
          class="mb-6"
        >
          <div class="mb-2">
            <strong>{{ item.product.title }}</strong>
            <span class="text-medium-emphasis"> (ID: {{ item.product.id }})</span>
          </div>

          <v-table density="comfortable" class="mb-2">
            <thead>
              <tr>
                <th>ID</th>
                <th>Ім’я</th>
                <th>Email</th>
                <th>Ролі</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!item.users || item.users.length === 0">
                <td colspan="4" class="text-medium-emphasis">Ніхто ще не додав цей товар в обране</td>
              </tr>
              <tr
                v-for="u in item.users"
                :key="`product-favorited-user-${item.product.id}-${u.id}`"
              >
                <td>{{ u.id }}</td>
                <td>{{ u.name }}</td>
                <td>{{ u.email }}</td>
                <td>
                  <v-chip
                    v-for="role in u.roles || []"
                    :key="`favorited-user-role-${u.id}-${role.id}`"
                    class="mr-1 mb-1"
                    size="x-small"
                  >
                    {{ role.name }}
                  </v-chip>
                </td>
              </tr>
            </tbody>
          </v-table>

          <v-divider />
        </div>
      </v-card-text>
    </v-card>

    <v-card
      v-if="favoriteReportStore.mode === 'seller'"
      elevation="2"
    >
      <v-card-title>Користувачі, які додали мене в обране як продавця</v-card-title>
      <v-card-text>
        <v-table density="comfortable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Ім’я</th>
              <th>Email</th>
              <th>Ролі</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="favoriteReportStore.usersWhoFavoritedMeAsSeller.length === 0">
              <td colspan="4" class="text-medium-emphasis">Ніхто ще не додав вас в обране як продавця</td>
            </tr>
            <tr
              v-for="u in favoriteReportStore.usersWhoFavoritedMeAsSeller"
              :key="`favorited-me-as-seller-${u.id}`"
            >
              <td>{{ u.id }}</td>
              <td>{{ u.name }}</td>
              <td>{{ u.email }}</td>
              <td>
                <v-chip
                  v-for="role in u.roles || []"
                  :key="`favorited-me-role-${u.id}-${role.id}`"
                  class="mr-1 mb-1"
                  size="x-small"
                >
                  {{ role.name }}
                </v-chip>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup>
import { onMounted } from 'vue';
import { useFavoriteReportStore } from '@/stores/favoriteReportStore';

const favoriteReportStore = useFavoriteReportStore();

onMounted(() => {
  favoriteReportStore.fetchReport();
});
</script>