import { createRouter, createWebHistory } from 'vue-router'
import Home from '../views/Home.vue'

// Home is the landing route and stays in the initial bundle. The heavier
// secondary routes are lazy-loaded so they no longer inflate first-screen JS;
// all brand routes reuse the single BrandPhoneList chunk.
const PhoneDetail = () => import('../views/PhoneDetail.vue')
const Category = () => import('../views/Category.vue')
const BrandPhoneList = () => import('../views/Category/BrandPhoneList.vue')

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
    redirect: (to) => ({
      name: 'Home',
      query: to.query,
    }),
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
    if (to.hash) {
      return {
        el: to.hash,
        top: 16,
        behavior: 'smooth',
      }
    }

    return savedPosition || { top: 0 }
  },
})

export default router
