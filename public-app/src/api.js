// Файл: public-app/src/api.js
import axios from 'axios';

const apiClient = axios.create({
  baseURL: '/api', // Относительный URL - это правильно и хорошо
  withCredentials: true, // <-- КРИТИЧЕСКИ ВАЖНО для отправки cookie
  xsrfCookieName: 'XSRF-TOKEN', // Говорим Axios, как называется CSRF-cookie
  xsrfHeaderName: 'X-XSRF-TOKEN', // Говорим Axios, какой заголовок нужно отправлять
});

/**
 * Функция для получения CSRF cookie.
 * Она нужна перед любой POST/PUT/DELETE операцией.
 * Мы используем базовый axios, чтобы обратиться к корневому пути, а не к /api
 */
apiClient.getCsrfCookie = () => {
  // axios.create() без baseURL будет использовать текущий домен
  const sanctumClient = axios.create({ 
    withCredentials: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
  });
  return sanctumClient.get('/sanctum/csrf-cookie');
};

export default apiClient;