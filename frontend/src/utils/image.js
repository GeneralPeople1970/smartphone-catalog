export const PLACEHOLDER_IMAGE = '/assets/phone-placeholder.svg'

const PROTOCOL_PATTERN = /^[a-z][a-z\d+\-.]*:/i
const BACKSLASH = String.fromCharCode(92)

// Data URLs limited to raster/vector image types the catalog actually serves.
const ALLOWED_DATA_IMAGE_PATTERN = /^data:image\/(png|jpe?g|gif|webp|avif|svg\+xml);/i

function hasControlOrWhitespace(value) {
  for (let i = 0; i < value.length; i += 1) {
    const code = value.charCodeAt(i)
    // C0 controls, space and DEL — covers tab/newline/NBSP-style obfuscation.
    if (code <= 0x20 || code === 0x7f) return true
  }
  return false
}

function isLocalHttpUrl(url) {
  const localHosts = new Set(['localhost', '127.0.0.1', '::1', window.location.hostname])
  const pageProtocol = new URL(window.location.origin).protocol

  if (pageProtocol === 'https:' && url.protocol !== 'https:') return false

  return ['http:', 'https:'].includes(url.protocol) && localHosts.has(url.hostname)
}

/**
 * Resolve an image reference to a value safe to place in a src attribute, or
 * the placeholder when it is not.
 *
 * Accepts: site-relative paths ("/..."), same-origin/local http(s) URLs that
 * do not downgrade an HTTPS page, and data: URLs for known image MIME types.
 * Rejects: backslash paths ("/\host", "\\host"), protocol-relative "//host",
 * control characters / obfuscating whitespace, mixed-content HTTP images,
 * javascript:/data:text/... and every other off-site scheme.
 * Mirrors the server-side Product::safeImageUrl() / App\Support\SafeUrl rules.
 */
export function imageOrPlaceholder(image, placeholder = PLACEHOLDER_IMAGE) {
  const value = String(image ?? '').trim()

  if (!value || hasControlOrWhitespace(value)) return placeholder

  // Any backslash is treated as hostile: browsers fold "\" to "/", so "/\host"
  // and "\host" would otherwise resolve off-site.
  if (value.includes(BACKSLASH)) return placeholder

  // Protocol-relative URL -> off-site.
  if (value.startsWith('//')) return placeholder

  // Site-relative path.
  if (value.startsWith('/')) return value

  // Allow-listed image data URLs only (blocks data:text/html and friends).
  if (ALLOWED_DATA_IMAGE_PATTERN.test(value)) return value

  if (value.startsWith('blob:')) return value

  // A bare path with no scheme (e.g. "img/a.png").
  if (!PROTOCOL_PATTERN.test(value)) return value

  try {
    const url = new URL(value, window.location.origin)

    if (!['http:', 'https:'].includes(url.protocol)) return placeholder

    return url.origin === window.location.origin || isLocalHttpUrl(url) ? url.href : placeholder
  } catch {
    return placeholder
  }
}
