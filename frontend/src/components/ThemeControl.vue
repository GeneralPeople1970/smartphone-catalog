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
      <svg
        class="shared-theme-icon"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path
          d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.51a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2Z"
        />
        <circle cx="12" cy="12" r="3" />
      </svg>
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
