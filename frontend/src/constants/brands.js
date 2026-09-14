import brands from '../../../resources/data/brands.json'

export const BRAND_ROUTE_MAP = Object.fromEntries(
  brands.flatMap(({ code, displayName, legacyCodes = [] }) =>
    [code, ...legacyCodes].map((route) => [route, { code, displayName }]),
  ),
)

export const BRAND_ROUTES = brands.flatMap(({ code, path, legacyCodes = [] }) => [
  { path, name: `${code}List` },
  ...legacyCodes.map((legacy) => ({ path: `/${legacy}`, name: `${legacy}List` })),
])

export function getBrandByRouteName(routeName) {
  const key = String(routeName || '')
    .replace(/List$/, '')
    .toUpperCase()
  return BRAND_ROUTE_MAP[key] || { code: key, displayName: key }
}
