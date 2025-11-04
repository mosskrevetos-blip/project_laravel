import axios from 'axios';

 const apiClient = axios.create({
   baseURL: '/api', 
   withCredentials: true,
   xsrfCookieName: 'XSRF-TOKEN',
   xsrfHeaderName: 'X-XSRF-TOKEN',
 });

 // Запрос на получение CSRF-cookie
 apiClient.getCsrfCookie = () => {
   // Создаем новый клиент БЕЗ baseURL, чтобы обратиться к корневому пути
   const sanctumClient = axios.create({ withCredentials: true });
   return sanctumClient.get('/sanctum/csrf-cookie');
 };

 export default apiClient;