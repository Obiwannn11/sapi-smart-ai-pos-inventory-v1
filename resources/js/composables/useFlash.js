import { ref } from 'vue';

// Module-level singleton — all useFlash() calls share the same state.
// This lets any component trigger a flash without prop drilling.
const _message = ref('');
const _type = ref('success');
const _visible = ref(false);
let _timer = null;

export function useFlash() {
    const show = (msg, type = 'success', duration = 4000) => {
        clearTimeout(_timer);
        _message.value = msg;
        _type.value = type;
        _visible.value = true;
        _timer = setTimeout(() => { _visible.value = false; }, duration);
    };

    const dismiss = () => {
        clearTimeout(_timer);
        _visible.value = false;
    };

    return {
        message: _message,
        type: _type,
        visible: _visible,
        show,
        dismiss,
    };
}
