import Alpine from 'alpinejs';

window.Alpine = Alpine;

function initFileNamePreviews() {
    document.querySelectorAll('[data-file-input]').forEach((input) => {
        const targetId = input.dataset.fileNameTarget;
        const target = targetId ? document.getElementById(targetId) : null;
        const defaultText = target?.textContent || '';

        if (!target) {
            return;
        }

        input.addEventListener('change', () => {
            target.textContent = input.files?.[0]?.name || defaultText;
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initFileNamePreviews();
    });
} else {
    initFileNamePreviews();
}

Alpine.start();
