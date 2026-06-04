import { API_BASE_URL } from '@/config/api.config.js'

const BRAND_FIELDS = 'name,code,displayName,logo,path,sort'
const PHONE_LIST_FIELDS = 'id,phonename,company,companyCode,socname,price,battery,imgurl,saledate'
const FEATURED_PHONE_FIELDS =
  'id,phonename,company,companyCode,socname,price,displayPrice,battery,feature,imgurl,brandLogo,slug'
const HOMEPAGE_FEATURED_PHONE_FIELDS =
  'id,phonename,company,companyCode,socname,price,displayPrice,battery,imgurl,feature,slug,brandLogo,recommendTitle,recommendDescription,sortOrder'
const HOMEPAGE_SLIDE_FIELDS = 'id,title,image,linkUrl,sortOrder'
const SEARCH_PHONE_FIELDS =
  'id,phonename,company,companyCode,socname,price,displayPrice,battery,imgurl,slug,saledate'
const PHONE_DETAIL_FIELDS = [
  'id',
  'phonename',
  'company',
  'companyCode',
  'socname',
  'price',
  'battery',
  'imgurl',
  'screenm',
  'charge',
  'storeage',
  'weight',
  'feature',
  'saledate',
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
  'remark',
  'updateTime',
  'saletime',
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

async function requestJson(path) {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
    },
  })

  if (!response.ok) {
    throw new Error(`API request failed: ${response.status} ${response.statusText}`)
  }

  return response.json()
}

export function getBrands() {
  return requestJson(
    withQuery('/brands', {
      fields: BRAND_FIELDS,
    }),
  )
}

export function getPhonesByBrand(brand) {
  return requestJson(
    withQuery('/phones', {
      brand,
      fields: PHONE_LIST_FIELDS,
    }),
  )
}

export function getPhoneById(id) {
  return requestJson(
    withQuery(`/phones/${encodeURIComponent(id)}`, {
      fields: PHONE_DETAIL_FIELDS,
    }),
  )
}

export function getPhoneDetail(brand, slug) {
  return requestJson(
    withQuery('/phones/detail', {
      brand,
      slug,
      fields: PHONE_DETAIL_FIELDS,
    }),
  )
}

export function getFeaturedPhones() {
  return requestJson(
    withQuery('/phones', {
      fields: FEATURED_PHONE_FIELDS,
      limit: 6,
    }),
  )
}

export function getHomepageFeaturedPhones() {
  return requestJson(
    withQuery('/homepage-featured-phones', {
      fields: HOMEPAGE_FEATURED_PHONE_FIELDS,
    }),
  )
}

export function getHomepageSlides() {
  return requestJson(
    withQuery('/homepage-slides', {
      fields: HOMEPAGE_SLIDE_FIELDS,
    }),
  )
}

export function searchPhones(keyword, options = {}) {
  return requestJson(
    withQuery('/search', {
      q: keyword,
      brand: options.brand,
      fields: options.fields || SEARCH_PHONE_FIELDS,
      limit: options.limit || 20,
    }),
  )
}

export function searchPhonesByBrand(brand, keyword, options = {}) {
  return requestJson(
    withQuery(`/brands/${encodeURIComponent(brand)}/search`, {
      q: keyword,
      fields: options.fields || SEARCH_PHONE_FIELDS,
      limit: options.limit || 20,
    }),
  )
}

export function getCurrentUser() {
  return requestJson('/me')
}
