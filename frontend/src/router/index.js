import { createRouter, createWebHistory } from 'vue-router'
import Home from '../views/Home.vue'
import PhoneDetail from '../views/PhoneDetail.vue'
import Category from '../views/Category.vue'
import Search from '../views/Search.vue'
import BrandPhoneList from '../views/Category/BrandPhoneList.vue'

const HUAWEIList = BrandPhoneList
const APPLEList = BrandPhoneList
const OPPOCategory = BrandPhoneList
const SAMSUNGList = BrandPhoneList
const REALMEList = BrandPhoneList
const HONORList = BrandPhoneList
const MEIZUList = BrandPhoneList
const XIAOMIList = BrandPhoneList
const VIVOList = BrandPhoneList
const ONEPLUSList = BrandPhoneList
const NUBIAList = BrandPhoneList
const LIANXIANGList = BrandPhoneList
const SONYList = BrandPhoneList
const ZTEList = BrandPhoneList
const ASUSList = BrandPhoneList
const GOOGLEList = BrandPhoneList
const LGList = BrandPhoneList
const NOKIAList = BrandPhoneList
const MOTOROLAList = BrandPhoneList
const REDMIList = BrandPhoneList

const routes = [
  {
    path: '/',
    name: 'Home',
    component: Home,
    meta: {
      title: '智能手机参数查询_最新手机规格对比与报价 - 智能手机参数站',
      description:
        '提供最全面准确的智能手机参数查询、实时规格对比、性能跑分与最新报价。涵盖主流品牌手机型号，助您快速了解手机配置，做出明智的购机决策。',
      keywords:
        '智能手机参数, 手机参数, 手机规格, 手机对比, 手机报价, 手机评测, 手机型号, 手机性能, 安卓手机, 苹果手机, 最新手机',
    },
  },
  {
    path: '/search',
    name: 'Search',
    component: Search,
    meta: {
      title: '手机搜索 - 智能手机参数站',
      description: '搜索手机型号、处理器和品牌，快速查找手机参数、价格、电池和上市时间。',
      keywords: '手机搜索, 手机型号搜索, 手机参数搜索, 手机品牌搜索',
    },
  },
  {
    path: '/category',
    name: 'Category',
    component: Category,
    meta: {
      title: '手机品牌 - 智能手机参数站',
      description:
        '浏览苹果、华为、小米、三星、OPPO、vivo等所有主流智能手机品牌分类，快速查找您感兴趣的手机型号和详细参数。',
      keywords:
        '手机品牌, 手机分类, 苹果手机, 华为手机, 小米手机, 三星手机, OPPO手机, 魅族手机, 真我手机, 荣耀手机, 努比亚手机, 一加手机, vivo手机, 联想手机, 手机大全',
    },
  }, // props: true
  //Category文件夹
  {
    path: '/HUAWEI',
    name: 'HUAWEIList',
    component: HUAWEIList,
    meta: {
      title: '华为手机参数大全_最新华为手机报价与评测 - 智能手机参数站',
      description:
        '查询最新华为智能手机（如Mate、P、nova系列）的详细参数、规格、性能和市场报价，助您全面了解华为手机。',
      keywords: '华为手机, 华为参数, 华为报价, 华为评测, 华为Mate, 华为P, 华为nova',
    },
  },
  {
    path: '/APPLE',
    name: 'APPLEList',
    component: APPLEList,
    meta: {
      title: '苹果iPhone手机参数大全_最新iPhone报价与评测 - 智能手机参数站',
      description:
        '查询最新苹果iPhone手机（如iPhone 16、iPhone 15系列）的详细参数、规格、性能和市场报价，全面掌握iOS手机信息。',
      keywords: '苹果手机, iPhone参数, iPhone报价, iPhone评测, iOS手机, iPhone 16, iPhone 15',
    },
  },
  {
    path: '/OPPO',
    name: 'OPPOList',
    component: OPPOCategory,
    meta: {
      title: 'OPPO手机参数大全_最新OPPO手机报价与评测 - 智能手机参数站',
      description:
        '查询最新OPPO智能手机（如Find、Reno系列）的详细参数、规格、性能和市场报价，助您深入了解OPPO手机。',
      keywords: 'OPPO手机, OPPO参数, OPPO报价, OPPO评测, OPPO Find, OPPO Reno',
    },
  }, // 使用 OPPOCategory
  {
    path: '/SAMSUNG',
    name: 'SAMSUNGList',
    component: SAMSUNGList,
    meta: {
      title: '三星手机参数大全_最新三星手机报价与评测 - 智能手机参数站',
      description:
        '查询最新三星智能手机（如Galaxy S、Z系列）的详细参数、规格、性能和市场报价，全面掌握三星手机信息。',
      keywords: '三星手机, 三星参数, 三星报价, 三星评测, 三星Galaxy, 三星折叠屏',
    },
  },
  {
    path: '/REALME',
    name: 'REALMEList',
    component: REALMEList,
    meta: {
      title: '真我Realme手机参数大全_最新Realme手机报价与评测 - 智能手机参数站',
      description:
        '查询最新真我Realme智能手机（如GT、数字系列）的详细参数、规格、性能和市场报价，了解真我手机特点。',
      keywords: '真我手机, Realme参数, Realme报价, Realme评测, 真我GT, 真我数字',
    },
  },
  {
    path: '/HONOR',
    name: 'HONORList',
    component: HONORList,
    meta: {
      title: '荣耀手机参数大全_最新荣耀手机报价与评测 - 智能手机参数站',
      description:
        '查询最新荣耀智能手机（如Magic、数字系列）的详细参数、规格、性能和市场报价，助您全面了解荣耀手机。',
      keywords: '荣耀手机, 荣耀参数, 荣耀报价, 荣耀评测, 荣耀Magic, 荣耀数字',
    },
  },
  {
    path: '/MEIZU',
    name: 'MEIZUList',
    component: MEIZUList,
    meta: {
      title: '魅族手机参数大全_最新魅族手机报价与评测 - 智能手机参数站',
      description: '查询最新魅族智能手机的详细参数、规格、性能和市场报价，了解魅族手机特色。',
      keywords: '魅族手机, 魅族参数, 魅族报价, 魅族评测',
    },
  },
  {
    path: '/XIAOMI',
    name: 'XIAOMIList',
    component: XIAOMIList,
    meta: {
      title: '小米手机参数大全_最新小米手机报价与评测 - 智能手机参数站',
      description:
        '查询最新小米、Redmi智能手机（如小米数字、Redmi K系列）的详细参数、规格、性能和市场报价，全面掌握小米生态。',
      keywords: '小米手机, 小米参数, 小米报价, 小米评测, Redmi手机, 小米数字, Redmi K',
    },
  },
  {
    path: '/VIVO',
    name: 'VIVOList',
    component: VIVOList,
    meta: {
      title: 'vivo手机参数大全_最新vivo手机报价与评测 - 智能手机参数站',
      description:
        '查询最新vivo、iQOO智能手机（如X、iQOO系列）的详细参数、规格、性能和市场报价，了解vivo手机影像与性能。',
      keywords: 'vivo手机, vivo参数, vivo报价, vivo评测, iQOO手机, vivo X, iQOO',
    },
  },
  {
    path: '/ONEPLUS',
    name: 'ONEPLUSList',
    component: ONEPLUSList,
    meta: {
      title: '一加手机参数大全_最新一加手机报价与评测 - 智能手机参数站',
      description:
        '查询最新一加智能手机（如数字、Ace系列）的详细参数、规格、性能和市场报价，了解一加手机性能与设计。',
      keywords: '一加手机, 一加参数, 一加报价, 一加评测, OnePlus, 一加Ace',
    },
  },
  {
    path: '/NUBIA',
    name: 'NUBIAList',
    component: NUBIAList,
    meta: {
      title: '努比亚手机参数大全_最新努比亚手机报价与评测 - 智能手机参数站',
      description:
        '查询最新努比亚、红魔手机（如Z、红魔系列）的详细参数、规格、性能和市场报价，了解努比亚电竞手机。',
      keywords: '努比亚手机, 努比亚参数, 努比亚报价, 努比亚评测, 红魔手机, 努比亚Z',
    },
  },
  {
    path: '/LIANXIANG',
    name: 'LIANXIANGList',
    component: LIANXIANGList,
    meta: {
      title: '联想小新手机参数大全_最新联想手机报价与评测 - 智能手机参数站',
      description:
        '查询最新联想小新、拯救者智能手机的详细参数、规格、性能和市场报价，了解联想手机特色。',
      keywords: '联想小新手机, 联想手机, 联想参数, 联想报价, 联想评测, 拯救者手机',
    },
  },
  {
    path: '/SONY',
    name: 'SONYList',
    component: SONYList,
    meta: {
      title: '索尼手机参数大全_最新索尼手机报价与评测 - 智能手机参数站',
      description:
        '查询最新索尼智能手机（如Xperia、Z系列）的详细参数、规格、性能和市场报价，了解索尼手机影像与性能。',
      keywords: '索尼手机, 索尼参数, 索尼报价, 索尼评测, 索尼Xperia, 索尼Z',
    },
  },
  {
    path: '/ZTE',
    name: 'ZTEList',
    component: ZTEList,
    meta: {
      title: '中兴手机参数大全_最新中兴手机报价与评测 - 智能手机参数站',
      description:
        '查询最新中兴智能手机（如A、V系列）的详细参数、规格、性能和市场报价，了解中兴手机性能。',
      keywords: '中兴手机, 中兴参数, 中兴报价, 中兴评测, 中兴A, 中兴V',
    },
  },
  {
    path: '/ASUS',
    name: 'ASUSList',
    component: ASUSList,
    meta: {
      title: '华硕手机参数大全_最新华硕手机报价与评测 - 智能手机参数站',
      description:
        '查询最新华硕智能手机（如Zen、Rog系列）的详细参数、规格、性能和市场报价，了解华硕手机性能。',
      keywords: '华硕手机, 华硕参数, 华硕报价, 华硕评测, 华硕Zen, 华硕Rog',
    },
  },
  {
    path: '/GOOGLE',
    name: 'GOOGLEList',
    component: GOOGLEList,
    meta: {
      title: '谷歌手机参数大全_最新谷歌手机报价与评测 - 智能手机参数站',
      description:
        '查询最新谷歌智能手机（如Pixel、Galaxy系列）的详细参数、规格、性能和市场报价，了解谷歌手机性能。',
      keywords: '谷歌手机, 谷歌参数, 谷歌报价, 谷歌评测, 谷歌Pixel, 谷歌Galaxy',
    },
  },
  {
    path: '/LG',
    name: 'LGList',
    component: LGList,
    meta: {
      title: 'LG手机参数大全_最新LG手机报价与评测 - 智能手机参数站',
      description:
        '查询最新LG智能手机（如V、G系列）的详细参数、规格、性能和市场报价，了解LG手机性能。',
      keywords: 'LG手机, LG参数, LG报价, LG评测, LG V, LG G',
    },
  },
  {
    path: '/NOKIA',
    name: 'NOKIAList',
    component: NOKIAList,
    meta: {
      title: '诺基亚手机参数大全_最新诺基亚手机报价与评测 - 智能手机参数站',
      description:
        '查询最新诺基亚智能手机（如8、9、10系列）的详细参数、规格、性能和市场报价，了解诺基亚手机性能。',
      keywords: '诺基亚手机, 诺基亚参数, 诺基亚报价, 诺基亚评测, 诺基亚8, 诺基亚9, 诺基亚10',
    },
  },
  {
    path: '/MOTOROLA',
    name: 'MOTOROLAList',
    component: MOTOROLAList,
    meta: {
      title: '摩托罗拉手机参数大全_最新摩托罗拉手机报价与评测 - 智能手机参数站',
      description:
        '查询最新摩托罗拉智能手机（如Moto G、Moto E系列）的详细参数、规格、性能和市场报价，了解摩托罗拉手机性能。',
      keywords:
        '摩托罗拉手机, 摩托罗拉参数, 摩托罗拉报价, 摩托罗拉评测, 摩托罗拉Moto G, 摩托罗拉Moto E',
    },
  },
  {
    path: '/REDMI',
    name: 'REDMIList',
    component: REDMIList,
    meta: {
      title: '红米手机参数大全_最新红米手机报价与评测 - 智能手机参数站',
      description:
        '查询最新红米智能手机（如Redmi K、Redmi Note系列）的详细参数、规格、性能和市场报价，了解红米手机性能。',
      keywords: '红米手机, 红米参数, 红米报价, 红米评测, 红米K, 红米Note',
    },
  },

  {
    path: '/phone/:id',
    name: 'PhoneDetailById',
    component: PhoneDetail,
    props: true,
    meta: {
      title: '手机详情页 - 智能手机参数站',
      description: '查看智能手机的详细参数、规格、性能和用户评价。',
      keywords: '手机详情, 手机参数, 手机规格, 手机评测',
    },
  },

  // Level 3 Phone Detail Page (通用手机详情页)
  // ':brandName' 将匹配品牌名 (如 'HUAWEI')
  // ':phoneNameSlug' 将匹配手机名的 slug (如 'huawei-nova-12')
  // 这个通用路由必须放在所有具体的 Level 2 品牌路由 *之后*，以确保正确的匹配优先级。
  {
    path: '/:brandName/:phoneNameSlug',
    name: 'PhoneDetail',
    component: PhoneDetail,
    props: true, // 将路由参数作为组件的props传递
    meta: {
      title: '手机详情页 - 智能手机参数站',
      description: '查看智能手机的详细参数、规格、性能和用户评价。',
      keywords: '手机详情, 手机参数, 手机规格, 手机评测',
    },
  },
]
const router = createRouter({
  history: createWebHistory(),
  routes,
  // 每次路由切换时，滚动到页面顶部，提升用户体验
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else {
      return { top: 0 }
    }
  },
})

const siteUrl = String(import.meta.env.VITE_SITE_URL || '').replace(/\/+$/, '')

function updateCanonicalUrl(path) {
  const existing = document.querySelector('link[rel="canonical"]')

  if (!siteUrl) {
    existing?.remove()
    return
  }

  const canonical = existing || document.createElement('link')
  canonical.rel = 'canonical'
  canonical.href = `${siteUrl}${path === '/' ? '/' : path}`

  if (!existing) {
    document.head.appendChild(canonical)
  }
}

// 全局导航守卫：在每次路由跳转前，根据路由的 meta 信息更新页面的 title 和 meta 标签
router.beforeEach((to, from, next) => {
  // 设置页面标题
  document.title = to.meta.title || '智能手机参数站' // 如果路由没有定义title，则使用默认标题
  // 更新 meta description
  let descriptionTag = document.querySelector('meta[name="description"]')
  if (!descriptionTag) {
    // 如果不存在，则创建
    descriptionTag = document.createElement('meta')
    descriptionTag.name = 'description'
    document.head.appendChild(descriptionTag)
  }
  descriptionTag.setAttribute(
    'content',
    to.meta.description || '提供最全面准确的智能手机参数查询、实时规格对比、性能跑分与最新报价。',
  )
  // 更新 meta keywords
  let keywordsTag = document.querySelector('meta[name="keywords"]')
  if (!keywordsTag) {
    // 如果不存在，则创建
    keywordsTag = document.createElement('meta')
    keywordsTag.name = 'keywords'
    document.head.appendChild(keywordsTag)
  }
  keywordsTag.setAttribute('content', to.meta.keywords || '智能手机参数, 手机参数, 手机规格')
  updateCanonicalUrl(to.path)
  next() // 继续导航
})
export default router
