/**
 * Delay calling `callback` until `wait` ms have passed since the last call.
 */
export default function debounce(callback, wait = 500) {
    let timeout;

    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => callback.apply(this, args), wait);
    };
}
