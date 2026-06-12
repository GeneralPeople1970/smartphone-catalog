export const BRAND_ROUTE_MAP = {
  APPLE: { code: 'APPLE', displayName: '苹果' },
  HUAWEI: { code: 'HUAWEI', displayName: '华为' },
  XIAOMI: { code: 'XIAOMI', displayName: '小米' },
  SAMSUNG: { code: 'SAMSUNG', displayName: '三星' },
  OPPO: { code: 'OPPO', displayName: 'OPPO' },
  MEIZU: { code: 'MEIZU', displayName: '魅族' },
  REALME: { code: 'REALME', displayName: '真我' },
  HONOR: { code: 'HONOR', displayName: '荣耀' },
  NUBIA: { code: 'NUBIA', displayName: '努比亚' },
  ONEPLUS: { code: 'ONEPLUS', displayName: '一加' },
  VIVO: { code: 'VIVO', displayName: 'vivo' },
  LENOVO: { code: 'LENOVO', displayName: '联想' },
  LIANXIANG: { code: 'LENOVO', displayName: '联想' },
  LENOVO_XIAOXIN: { code: 'LENOVO', displayName: '联想' },
  SONY: { code: 'SONY', displayName: '索尼' },
  ZTE: { code: 'ZTE', displayName: '中兴' },
  ASUS: { code: 'ASUS', displayName: '华硕' },
  GOOGLE: { code: 'GOOGLE', displayName: '谷歌' },
  LG: { code: 'LG', displayName: 'LG' },
  NOKIA: { code: 'NOKIA', displayName: '诺基亚' },
  MOTOROLA: { code: 'MOTOROLA', displayName: '摩托罗拉' },
  REDMI: { code: 'REDMI', displayName: '红米' },
}

export function getBrandByRouteName(routeName) {
  const key = String(routeName || '')
    .replace(/List$/, '')
    .toUpperCase()
  return BRAND_ROUTE_MAP[key] || { code: key, displayName: key }
}
