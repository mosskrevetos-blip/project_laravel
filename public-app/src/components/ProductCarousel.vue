<template>
  <div class="my-8">
    <h2 class="text-h4 font-weight-bold mb-4">{{ title }}</h2>
    <v-sheet class="mx-auto">
      <v-slide-group class="pa-2" show-arrows>
        <v-slide-group-item v-for="product in products" :key="product.id">
          <v-card class="ma-4" height="350" width="250">
            <v-img
              height="200"
              :src="getProductImageUrl(product)"
              cover
            >
              <template v-slot:placeholder>
                <div class="d-flex align-center justify-center fill-height grey-lighten-3">
                  <v-progress-circular indeterminate color="grey-lighten-1"></v-progress-circular>
                </div>
              </template>
            </v-img>
            
            <v-card-title class="text-subtitle-1 font-weight-bold">{{ product.title }}</v-card-title>
            <v-card-subtitle>{{ product.category?.title }}</v-card-subtitle>
            <v-card-text class="text-h6 font-weight-bold primary--text">{{ product.price }} $</v-card-text>
          </v-card>
        </v-slide-group-item>
      </v-slide-group>
    </v-sheet>
  </div>
</template>

<script setup>
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


function getProductImageUrl(product) {
  // 1. Проверяем, есть ли массив image_url и есть ли в нём хотя бы один элемент
  if (product.image_url && product.image_url.length > 1) {
    return `http://ecom.local:8000/storage/products/${product.image_url[0]}`;
  } 
  
  if (product.image_url && product.image_url.length == 1) {
    return product.image_url[0];
  }
  
  // 3. Если изображений нет, возвращаем "заглушку"
  return 'https://cdn.vuetifyjs.com/images/cards/docks.jpg';
}

</script>