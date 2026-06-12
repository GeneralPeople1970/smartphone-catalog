import Alpine from 'alpinejs';

window.Alpine = Alpine;

const themeStorageKey = 'smartphone_catalog_theme';
const themeChangeEvent = 'smartphone-theme-change';
const themeDefaults = { mode: 'light', primaryColor: 'blue' };
const themeColors = {
    blue: { value: '#2563eb', rgb: '37, 99, 235', hover: '#1d4ed8' },
    emerald: { value: '#059669', rgb: '5, 150, 105', hover: '#047857' },
    violet: { value: '#7c3aed', rgb: '124, 58, 237', hover: '#6d28d9' },
    rose: { value: '#e11d48', rgb: '225, 29, 72', hover: '#be123c' },
    amber: { value: '#d97706', rgb: '217, 119, 6', hover: '#b45309' },
};

let themeMediaQuery = null;
let themeMediaHandler = null;

function readTheme() {
    try {
        return normalizeTheme(JSON.parse(window.localStorage.getItem(themeStorageKey) || '{}'));
    } catch (error) {
        return { ...themeDefaults };
    }
}

function normalizeTheme(theme = {}) {
    return {
        mode: ['light', 'dark', 'system'].includes(theme.mode) ? theme.mode : themeDefaults.mode,
        primaryColor: themeColors[theme.primaryColor] ? theme.primaryColor : themeDefaults.primaryColor,
    };
}

function applyTheme(theme = readTheme()) {
    const normalizedTheme = normalizeTheme(theme);
    const palette = themeColors[normalizedTheme.primaryColor] || themeColors.blue;
    const root = document.documentElement;

    root.dataset.themeMode = normalizedTheme.mode;
    root.dataset.primaryColor = normalizedTheme.primaryColor;
    root.style.setProperty('--bs-primary', palette.value);
    root.style.setProperty('--bs-primary-rgb', palette.rgb);
    root.style.setProperty('--bs-link-color', palette.value);
    root.style.setProperty('--bs-link-hover-color', palette.hover);
    root.style.setProperty('--app-primary', palette.value);
    root.style.setProperty('--app-primary-rgb', palette.rgb);
    root.style.setProperty('--app-primary-hover', palette.hover);
    root.style.setProperty('--ui-primary', palette.value);
    root.style.setProperty('--ui-primary-rgb', palette.rgb);
    root.style.setProperty('--ui-primary-hover', palette.hover);
    applyColorMode(normalizedTheme.mode);

    return normalizedTheme;
}

function saveTheme(theme) {
    const normalizedTheme = normalizeTheme(theme);

    window.localStorage.setItem(themeStorageKey, JSON.stringify(normalizedTheme));
    applyTheme(normalizedTheme);
    window.dispatchEvent(new CustomEvent(themeChangeEvent, { detail: normalizedTheme }));

    return normalizedTheme;
}

function applyColorMode(mode) {
    const root = document.documentElement;
    const query = window.matchMedia('(prefers-color-scheme: dark)');
    const applyResolvedMode = () => {
        const resolvedMode = mode === 'system' ? (query.matches ? 'dark' : 'light') : mode;

        root.dataset.bsTheme = resolvedMode;
        root.dataset.resolvedTheme = resolvedMode;
    };

    removeThemeListener();
    applyResolvedMode();

    if (mode === 'system') {
        themeMediaQuery = query;
        themeMediaHandler = applyResolvedMode;

        if (query.addEventListener) {
            query.addEventListener('change', applyResolvedMode);
        } else {
            query.addListener(applyResolvedMode);
        }
    }
}

function removeThemeListener() {
    if (!themeMediaQuery || !themeMediaHandler) {
        return;
    }

    if (themeMediaQuery.removeEventListener) {
        themeMediaQuery.removeEventListener('change', themeMediaHandler);
    } else {
        themeMediaQuery.removeListener(themeMediaHandler);
    }

    themeMediaQuery = null;
    themeMediaHandler = null;
}

applyTheme();

function updateThemeControls(theme = readTheme()) {
    const normalizedTheme = normalizeTheme(theme);

    document.querySelectorAll('[data-theme-control]').forEach((control) => {
        control.querySelectorAll('[data-theme-mode-option]').forEach((button) => {
            const active = button.dataset.themeModeOption === normalizedTheme.mode;
            button.classList.toggle('admin-theme-mode-button-active', active);
        });

        control.querySelectorAll('[data-theme-color-option]').forEach((button) => {
            const active = button.dataset.themeColorOption === normalizedTheme.primaryColor;
            button.classList.toggle('admin-theme-color-button-active', active);
        });
    });
}

function closeThemeControl(control) {
    const panel = control.querySelector('[data-theme-panel]');
    const toggle = control.querySelector('[data-theme-toggle]');

    if (!panel || !toggle) {
        return;
    }

    panel.hidden = true;
    toggle.classList.remove('admin-theme-toggle-active');
    toggle.setAttribute('aria-expanded', 'false');
}

function initThemeControls() {
    const controls = document.querySelectorAll('[data-theme-control]');

    if (!controls.length) {
        return;
    }

    updateThemeControls();

    controls.forEach((control) => {
        const toggle = control.querySelector('[data-theme-toggle]');
        const panel = control.querySelector('[data-theme-panel]');

        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', () => {
            const nextOpen = panel.hidden;

            controls.forEach((item) => {
                if (item !== control) {
                    closeThemeControl(item);
                }
            });

            panel.hidden = !nextOpen;
            toggle.classList.toggle('admin-theme-toggle-active', nextOpen);
            toggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
        });

        control.querySelectorAll('[data-theme-mode-option]').forEach((button) => {
            button.addEventListener('click', () => {
                saveTheme({
                    ...readTheme(),
                    mode: button.dataset.themeModeOption,
                });
                updateThemeControls();
            });
        });

        control.querySelectorAll('[data-theme-color-option]').forEach((button) => {
            button.addEventListener('click', () => {
                saveTheme({
                    ...readTheme(),
                    primaryColor: button.dataset.themeColorOption,
                });
                updateThemeControls();
            });
        });
    });

    document.addEventListener('click', (event) => {
        controls.forEach((control) => {
            if (!control.contains(event.target)) {
                closeThemeControl(control);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            controls.forEach(closeThemeControl);
        }
    });
}

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

window.addEventListener('storage', (event) => {
    if (event.key === themeStorageKey) {
        const theme = readTheme();

        applyTheme(theme);
        updateThemeControls(theme);
    }
});

window.addEventListener(themeChangeEvent, (event) => {
    const theme = applyTheme(event.detail);

    updateThemeControls(theme);
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initThemeControls();
        initFileNamePreviews();
    });
} else {
    initThemeControls();
    initFileNamePreviews();
}

Alpine.start();
