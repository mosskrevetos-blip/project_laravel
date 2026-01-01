# API Документация E-Commerce Platform

## Базовый URL

```
http://localhost:8000/api
```

## Аутентификация

Для защищенных эндпоинтов используется Laravel Sanctum (Bearer Token).

### Получение токена

```http
POST /login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

**Ответ:**
```json
{
  "token": "1|abcdefghijklmnopqrstuvwxyz",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com"
  }
}
```

### Использование токена

Добавьте заголовок в запрос:
```
Authorization: Bearer {token}
```

---

## Эндпоинты API

## 📦 Товары (Products)

### Получить список товаров

```http
GET /products
```

**Параметры запроса:**
- `page` (integer, optional) - номер страницы для пагинации
- `per_page` (integer, optional) - количество элементов на странице
- `category_id` (integer, optional) - фильтр по категории
- `search` (string, optional) - поиск по названию
- `sort` (string, optional) - сортировка: `price_asc`, `price_desc`, `newest`, `popular`

**Пример запроса:**
```http
GET /products?page=1&per_page=20&category_id=3&sort=price_asc
```

**Ответ:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Смартфон Samsung Galaxy S21",
      "description": "Флагманский смартфон с отличной камерой",
      "price": 29999.99,
      "sku": "SAMSUNG-S21-001",
      "quantity": 50,
      "image_url": [
        "/storage/products/image1.jpg",
        "/storage/products/image2.jpg"
      ],
      "video_urls": [
        "https://youtube.com/watch?v=..."
      ],
      "category_id": 3,
      "secondary_category_id": 5,
      "city": "Киев",
      "properties": {
        "color": "Черный",
        "memory": "128GB",
        "ram": "8GB"
      },
      "category": {
        "id": 3,
        "name": "Смартфоны",
        "slug": "smartphones"
      },
      "created_at": "2025-01-01T12:00:00Z",
      "updated_at": "2025-01-01T12:00:00Z"
    }
  ],
  "links": {},
  "meta": {}
}
```

### Получить популярные товары

```http
GET /products/popular
```

**Параметры:**
- `limit` (integer, optional, default: 10) - количество товаров

**Ответ:** Массив товаров (структура как выше)

### Получить новые товары

```http
GET /products/newest
```

**Параметры:**
- `limit` (integer, optional, default: 10) - количество товаров

**Ответ:** Массив товаров (структура как выше)

### Получить конкретный товар

```http
GET /products/{id}
```

**Ответ:**
```json
{
  "id": 1,
  "title": "Смартфон Samsung Galaxy S21",
  "description": "Флагманский смартфон...",
  "price": 29999.99,
  "sku": "SAMSUNG-S21-001",
  "quantity": 50,
  "image_url": [...],
  "video_urls": [...],
  "category": {...},
  "secondary_category": {...},
  "user": {
    "id": 2,
    "name": "Seller Name"
  },
  "properties": {...}
}
```

### Создать товар (🔒 Auth)

```http
POST /products
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "title": "Новый товар",
  "description": "Описание товара",
  "price": 999.99,
  "sku": "SKU-001",
  "quantity": 100,
  "category_id": 3,
  "secondary_category_id": 5,
  "city": "Киев",
  "properties": {
    "color": "Синий",
    "size": "M"
  },
  "images": [File, File],
  "video_urls": ["https://youtube.com/..."]
}
```

**Ответ:**
```json
{
  "message": "Product created successfully",
  "product": {...}
}
```

### Обновить товар (🔒 Auth)

```http
PUT /products/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Обновленное название",
  "price": 1299.99,
  "quantity": 150
}
```

### Удалить товар (🔒 Auth)

```http
DELETE /products/{id}
Authorization: Bearer {token}
```

**Ответ:**
```json
{
  "message": "Product deleted successfully"
}
```

---

## 📁 Категории (Categories)

### Получить список категорий

```http
GET /categories
```

**Параметры:**
- `parent_id` (integer, optional) - фильтр по родительской категории
- `include_children` (boolean, optional) - включить вложенные категории

**Ответ:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Электроника",
      "slug": "electronics",
      "parent_id": null,
      "children": [
        {
          "id": 2,
          "name": "Смартфоны",
          "slug": "smartphones",
          "parent_id": 1
        }
      ]
    }
  ]
}
```

### Получить конкретную категорию

```http
GET /categories/{id}
```

**Ответ:**
```json
{
  "id": 1,
  "name": "Электроника",
  "slug": "electronics",
  "parent_id": null,
  "children": [...],
  "products_count": 150,
  "attributes": [...]
}
```

### Получить атрибуты категории

```http
GET /categories/{id}/attributes
```

**Ответ:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Цвет",
      "type": "select",
      "options": [
        {
          "id": 1,
          "value": "Черный"
        },
        {
          "id": 2,
          "value": "Белый"
        }
      ]
    },
    {
      "id": 2,
      "name": "Размер экрана",
      "type": "text"
    }
  ]
}
```

### Подсказки категорий (для автодополнения)

```http
GET /categories/suggest?q=smart
```

**Ответ:**
```json
{
  "suggestions": [
    {
      "id": 2,
      "name": "Смартфоны",
      "slug": "smartphones"
    }
  ]
}
```

### Создать категорию (🔒 Admin)

```http
POST /categories
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Ноутбуки",
  "slug": "laptops",
  "parent_id": 1
}
```

### Обновить категорию (🔒 Admin)

```http
PUT /categories/{id}
Authorization: Bearer {token}
```

### Удалить категорию (🔒 Admin)

```http
DELETE /categories/{id}
Authorization: Bearer {token}
```

---

## 👥 Пользователи (Users)

### Получить текущего пользователя (🔒 Auth)

```http
GET /user
Authorization: Bearer {token}
```

**Ответ:**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "roles": [
    {
      "id": 1,
      "name": "admin"
    }
  ],
  "created_at": "2025-01-01T12:00:00Z"
}
```

### Получить список пользователей (🔒 Admin)

```http
GET /users
Authorization: Bearer {token}
```

**Параметры:**
- `page` (integer, optional)
- `per_page` (integer, optional)
- `role` (string, optional) - фильтр по роли

**Ответ:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "roles": [...],
      "created_at": "2025-01-01T12:00:00Z"
    }
  ]
}
```

### Получить конкретного пользователя (🔒 Admin)

```http
GET /users/{id}
Authorization: Bearer {token}
```

### Создать пользователя (🔒 Admin)

```http
POST /users
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "password": "securepassword",
  "role_ids": [2]
}
```

### Обновить пользователя (🔒 Admin)

```http
PUT /users/{id}
Authorization: Bearer {token}

{
  "name": "Jane Smith Updated",
  "email": "jane.updated@example.com"
}
```

### Удалить пользователя (🔒 Admin)

```http
DELETE /users/{id}
Authorization: Bearer {token}
```

---

## 🛒 Заказы (Orders)

### Создать заказ (публичный)

```http
POST /orders/public
Content-Type: application/json

{
  "customer_name": "Иван Иванов",
  "customer_email": "ivan@example.com",
  "customer_phone": "+380501234567",
  "shipping_address": "ул. Примерная, 123, Киев",
  "products": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 5,
      "quantity": 1
    }
  ],
  "notes": "Доставка после 18:00"
}
```

**Ответ:**
```json
{
  "message": "Order created successfully",
  "order": {
    "id": 42,
    "order_number": "ORD-2025-000042",
    "customer_name": "Иван Иванов",
    "customer_email": "ivan@example.com",
    "customer_phone": "+380501234567",
    "shipping_address": "ул. Примерная, 123, Киев",
    "status": "pending",
    "total_amount": 59999.98,
    "products": [
      {
        "id": 1,
        "title": "Смартфон Samsung Galaxy S21",
        "quantity": 2,
        "price": 29999.99
      }
    ],
    "created_at": "2025-01-01T14:30:00Z"
  }
}
```

### Получить список заказов (🔒 Admin)

```http
GET /orders
Authorization: Bearer {token}
```

**Параметры:**
- `status` (string, optional) - фильтр по статусу: `pending`, `processing`, `completed`, `cancelled`
- `page` (integer, optional)
- `per_page` (integer, optional)

### Получить конкретный заказ (🔒 Auth)

```http
GET /orders/{id}
Authorization: Bearer {token}
```

### Обновить статус заказа (🔒 Admin)

```http
PUT /orders/{id}
Authorization: Bearer {token}

{
  "status": "processing"
}
```

### Удалить заказ (🔒 Admin)

```http
DELETE /orders/{id}
Authorization: Bearer {token}
```

---

## 🎨 Атрибуты (Attributes)

### Получить список атрибутов (🔒 Admin)

```http
GET /attributes
Authorization: Bearer {token}
```

### Создать атрибут (🔒 Admin)

```http
POST /attributes
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Цвет",
  "type": "select",
  "category_ids": [1, 2, 3],
  "options": [
    {"value": "Черный"},
    {"value": "Белый"},
    {"value": "Синий"}
  ]
}
```

### Обновить атрибут (🔒 Admin)

```http
PUT /attributes/{id}
Authorization: Bearer {token}
```

### Удалить атрибут (🔒 Admin)

```http
DELETE /attributes/{id}
Authorization: Bearer {token}
```

---

## 🔐 Роли (Roles)

### Получить список ролей (🔒 Admin)

```http
GET /roles
Authorization: Bearer {token}
```

**Ответ:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "admin",
      "display_name": "Администратор",
      "users_count": 3
    },
    {
      "id": 2,
      "name": "seller",
      "display_name": "Продавец",
      "users_count": 25
    },
    {
      "id": 3,
      "name": "user",
      "display_name": "Пользователь",
      "users_count": 150
    }
  ]
}
```

### Создать роль (🔒 Admin)

```http
POST /roles
Authorization: Bearer {token}

{
  "name": "moderator",
  "display_name": "Модератор"
}
```

---

## Коды ответов HTTP

- `200 OK` - Успешный запрос
- `201 Created` - Ресурс успешно создан
- `204 No Content` - Успешное удаление
- `400 Bad Request` - Неверный запрос
- `401 Unauthorized` - Не авторизован
- `403 Forbidden` - Нет прав доступа
- `404 Not Found` - Ресурс не найден
- `422 Unprocessable Entity` - Ошибка валидации
- `500 Internal Server Error` - Ошибка сервера

## Формат ошибок

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

## Пагинация

Большинство списковых эндпоинтов поддерживают пагинацию:

**Параметры:**
- `page` - номер страницы (начиная с 1)
- `per_page` - количество элементов на странице (по умолчанию 15, максимум 100)

**Формат ответа:**
```json
{
  "data": [...],
  "links": {
    "first": "http://localhost:8000/api/products?page=1",
    "last": "http://localhost:8000/api/products?page=5",
    "prev": null,
    "next": "http://localhost:8000/api/products?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 75
  }
}
```

## Rate Limiting

API имеет ограничение на количество запросов:
- Для авторизованных пользователей: 60 запросов в минуту
- Для неавторизованных: 30 запросов в минуту

При превышении лимита API вернет код `429 Too Many Requests`.

## CORS

API настроен для приема запросов с:
- http://localhost:5173 (Admin App)
- http://localhost:5174 (Public App)

В production необходимо настроить CORS для ваших доменов.

## Примеры использования

### JavaScript (Axios)

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
  }
});

// Добавить токен к каждому запросу
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Получить товары
const products = await api.get('/products');

// Создать заказ
const order = await api.post('/orders/public', {
  customer_name: 'Иван Иванов',
  customer_email: 'ivan@example.com',
  products: [
    { product_id: 1, quantity: 2 }
  ]
});
```

### cURL

```bash
# Получить товары
curl http://localhost:8000/api/products

# Авторизация
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Создать товар (с токеном)
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "title": "Новый товар",
    "price": 999.99,
    "quantity": 100,
    "category_id": 1
  }'
```

## Тестирование API

Рекомендуемые инструменты:
- [Postman](https://www.postman.com/) - GUI клиент
- [Insomnia](https://insomnia.rest/) - GUI клиент
- [HTTPie](https://httpie.io/) - CLI клиент
- [cURL](https://curl.se/) - CLI клиент

## Дополнительная информация

Для более подробной информации о проекте см.:
- [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) - Общее описание проекта
- [QUICK_START.md](QUICK_START.md) - Быстрый старт
