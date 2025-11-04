# Файл: e-commerce-project/admin-app/Dockerfile
# И такой же в e-commerce-project/public-app/Dockerfile

FROM node:20

WORKDIR /app

# Копируем package.json и устанавливаем зависимости
COPY package*.json ./
RUN npm install

# Копируем остальные файлы
COPY . .

# Команда для запуска сервера разработки
CMD ["npm", "run", "dev", "--", "--host"]