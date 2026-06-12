export const PLACEHOLDER_IMAGE = '/assets/phone-placeholder.svg'

const PROTOCOL_PATTERN = /^[a-z][a-z\d+\-.]*:/i

function isLocalHttpUrl(url) {
  const localHosts = new Set(['localhost', '127.0.0.1', '::1', window.location.hostname])

  return ['http:', 'https:'].includes(url.protocol) && localHosts.has(url.hostname)
}

export function imageOrPlaceholder(image, placeholder = PLACEHOLDER_IMAGE) {
  const value = String(image ?? '').trim()

  if (!value) return placeholder
  if (value.startsWith('//')) return placeholder
  if (value.startsWith('/') || value.startsWith('data:image/') || value.startsWith('blob:')) {
    return value
  }

  if (!PROTOCOL_PATTERN.test(value)) {
    return value
  }

  try {
    const url = new URL(value, window.location.origin)

    return url.origin === window.location.origin || isLocalHttpUrl(url) ? url.href : placeholder
  } catch {
    return placeholder
  }
}
