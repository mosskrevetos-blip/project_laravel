import dayjs from 'dayjs';

/**
 * Форматирует строку с датой в локальный, человекочитаемый формат.
 * @param {string} dateString - Дата в формате ISO (например, "2025-09-28T19:39:10.000000Z")
 * @returns {string} - Отформатированная дата (например, "28.09.2025 22:39")
 */
export function formatDate(dateString) {
  if (!dateString) return '';
  return dayjs(dateString).format('DD.MM.YYYY HH:mm');
}