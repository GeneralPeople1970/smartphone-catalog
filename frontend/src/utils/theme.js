export const THEME_STORAGE_KEY = 'smartphone_catalog_theme'
export const THEME_CHANGE_EVENT = 'smartphone-theme-change'

export const THEME_MODES = {
  light: '浅色',
  dark: '深色',
  system: '跟随系统',
}

export const THEME_COLORS = {
  blue: { name: '蓝色', value: '#2563eb', rgb: '37, 99, 235', hover: '#1d4ed8' },
  emerald: { name: '翠绿', value: '#059669', rgb: '5, 150, 105', hover: '#047857' },
  violet: { name: '紫色', value: '#7c3aed', rgb: '124, 58, 237', hover: '#6d28d9' },
  rose: { name: '玫红', value: '#e11d48', rgb: '225, 29, 72', hover: '#be123c' },
  amber: { name: '琥珀', value: '#d97706', rgb: '217, 119, 6', hover: '#b45309' },
}

export const DEFAULT_THEME = {
  mode: 'light',
  primaryColor: 'blue',
}

let mediaQuery = null
let mediaHandler = null

export function normalizeTheme(theme = {}) {
  return {
    mode: Object.prototype.hasOwnProperty.call(THEME_MODES, theme.mode)
      ? theme.mode
      : DEFAULT_THEME.mode,
    primaryColor: Object.prototype.hasOwnProperty.call(THEME_COLORS, theme.primaryColor)
      ? theme.primaryColor
      : DEFAULT_THEME.primaryColor,
  }
}

export function readStoredTheme() {
  try {
    const stored = window.localStorage.getItem(THEME_STORAGE_KEY)
    return normalizeTheme(stored ? JSON.parse(stored) : DEFAULT_THEME)
  } catch (error) {
    console.error(error)
    return { ...DEFAULT_THEME }
  }
}

export function saveTheme(theme) {
  const normalizedTheme = normalizeTheme(theme)

  window.localStorage.setItem(THEME_STORAGE_KEY, JSON.stringify(normalizedTheme))
  applyTheme(normalizedTheme)
  window.dispatchEvent(new CustomEvent(THEME_CHANGE_EVENT, { detail: normalizedTheme }))

  return normalizedTheme
}

export function applyTheme(theme) {
  const normalizedTheme = normalizeTheme(theme)
  const palette = THEME_COLORS[normalizedTheme.primaryColor] || THEME_COLORS.blue
  const root = document.documentElement

  root.dataset.themeMode = normalizedTheme.mode
  root.dataset.primaryColor = normalizedTheme.primaryColor
  root.style.setProperty('--bs-primary', palette.value)
  root.style.setProperty('--bs-primary-rgb', palette.rgb)
  root.style.setProperty('--bs-link-color', palette.value)
  root.style.setProperty('--bs-link-hover-color', palette.hover)
  root.style.setProperty('--app-primary', palette.value)
  root.style.setProperty('--app-primary-rgb', palette.rgb)
  root.style.setProperty('--app-primary-hover', palette.hover)
  root.style.setProperty('--ui-primary', palette.value)
  root.style.setProperty('--ui-primary-rgb', palette.rgb)
  root.style.setProperty('--ui-primary-hover', palette.hover)

  applyColorMode(normalizedTheme.mode)

  return normalizedTheme
}

function applyColorMode(mode) {
  const root = document.documentElement
  const query = window.matchMedia('(prefers-color-scheme: dark)')
  const applyResolvedMode = () => {
    const resolvedMode = mode === 'system' ? (query.matches ? 'dark' : 'light') : mode

    root.dataset.bsTheme = resolvedMode
    root.dataset.resolvedTheme = resolvedMode
  }

  removeThemeListener()
  applyResolvedMode()

  if (mode === 'system') {
    mediaQuery = query
    mediaHandler = applyResolvedMode

    if (query.addEventListener) {
      query.addEventListener('change', applyResolvedMode)
    } else {
      query.addListener(applyResolvedMode)
    }
  }
}

function removeThemeListener() {
  if (!mediaQuery || !mediaHandler) {
    return
  }

  if (mediaQuery.removeEventListener) {
    mediaQuery.removeEventListener('change', mediaHandler)
  } else {
    mediaQuery.removeListener(mediaHandler)
  }

  mediaQuery = null
  mediaHandler = null
}
