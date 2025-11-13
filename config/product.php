<?php

return [
    // Максимум изображений на товар (целое число)
    'max_images_per_product' => env('MAX_IMAGES_PER_PRODUCT', 8),

    // Максимальный размер файла в мегабайтах
    'max_image_size_mb' => env('MAX_IMAGE_SIZE_MB', 2),

    // Качество WebP (0-100)
    'image_quality' => env('IMAGE_QUALITY', 80),
];