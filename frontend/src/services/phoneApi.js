import { API_BASE_URL } from '@/config/api.config.js'
import { requestJson as request } from '../../../resources/js/http.js'

const BRAND_FIELDS = 'name,code,displayName,logo,path,sort,phoneCount'
const PHONE_LIST_FIELDS =
  'id,phonename,company,companyCode,socname,price,displayPrice,battery,imgurl,slug,brandLogo'
const FEATURED_PHONE_FIELDS = `${PHONE_LIST_FIELDS},feature`
const HOMEPAGE_FEATURED_PHONE_FIELDS = `${FEATURED_PHONE_FIELDS},recommendTitle,recommendDescription,sortOrder`
const HOMEPAGE_SLIDE_FIELDS = 'id,title,image,linkUrl,sortOrder'
const PHONE_DETAIL_FIELDS = [
  'id',
  'phonename',
  'company',
  'companyCode',
  'brandLogo',
  'socname',
  'price',
  'displayPrice',
  'battery',
  'imgurl',
  'screenm',
  'charge',
  'storeage',
  'weight',
  'feature',
  'official',
  'cpu',
  'gpu',
  'ramfadsf',
  'romagbcz',
  'wifi',
  'bluetooth',
  'screencolor',
  'location',
  'osui',
  'material',
  'sensor',
].join(',')

function withQuery(path, params) {
  const searchParams = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      searchParams.set(key, value)
    }
  })

  const query = searchParams.toString()
  return query ? `${path}?${query}` : path
}

function requestJson(path, options = {}) {
  return request(`${API_BASE_URL}${path}`, options)
}

export function getBrands(options = {}) {
  return requestJson(
    withQuery('/brands', {
      fields: BRAND_FIELDS,
    }),
    options,
  )
}

export function getPhonesByBrand(brand, options = {}) {
  return requestJson(
    withQuery('/phones', {
      brand,
      fields: PHONE_LIST_FIELDS,
      paginate: 'cursor',
      limit: 24,
      cursor: options.cursor,
    }),
    options,
  )
}

export function getPhoneById(id, options = {}) {
  return requestJson(
    withQuery(`/phones/${encodeURIComponent(id)}`, {
      fields: PHONE_DETAIL_FIELDS,
    }),
    options,
  )
}

export function getPhoneDetail(brand, slug, options = {}) {
  return requestJson(
    withQuery('/phones/detail', {
      brand,
      slug,
      fields: PHONE_DETAIL_FIELDS,
    }),
    options,
  )
}

export function getFeaturedPhones(options = {}) {
  return requestJson(
    withQuery('/phones', {
      fields: FEATURED_PHONE_FIELDS,
      limit: 6,
    }),
    options,
  )
}

export function getHomepageFeaturedPhones(options = {}) {
  return requestJson(
    withQuery('/homepage-featured-phones', {
      fields: HOMEPAGE_FEATURED_PHONE_FIELDS,
    }),
    options,
  )
}

export function getHomepageSlides(options = {}) {
  return requestJson(
    withQuery('/homepage-slides', {
      fields: HOMEPAGE_SLIDE_FIELDS,
    }),
    options,
  )
}

export function searchPhones(keyword, options = {}) {
  return requestJson(
    withQuery('/search', {
      q: keyword,
      brand: options.brand,
      fields: options.fields || PHONE_LIST_FIELDS,
      limit: options.limit || 20,
    }),
    { signal: options.signal },
  )
}

export function searchPhonesByBrand(brand, keyword, options = {}) {
  return requestJson(
    withQuery(`/brands/${encodeURIComponent(brand)}/search`, {
      q: keyword,
      fields: options.fields || PHONE_LIST_FIELDS,
      limit: 24,
      paginate: 'cursor',
      cursor: options.cursor,
    }),
    { signal: options.signal },
  )
}

export function getCurrentUser() {
  return requestJson('/me')
}
