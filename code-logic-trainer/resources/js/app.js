import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.normalizeQuestionTimer = (value, commit = false) => {
    if (value === '' && !commit) return '';

    const seconds = Number.parseInt(value, 10);
    if (!Number.isFinite(seconds)) return 20;
    if (seconds === 0 && !commit) return 0;
    if (seconds <= 0) return 20;

    return Math.max(1, Math.min(100, seconds));
};
Alpine.start();
