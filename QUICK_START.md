# Быстрый старт проекта E-Commerce

## Предварительные требования

Убедитесь, что на вашем компьютере установлены:
- Docker Desktop
- Docker Compose
- Git

## Шаг 1: Клонирование репозитория

```bash
git clone https://github.com/mosskrevetos-blip/project_laravel.git
cd project_laravel
```

## Шаг 2: Настройка Backend (Laravel)

### 2.1 Настройка .env файла

```bash
cd backend
cp .env.example .env
```

Откройте `.env` файл и убедитесь, что настройки базы данных соответствуют docker-compose.yml:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=ecom_db
DB_USERNAME=root
DB_PASSWORD=root
```

### 2.2 Генерация ключа приложения

Это будет сделано внутри Docker контейнера после запуска.

## Шаг 3: Запуск Docker контейнеров

Вернитесь в корневую директорию проекта и запустите все сервисы:

```bash
cd ..
docker-compose up -d
```

Это запустит:
- ✅ NGINX (порт 8000)
- ✅ Laravel App (PHP-FPM)
- ✅ MySQL (порт 3307)
- ✅ PHPMyAdmin (порт 8081)
- ✅ Admin App (Vue.js)
- ✅ Public App (Vue.js, порт 5174)

## Шаг 4: Инициализация Backend

### 4.1 Войти в контейнер приложения

```bash
docker exec -it ecom_app bash
```

### 4.2 Установить зависимости Composer

```bash
composer install
```

### 4.3 Генерация ключа приложения

```bash
php artisan key:generate
```

### 4.4 Запустить миграции

```bash
php artisan migrate
```

### 4.5 (Опционально) Заполнить базу тестовыми данными

```bash
php artisan db:seed
```

### 4.6 Выйти из контейнера

```bash
exit
```

## Шаг 5: Настройка Admin App

### 5.1 Войти в контейнер Admin App

```bash
docker exec -it ecom_admin_app sh
```

### 5.2 Установить зависимости

```bash
npm install
```

### 5.3 Запустить dev сервер (если еще не запущен)

```bash
npm run dev
```

### 5.4 Выйти из контейнера

```bash
exit
```

## Шаг 6: Настройка Public App

### 6.1 Войти в контейнер Public App

```bash
docker exec -it ecom_public_app sh
```

### 6.2 Установить зависимости

```bash
npm install
```

### 6.3 Запустить dev сервер (если еще не запущен)

```bash
npm run dev
```

### 6.4 Выйти из контейнера

```bash
exit
```

## Шаг 7: Проверка работоспособности

Откройте в браузере:

1. **Backend API:** http://localhost:8000/api/products
   - Должен вернуть JSON со списком товаров

2. **Admin Panel:** http://localhost:5173
   - Административная панель Vue.js

3. **Public Site:** http://localhost:5174
   - Публичный сайт для покупателей

4. **PHPMyAdmin:** http://localhost:8081
   - Логин: root
   - Пароль: root

## Проблемы и решения

### Проблема: Порты уже заняты

**Решение:** Измените порты в `docker-compose.yml`

```yaml
ports:
  - "8001:80"  # Вместо 8000
```

### Проблема: Docker контейнеры не запускаются

**Решение:**
```bash
# Остановить все контейнеры
docker-compose down

# Очистить volumes
docker-compose down -v

# Пересобрать образы
docker-compose build --no-cache

# Запустить снова
docker-compose up -d
```

### Проблема: Ошибки миграций базы данных

**Решение:**
```bash
# Войти в контейнер
docker exec -it ecom_app bash

# Откатить миграции
php artisan migrate:rollback

# Запустить снова
php artisan migrate:fresh --seed
```

### Проблема: Frontend не запускается

**Решение:**
```bash
# Удалить node_modules и package-lock.json
docker exec -it ecom_admin_app sh -c "rm -rf node_modules package-lock.json"

# Переустановить зависимости
docker exec -it ecom_admin_app sh -c "npm install"

# Запустить dev сервер
docker exec -it ecom_admin_app sh -c "npm run dev"
```

## Полезные команды Docker

```bash
# Просмотр логов всех сервисов
docker-compose logs -f

# Просмотр логов конкретного сервиса
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f db

# Перезапуск сервиса
docker-compose restart app

# Остановка всех сервисов
docker-compose stop

# Запуск всех сервисов
docker-compose start

# Полное удаление (с volumes)
docker-compose down -v

# Просмотр запущенных контейнеров
docker ps

# Просмотр использования ресурсов
docker stats
```

## Разработка

### Backend (Laravel)

```bash
# Войти в контейнер
docker exec -it ecom_app bash

# Запустить тесты
php artisan test

# Создать контроллер
php artisan make:controller Api/TestController

# Создать модель с миграцией
php artisan make:model Test -m

# Создать миграцию
php artisan make:migration create_tests_table

# Очистить кеш
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Форматирование кода
./vendor/bin/pint
```

### Frontend (Admin/Public App)

```bash
# Войти в контейнер (Admin)
docker exec -it ecom_admin_app sh

# Войти в контейнер (Public)
docker exec -it ecom_public_app sh

# Dev сервер (hot reload)
npm run dev

# Production сборка
npm run build

# Preview production сборки
npm run preview
```

## Следующие шаги

1. **Создать первого администратора**
   - Используйте Laravel Tinker или создайте seeder

2. **Настроить права доступа**
   - Создайте роли: Admin, Seller, User
   - Настройте Policies для контроллеров

3. **Добавить товары**
   - Через Admin Panel
   - Или через API

4. **Настроить категории**
   - Создайте структуру категорий
   - Привяжите атрибуты к категориям

5. **Тестирование**
   - Проверьте все CRUD операции
   - Протестируйте регистрацию и вход
   - Проверьте создание заказов

## Документация

- [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) - Подробное описание проекта
- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Documentation](https://vuejs.org/guide/)
- [Vuetify Documentation](https://vuetifyjs.com/)
- [Docker Documentation](https://docs.docker.com/)

## Поддержка

Если у вас возникли вопросы или проблемы:
1. Проверьте логи Docker: `docker-compose logs -f`
2. Проверьте статус контейнеров: `docker ps -a`
3. Убедитесь, что все порты свободны
4. Попробуйте пересобрать контейнеры: `docker-compose build --no-cache`

Удачи в разработке! 🚀
