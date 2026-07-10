import { computed, ref } from 'vue';

export function useUiBlocker() {
  const _counter = ref(0);
  const _message = ref('Обробка...');

  const isBlocked = computed(() => _counter.value > 0);
  const message = computed(() => _message.value);

  function block(text = 'Обробка...') {
    _message.value = text;
    _counter.value += 1;
  }

  function unblock() {
    _counter.value = Math.max(0, _counter.value - 1);
  }

  function setMessage(text = 'Обробка...') {
    _message.value = text;
  }

  async function withBlock(task, text = 'Обробка...') {
    block(text);
    try {
      return await task();
    } finally {
      unblock();
    }
  }

  return {
    isBlocked,
    message,
    block,
    unblock,
    setMessage,
    withBlock,
  };
}