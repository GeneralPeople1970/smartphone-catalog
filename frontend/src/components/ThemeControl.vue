<template>
  <div ref="root" class="theme-control">
    <button
      type="button"
      class="theme-toggle"
      :class="{ 'theme-toggle-active': panelOpen }"
      aria-label="主题设置"
      :aria-expanded="panelOpen ? 'true' : 'false'"
      @click="togglePanel"
    >
      <i class="bi bi-gear" aria-hidden="true"></i>
    </button>

    <div v-if="panelOpen" class="theme-panel" role="dialog" aria-label="主题设置">
      <div class="theme-section">
        <div class="theme-label">显示模式</div>
        <div class="theme-mode-group" role="group" aria-label="显示模式">
          <button
            v-for="(label, value) in modes"
            :key="value"
            type="button"
            class="theme-mode-button"
            :class="{ 'theme-mode-button-active': currentTheme.mode === value }"
            @click="selectMode(value)"
          >
            {{ label }}
          </button>
        </div>
      </div>

      <div class="theme-section">
        <div class="theme-label">主色调</div>
        <div class="theme-color-row" role="group" aria-label="主色调">
          <button
            v-for="(color, value) in colors"
            :key="value"
            type="button"
            class="theme-color-button"
            :class="{ 'theme-color-button-active': currentTheme.primaryColor === value }"
            :aria-label="`主色调：${color.name}`"
            :title="color.name"
            @click="selectColor(value)"
          >
            <span class="theme-color-dot" :style="{ backgroundColor: color.value }"></span>
            <span class="visually-hidden">{{ color.name }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import {
  readStoredTheme,
  saveTheme,
  THEME_CHANGE_EVENT,
  THEME_COLORS,
  THEME_MODES,
  THEME_STORAGE_KEY,
} from '@/utils/theme.js'

export default {
  name: 'ThemeControl',
  data() {
    return {
      panelOpen: false,
      currentTheme: readStoredTheme(),
      colors: THEME_COLORS,
      modes: THEME_MODES,
    }
  },
  mounted() {
    document.addEventListener('click', this.handleOutsideClick)
    document.addEventListener('keydown', this.handleKeydown)
    window.addEventListener(THEME_CHANGE_EVENT, this.handleThemeChange)
    window.addEventListener('storage', this.handleStorageChange)
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleOutsideClick)
    document.removeEventListener('keydown', this.handleKeydown)
    window.removeEventListener(THEME_CHANGE_EVENT, this.handleThemeChange)
    window.removeEventListener('storage', this.handleStorageChange)
  },
  methods: {
    togglePanel() {
      this.panelOpen = !this.panelOpen
    },
    selectMode(mode) {
      this.currentTheme = saveTheme({
        ...this.currentTheme,
        mode,
      })
    },
    selectColor(primaryColor) {
      this.currentTheme = saveTheme({
        ...this.currentTheme,
        primaryColor,
      })
    },
    handleThemeChange(event) {
      this.currentTheme = event.detail || readStoredTheme()
    },
    handleStorageChange(event) {
      if (event.key === THEME_STORAGE_KEY) {
        this.currentTheme = readStoredTheme()
      }
    },
    handleOutsideClick(event) {
      if (this.panelOpen && this.$refs.root && !this.$refs.root.contains(event.target)) {
        this.panelOpen = false
      }
    },
    handleKeydown(event) {
      if (event.key === 'Escape') {
        this.panelOpen = false
      }
    },
  },
}
</script>

<style scoped>
.theme-control {
  position: relative;
  display: inline-flex;
}

.theme-toggle {
  display: inline-flex;
  width: var(--nav-control-size);
  height: var(--nav-control-size);
  align-items: center;
  justify-content: center;
  border: 1px solid var(--nav-border-soft);
  border-radius: 999px;
  background: var(--nav-muted-surface);
  color: var(--nav-text-main);
  font-size: 1rem;
  line-height: 1;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease,
    color 0.2s ease;
}

.theme-toggle:hover,
.theme-toggle:focus,
.theme-toggle-active {
  border-color: var(--app-primary);
  background: rgba(var(--app-primary-rgb), 0.1);
  color: var(--app-primary);
}

.theme-toggle :deep(.bi) {
  color: inherit;
  line-height: 1;
}

.theme-panel {
  position: absolute;
  top: calc(100% + 0.65rem);
  right: 0;
  z-index: 1050;
  width: min(18rem, calc(100vw - 2rem));
  padding: 0.9rem;
  border: 1px solid var(--nav-border-soft);
  border-radius: 8px;
  background: var(--surface-bg);
  color: var(--text-main);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
}

.theme-section + .theme-section {
  margin-top: 0.9rem;
}

.theme-label {
  margin-bottom: 0.45rem;
  color: var(--text-muted);
  font-size: 0.78rem;
  font-weight: 500;
}

.theme-mode-group {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.35rem;
}

.theme-mode-button {
  min-height: 2rem;
  border: 1px solid var(--nav-border-soft);
  border-radius: 6px;
  background: var(--surface-muted);
  color: var(--text-main);
  font-size: 0.8rem;
  font-weight: 400;
}

.theme-mode-button-active {
  border-color: var(--app-primary);
  background: rgba(var(--app-primary-rgb), 0.12);
  color: var(--app-primary);
}

.theme-color-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.theme-color-button {
  display: inline-flex;
  width: 2.35rem;
  height: 2.35rem;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--nav-border-soft);
  border-radius: 999px;
  background: var(--surface-bg);
  padding: 0;
  transition:
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    transform 0.18s ease;
}

.theme-color-button:hover,
.theme-color-button:focus {
  border-color: rgba(var(--app-primary-rgb), 0.55);
  box-shadow: 0 0 0 3px rgba(var(--app-primary-rgb), 0.12);
}

.theme-color-button-active {
  border-color: var(--app-primary);
  box-shadow:
    0 0 0 2px var(--surface-bg),
    0 0 0 4px var(--app-primary);
}

.theme-color-dot {
  display: block;
  width: 1.5rem;
  height: 1.5rem;
  border: 1px solid rgba(15, 23, 42, 0.28);
  border-radius: 999px;
  box-shadow: none;
}

[data-bs-theme='dark'] .theme-color-dot {
  border-color: rgba(255, 255, 255, 0.45);
}

@media (max-width: 991.98px) {
  .theme-toggle {
    width: var(--nav-control-mobile-size);
    height: var(--nav-control-mobile-size);
  }
}
</style>
