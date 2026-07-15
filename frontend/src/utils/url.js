const SAFE_SCHEMES = new Set(['http:', 'https:'])
const BACKSLASH = String.fromCharCode(92)

function hasControlChars(value) {
  for (let i = 0; i < value.length; i += 1) {
    const code = value.charCodeAt(i)
    if (code <= 0x20 || code === 0x7f) return true
  }
  return false
}

/**
 * Return a URL that is safe to place in an href, or '' when it is not.
 *
 * Allows site-relative paths ("/...") and absolute http(s) URLs; rejects
 * javascript:/data:/vbscript: and every other scheme, protocol-relative
 * "//host" (including the backslash "/\host" / "\host" variants), control
 * characters and whitespace. Mirrors the server-side App\Support\SafeUrl
 * check as a second defense layer.
 */
export function safeExternalUrl(value) {
  const trimmed = String(value ?? '').trim()

  if (!trimmed || hasControlChars(trimmed)) return ''

  // Fold backslashes to slashes so "\host" / "/\host" cannot masquerade as a
  // site-relative path and then resolve off-site.
  const normalized = trimmed.split(BACKSLASH).join('/')

  if (normalized.startsWith('/')) {
    return normalized.startsWith('//') ? '' : normalized
  }

  try {
    const url = new URL(normalized, window.location.origin)
    return SAFE_SCHEMES.has(url.protocol) ? url.href : ''
  } catch {
    return ''
  }
}
