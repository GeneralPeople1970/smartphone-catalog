import { createRouter, createWebHistory } from 'vue-router'
import Home from '../views/Home.vue'
import PhoneDetail from '../views/PhoneDetail.vue'
import Category from '../views/Category.vue'
import Search from '../views/Search.vue'
import BrandPhoneList from '../views/Category/BrandPhoneList.vue'

const brandRoutes = [
  'HUAWEI',
  'APPLE',
  'OPPO',
  'SAMSUNG',
  'REALME',
  'HONOR',
  'MEIZU',
  'XIAOMI',
  'VIVO',
  'ONEPLUS',
  'NUBIA',
  'LENOVO',
  'LIANXIANG',
  'LENOVO_XIAOXIN',
  'SONY',
  'ZTE',
  'ASUS',
  'GOOGLE',
  'LG',
  'NOKIA',
  'MOTOROLA',
  'REDMI',
].map((brand) => ({
  path: `/${brand}`,
  name: `${brand}List`,
  component: BrandPhoneList,
}))

const routes = [
  {
    path: '/',
    name: 'Home',
    component: Home,
  },
  {
    path: '/search',
    name: 'Search',
    component: Search,
  },
  {
    path: '/category',
    name: 'Category',
    component: Category,
  },
  ...brandRoutes,
  {
    path: '/phone/:id',
    name: 'PhoneDetailById',
    component: PhoneDetail,
    props: true,
  },
  {
    path: '/:brandName/:phoneNameSlug',
    name: 'PhoneDetail',
    component: PhoneDetail,
    props: true,
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { top: 0 }
  },
})

export default router
