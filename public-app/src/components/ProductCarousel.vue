<template>
  <div class="my-8">
    <h2 class="text-h4 font-weight-bold mb-4">{{ title }}</h2>
    <v-sheet class="mx-auto">
      <v-slide-group class="pa-2" show-arrows>
        <v-slide-group-item v-for="product in products" :key="product.id">
          <!-- CHANGED: обернул карточку в <router-link>, чтобы сделать кликабельную ссылку на страницу товара -->
          <router-link
            :to="{ name: 'product.show', params: { id: product.id } }"
            class="no-decoration"
          >
            <v-card class="ma-4" height="350" width="250">
              <!-- Используем адаптивную компоненту -->
              <div style="height:200px; width:100%;">
                <ProductResponsiveImagePublic
                  v-if="hasImage(product)"
                  :product-id="product.id"
                  :filename="firstImageName(product)"
                  :alt-text="product.title"
                  :sizes-List="[150,400,800,1200,2000]"
                  :sizes-attr="'(max-width: 480px) 150px, (max-width: 800px) 400px, (max-width: 1200px) 800px, 1200px'"
                  :style-object="{ height: '200px', width: '100%' }"
                />
                <img v-else src="https://cdn.vuetifyjs.com/images/cards/docks.jpg" style="height:200px; width:100%; object-fit:cover;" />
              </div>

              <v-card-title class="text-subtitle-1 font-weight-bold">{{ product.title }}</v-card-title>
              <v-card-subtitle>{{ product.category?.title }}</v-card-subtitle>
              <v-card-text class="text-h6 font-weight-bold primary--text">{{ product.price }} $</v-card-text>
            </v-card>
          </router-link>
        </v-slide-group-item>
      </v-slide-group>
    </v-sheet>
  </div>
</template>

<script setup>
import ProductResponsiveImagePublic from '@/components/ProductResponsiveImagePublic.vue';

defineProps({
  title: {
    type: String,
    required: true,
  },
  products: {
    type: Array,
    required: true,
  },
});

// helper: вернуть первое имя файла из product.image_url,
// допускаем, что product.image_url — массив или JSON-строка
function firstImageName(product) {
  const images = product.image_url || [];
  if (Array.isArray(images) && images.length) return images[0];
  if (typeof images === 'string' && images) {
    try {
      const parsed = JSON.parse(images);
      if (Array.isArray(parsed) && parsed.length) return parsed[0];
    } catch (e) {
      // строка — вернём её как есть
      return images;
    }
  }
  return null;
}
function hasImage(product) {
  const first = firstImageName(product);
  return !!first;
}
</script>

<style scoped>
/* CHANGED: убираем подчеркивание и наследуем цвет, делаем ссылку блочной чтобы занимала весь v-card */
.no-decoration {
  text-decoration: none;
  color: inherit;
  display: block;
}
</style>