import { slugify } from './slugify.js'

export function formatPrice(phone) {
  const value = String(phone?.displayPrice ?? '').trim() || String(phone?.price ?? '').trim()
  if (!value || (Number.isFinite(Number(value)) && Number(value) <= 0)) return '暂无价格'
  return Number.isFinite(Number(value)) ? `￥${value}` : value
}

export function formatBattery(value) {
  return Number(value) > 0 ? `${value} mAh` : '待补充'
}

export function phoneRoute(phone) {
  return phone.id
    ? { name: 'PhoneDetailById', params: { id: phone.id } }
    : {
        name: 'PhoneDetail',
        params: {
          brandName: phone.companyCode || phone.company,
          phoneNameSlug: phone.slug || slugify(phone.phonename),
        },
      }
}

export function requestError(error, fallback) {
  return error?.status === 429 ? '请求频繁，请稍后重试。' : fallback
}
