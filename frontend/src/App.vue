<template>
  <div class="app-container">
    <NavBar />
    <router-view />
    <FooterBar />
  </div>
</template>

<script>
import NavBar from './components/NavBar.vue'
import FooterBar from './components/FooterBar.vue'
import {
  applyTheme,
  readStoredTheme,
  THEME_CHANGE_EVENT,
  THEME_STORAGE_KEY,
} from '@/utils/theme.js'

export default {
  components: {
    NavBar,
    FooterBar,
  },
  mounted() {
    this.applyStoredTheme()
    window.addEventListener(THEME_CHANGE_EVENT, this.handleThemeChange)
    window.addEventListener('storage', this.handleStorageChange)
  },
  beforeUnmount() {
    window.removeEventListener(THEME_CHANGE_EVENT, this.handleThemeChange)
    window.removeEventListener('storage', this.handleStorageChange)
  },
  methods: {
    applyStoredTheme() {
      applyTheme(readStoredTheme())
    },
    handleThemeChange(event) {
      applyTheme(event.detail)
    },
    handleStorageChange(event) {
      if (event.key === THEME_STORAGE_KEY) {
        this.applyStoredTheme()
      }
    },
  },
}
</script>

<style>
:root,
[data-bs-theme='light'] {
  --front-container-width: var(
    --shared-nav-container-width,
    min(1760px, calc(100% - clamp(24px, 4vw, 80px)))
  );
  --bs-body-bg: #f2f2f2;
  --bs-body-color: #1f2d3d;
  --bs-secondary-color: #6c757d;
  --bs-tertiary-bg: #f5f9ff;
  --bs-border-color: #e5edf6;
  --page-bg: #f2f2f2;
  --surface-bg: #ffffff;
  --surface-muted: #f5f9ff;
  --text-main: #1f2d3d;
  --text-muted: #6c757d;
  --border-soft: #e5edf6;
}

[data-bs-theme='dark'] {
  color-scheme: dark;
  --bs-body-bg: #111827;
  --bs-body-color: #f8fafc;
  --bs-secondary-color: #cbd5e1;
  --bs-tertiary-bg: #1f2937;
  --bs-border-color: #475569;
  --page-bg: #0f172a;
  --surface-bg: #111827;
  --surface-muted: #1f2937;
  --text-main: var(--bs-body-color);
  --text-muted: var(--bs-secondary-color);
  --border-soft: var(--bs-border-color);
}

body {
  background-color: var(--page-bg);
  color: var(--text-main);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.app-container {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.app-container .container {
  width: var(--front-container-width) !important;
  max-width: none !important;
  padding-right: 0 !important;
  padding-left: 0 !important;
}

/* 页面内容区域 */
.page-content {
  flex: 1;
  padding: 20px 0;
}

a {
  color: var(--app-primary);
}

.btn-primary {
  border-color: var(--app-primary) !important;
  background-color: var(--app-primary) !important;
}

.btn-primary:hover {
  border-color: var(--app-primary-hover) !important;
  background-color: var(--app-primary-hover) !important;
}

.btn-outline-dark {
  border-color: var(--app-primary) !important;
  color: var(--app-primary) !important;
}

.btn-outline-dark:hover {
  background-color: var(--app-primary) !important;
  color: #fff !important;
}

.form-control:focus,
.form-select:focus {
  border-color: rgba(var(--app-primary-rgb), 0.55) !important;
  box-shadow: 0 0 0 0.2rem rgba(var(--app-primary-rgb), 0.18) !important;
}

[data-bs-theme='dark'] .bg-white,
[data-bs-theme='dark'] .card,
[data-bs-theme='dark'] .list-group-item,
[data-bs-theme='dark'] .navbar,
[data-bs-theme='dark'] .form-control {
  background-color: var(--surface-bg) !important;
  color: var(--text-main) !important;
  border-color: var(--border-soft) !important;
}

[data-bs-theme='dark'] .text-dark,
[data-bs-theme='dark'] h1,
[data-bs-theme='dark'] h2,
[data-bs-theme='dark'] h3,
[data-bs-theme='dark'] h4,
[data-bs-theme='dark'] h5 {
  color: var(--text-main) !important;
}

[data-bs-theme='dark'] .text-muted {
  color: var(--text-muted) !important;
}

@media (max-width: 991.98px) {
  :root,
  [data-bs-theme='light'] {
    --front-container-width: var(--shared-nav-container-width, calc(100% - 32px));
  }
}
</style>
