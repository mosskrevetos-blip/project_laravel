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

    <v-tabs v-model="tab" class="mb-4">
      <!-- Admin / Manager -->
      <template v-if="isAdminOrManager">
        <v-tab value="favorite-products-by-users">
          Обрані товари користувачами
        </v-tab>
        <v-tab value="favorite-sellers-by-users">
          Обрані продавці користувачами
        </v-tab>
      </template>

      <!-- Seller / Wholesale seller / Manufacturer -->
      <template v-else-if="isSellerLike">
        <v-tab value="favorite-products">
          Обрані товари
        </v-tab>
        <v-tab value="favorite-sellers">
          Обрані продавці
        </v-tab>
        <v-tab value="favorited-my-products">
          Ваші товари, обрані іншими
        </v-tab>
        <v-tab value="favorited-me-as-seller">
          Ви, обрані іншими
        </v-tab>
      </template>

      <!-- Regular user -->
      <template v-else>
        <v-tab value="favorite-products">
          Обрані товари
        </v-tab>
        <v-tab value="favorite-sellers">
          Обрані продавці
        </v-tab>
      </template>
    </v-tabs>

    <v-window v-model="tab">
      <!-- ========================================================= -->
      <!-- Admin / Manager: favorite products by users -->
      <!-- ========================================================= -->
      <v-window-item value="favorite-products-by-users">
        <v-card elevation="2">
          <v-card-title>Обрані товари користувачами</v-card-title>
          <v-card-text>
            <div
              v-for="userBlock in favoriteProductsByUserSorted"
              :key="`products-${userBlock.id}`"
              class="mb-6"
            >
              <div class="mb-2">
                <strong>{{ userBlock.name }}</strong>
                <span class="text-medium-emphasis"> ({{ userBlock.email }})</span>
              </div>

              <div v-if="canSeeUserRoles() && userBlock.roles?.length" class="mb-2">
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
                    <th>Фото</th>
                    <th>ID</th>
                    <th>Назва</th>
                    <th>Категорія</th>
                    <th>Продавець</th>
                    <th>Ціна</th>
                    <th>Дія</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!userBlock.favorite_products || userBlock.favorite_products.length === 0">
                    <td colspan="7" class="text-medium-emphasis">Немає обраних товарів</td>
                    </tr>
                    <tr
                    v-for="product in userBlock.favorite_products"
                    :key="`favorite-product-${userBlock.id}-${product.id}`"
                    >
                    <td>
                        <v-img
                        v-if="product.image_url?.[0]"
                        :src="getProductThumbnailUrl(product)"
                        width="56"
                        height="56"
                        cover
                        class="rounded"
                        />
                        <div
                        v-else
                        class="d-flex align-center justify-center rounded bg-grey-lighten-3 text-medium-emphasis"
                        style="width: 56px; height: 56px;"
                        >
                        —
                        </div>
                    </td>
                    <td>{{ product.id }}</td>
                    <td>
                        <a
                        :href="getProductPublicUrl(product)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-decoration-none"
                        >
                        {{ product.title }}
                        </a>
                    </td>
                    <td>{{ product.category?.title || '—' }}</td>
                    <td>{{ product.user?.name || '—' }}</td>
                    <td>{{ product.price }}</td>
                    <td>
                        <v-btn
                        icon
                        variant="text"
                        color="error"
                        @click="openDeleteDialog(userBlock.id, product.id)"
                        >
                        <v-icon>mdi-delete</v-icon>
                        </v-btn>
                    </td>
                    </tr>
                </tbody>
              </v-table>

              <v-divider />
            </div>
          </v-card-text>
        </v-card>
      </v-window-item>

      <!-- ========================================================= -->
      <!-- Admin / Manager: favorite sellers by users -->
      <!-- ========================================================= -->
      <v-window-item value="favorite-sellers-by-users">
        <v-card elevation="2">
          <v-card-title>Обрані продавці користувачами</v-card-title>
          <v-card-text>
            <div
              v-for="userBlock in favoriteSellersByUserSorted"
              :key="`sellers-${userBlock.id}`"
              class="mb-6"
            >
              <div class="mb-2">
                <strong>{{ userBlock.name }}</strong>
                <span class="text-medium-emphasis"> ({{ userBlock.email }})</span>
              </div>

              <div v-if="canSeeUserRoles() && userBlock.roles?.length" class="mb-2">
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
      </v-window-item>

      <!-- ========================================================= -->
      <!-- Regular user / seller-like: own favorite products -->
      <!-- ========================================================= -->
      <v-window-item value="favorite-products">
        <v-card elevation="2">
            <v-card-title>Обрані товари</v-card-title>
            <v-card-text>
            <div
                v-for="userBlock in favoriteReportStore.favoriteProductsByUser"
                :key="`my-products-${userBlock.id}`"
                class="mb-6"
            >

                <v-table density="comfortable" class="mb-2">
                <thead>
                    <tr>
                    <th>Фото</th>
                    <th>Назва</th>
                    <th>Категорія</th>
                    <th>Продавець</th>
                    <th>Ціна</th>
                    <th>Дія</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!userBlock.favorite_products || userBlock.favorite_products.length === 0">
                        <td colspan="6" class="text-medium-emphasis">Немає обраних товарів</td>
                    </tr>
                    <tr
                        v-for="product in userBlock.favorite_products"
                        :key="`my-favorite-product-${userBlock.id}-${product.id}`"
                        >
                        <td>
                            <v-img
                                v-if="product.image_url?.[0]"
                                :src="getProductThumbnailUrl(product)"
                                width="56"
                                height="56"
                                cover
                                class="rounded"
                            />
                            <div
                                v-else
                                class="d-flex align-center justify-center rounded bg-grey-lighten-3 text-medium-emphasis"
                                style="width: 56px; height: 56px;"
                                >
                                —
                            </div>
                        </td>
                        <td>
                            <a
                                :href="getProductPublicUrl(product)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-decoration-none"
                            >
                                {{ product.title }}
                            </a>
                        </td>
                        <td>{{ product.category?.title || '—' }}</td>
                        <td>{{ product.user?.name || '—' }}</td>
                        <td>{{ product.price }}</td>
                        <td>
                            <v-btn
                                icon
                                variant="text"
                                color="error"
                                @click="openDeleteDialog(userBlock.id, product.id)"
                            >
                                <v-icon>mdi-delete</v-icon>
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
                </v-table>
            </div>
            </v-card-text>
        </v-card>
      </v-window-item>

      <!-- ========================================================= -->
      <!-- Regular user / seller-like: own favorite sellers -->
      <!-- ========================================================= -->
      <v-window-item value="favorite-sellers">
        <v-card elevation="2">
          <v-card-title>Обрані продавці</v-card-title>
          <v-card-text>
            <div
              v-for="userBlock in favoriteReportStore.favoriteSellersByUser"
              :key="`my-sellers-${userBlock.id}`"
              class="mb-6"
            >

              <v-table density="comfortable" class="mb-2">
                <thead>
                    <tr>
                        <th>Ім’я</th>
                        <th>Дія</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!userBlock.favorite_sellers || userBlock.favorite_sellers.length === 0">
                        <td colspan="2" class="text-medium-emphasis">Немає обраних продавців</td>
                    </tr>
                    <tr
                        v-for="seller in userBlock.favorite_sellers"
                        :key="`favorite-seller-${userBlock.id}-${seller.id}`"
                    >
                        <td>{{ seller.name }}</td>
                        <td>
                        <v-btn
                            icon
                            variant="text"
                            color="error"
                            @click="openDeleteSellerDialog(userBlock.id, seller.id)"
                        >
                            <v-icon>mdi-delete</v-icon>
                        </v-btn>
                        </td>
                    </tr>
                </tbody>
              </v-table>
            </div>
          </v-card-text>
        </v-card>
      </v-window-item>

      <!-- ========================================================= -->
      <!-- Seller-like: my products favorited by others -->
      <!-- ========================================================= -->
      <v-window-item value="favorited-my-products">
        <v-card elevation="2">
            <v-card-title>Ваші товари, обрані іншими</v-card-title>
            <v-card-text>
            <div
                v-for="item in favoriteReportStore.favoritedMyProducts.filter(item => item.users && item.users.length > 0)"
                :key="`my-product-favorites-${item.product.id}`"
                class="mb-6"
            >
                <div class="mb-2">
                <a
                    :href="getProductPublicUrl(item.product)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-decoration-none font-weight-bold"
                >
                    {{ item.product.title }}
                </a>
                </div>

                <v-table density="comfortable" class="mb-2">
                <thead>
                    <tr>
                    <th>Ім’я</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                    v-for="u in item.users"
                    :key="`product-favorited-user-${item.product.id}-${u.id}`"
                    >
                    <td>{{ u.name }}</td>
                    </tr>
                </tbody>
                </v-table>

                <v-divider />
            </div>

            <div
                v-if="favoriteReportStore.favoritedMyProducts.filter(item => item.users && item.users.length > 0).length === 0"
                class="text-medium-emphasis"
            >
                Ніхто ще не додав ваші товари в обране
            </div>
            </v-card-text>
        </v-card>
      </v-window-item>

      <!-- ========================================================= -->
      <!-- Seller-like: users who favorited me as seller -->
      <!-- ========================================================= -->
      <v-window-item value="favorited-me-as-seller">
        <v-card elevation="2">
            <v-card-title>Ви, обрані іншими</v-card-title>
            <v-card-text>
            <v-table density="comfortable">
                <thead>
                <tr>
                    <th>Ім’я</th>
                </tr>
                </thead>
                <tbody>
                <tr v-if="favoriteReportStore.usersWhoFavoritedMeAsSeller.length === 0">
                    <td class="text-medium-emphasis">Ніхто ще не додав вас в обране як продавця</td>
                </tr>
                <tr
                    v-for="u in favoriteReportStore.usersWhoFavoritedMeAsSeller"
                    :key="`favorited-me-as-seller-${u.id}`"
                >
                    <td>{{ u.name }}</td>
                </tr>
                </tbody>
            </v-table>
            </v-card-text>
        </v-card>
      </v-window-item>
    </v-window>

    <v-dialog v-model="deleteDialog" max-width="420">
        <v-card>
            <v-card-title class="text-h6">
                Підтвердження
            </v-card-title>
            <v-card-text>
                Ви дійсно бажаєте видалити цей товар з обраного?
            </v-card-text>
            <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="closeDeleteDialog">
                Скасувати
            </v-btn>
            <v-btn color="error" @click="confirmDeleteFavoriteProduct">
                Видалити
            </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="deleteSellerDialog" max-width="420">
        <v-card>
            <v-card-title class="text-h6">
            Підтвердження
            </v-card-title>
            <v-card-text>
            Ви дійсно бажаєте видалити цього продавця з обраного?
            </v-card-text>
            <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="closeDeleteSellerDialog">
                Скасувати
            </v-btn>
            <v-btn color="error" @click="confirmDeleteFavoriteSeller">
                Видалити
            </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useFavoriteReportStore } from '@/stores/favoriteReportStore';
import { useAuthStore } from '@/stores/authStore';

const favoriteReportStore = useFavoriteReportStore();
const authStore = useAuthStore();

const tab = ref(null);

const isAdminOrManager = computed(() => {
  return authStore.hasRole('admin') || authStore.hasRole('manager');
});

const isSellerLike = computed(() => {
  return (
    authStore.hasRole('seller') ||
    authStore.hasRole('wholesale_seller') ||
    authStore.hasRole('manufacturer')
  );
});

const canSeeUserRoles = () => {
  return authStore.hasRole('admin') || authStore.hasRole('manager');
};

onMounted(async () => {
  await favoriteReportStore.fetchReport();

  if (isAdminOrManager.value) {
    tab.value = 'favorite-products-by-users';
  } else {
    tab.value = 'favorite-products';
  }
});


//Посилання на товар
const publicSiteUrl = import.meta.env.VITE_PUBLIC_SITE_URL;

function getProductPublicUrl(product) {
  if (!product?.id || !product?.slug) {
    return '#';
  }

  const base = (publicSiteUrl || '').replace(/\/$/, '');
  return `${base}/product/${product.id}-${product.slug}`;
}


// url зображення
function getProductThumbnailUrl(product) {
  const filename = product?.image_url?.[0];
  const productId = product?.id;

  if (!filename || !productId) {
    return '';
  }

  if (filename.startsWith('http://') || filename.startsWith('https://')) {
    return filename;
  }

  const baseUrl = (import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_PUBLIC_SITE_URL || window.location.origin).replace(/\/$/, '');

  const dot = filename.lastIndexOf('.');
  const base = dot === -1 ? filename : filename.slice(0, dot);
  const ext = dot === -1 ? '' : filename.slice(dot);

  return `${baseUrl}/storage/products/${productId}/${base}_150${ext}`;
}

/* Видалення товару з обраного */
const deleteDialog = ref(false);
const productToDelete = ref({
  userId: null,
  productId: null,
});

function openDeleteDialog(userId, productId) {
  productToDelete.value = { userId, productId };
  deleteDialog.value = true;
}

function closeDeleteDialog() {
  deleteDialog.value = false;
  productToDelete.value = { userId: null, productId: null };
}

async function confirmDeleteFavoriteProduct() {
  try {
    if (isAdminOrManager.value) {
      await favoriteReportStore.removeFavoriteProductForUser(
        productToDelete.value.userId,
        productToDelete.value.productId
      );
    } else {
      await favoriteReportStore.removeFavoriteProduct(
        productToDelete.value.productId
      );
    }

    closeDeleteDialog();
  } catch (error) {
    console.error('Помилка при видаленні товару з обраного:', error);
  }
}

/* Видалення продавця з обраного */
const deleteSellerDialog = ref(false);
const sellerToDelete = ref({
  userId: null,
  sellerId: null,
});

function openDeleteSellerDialog(userId, sellerId) {
  sellerToDelete.value = { userId, sellerId };
  deleteSellerDialog.value = true;
}

function closeDeleteSellerDialog() {
  deleteSellerDialog.value = false;
  sellerToDelete.value = { userId: null, sellerId: null };
}

async function confirmDeleteFavoriteSeller() {
  try {
    if (isAdminOrManager.value) {
      await favoriteReportStore.removeFavoriteSellerForUser(
        sellerToDelete.value.userId,
        sellerToDelete.value.sellerId
      );
    } else {
      await favoriteReportStore.removeFavoriteSeller(
        sellerToDelete.value.sellerId
      );
    }

    closeDeleteSellerDialog();
  } catch (error) {
    console.error('Помилка при видаленні продавця з обраного:', error);
  }
}

// Сортування користувачыв за кількістю обраних товарів/продавців для адміна та менеджера
const favoriteProductsByUserSorted = computed(() => {
  const users = [...favoriteReportStore.favoriteProductsByUser];

  return users.sort((a, b) => {
    const aCount = a.favorite_products?.length || 0;
    const bCount = b.favorite_products?.length || 0;

    // Сначала пользователи, у которых есть товары
    if (aCount > 0 && bCount === 0) return -1;
    if (aCount === 0 && bCount > 0) return 1;

    return 0;
  });
});

// Сортування користувачыв за кількістю обраних продавців для адміна та менеджера
const favoriteSellersByUserSorted = computed(() => {
  const users = [...favoriteReportStore.favoriteSellersByUser];

  return users.sort((a, b) => {
    const aCount = a.favorite_sellers?.length || 0;
    const bCount = b.favorite_sellers?.length || 0;

    // Сначала пользователи, у которых есть избранные продавці
    if (aCount > 0 && bCount === 0) return -1;
    if (aCount === 0 && bCount > 0) return 1;

    return 0;
  });
});

</script>