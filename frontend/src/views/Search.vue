<template>
  <div class="search-page">
    <div class="container">
      <div class="search-panel">
        <h1>搜索手机</h1>
        <form class="search-form" @submit.prevent="submitSearch">
          <input
            v-model="keyword"
            type="search"
            class="form-control"
            placeholder="输入手机型号、处理器或品牌"
            aria-label="搜索手机"
          />
          <button class="btn btn-primary" type="submit">搜索</button>
        </form>
      </div>

      <div v-if="loading" class="text-center py-5 text-muted">正在搜索...</div>
      <div v-else-if="errorMessage" class="alert alert-warning" role="alert">
        {{ errorMessage }}
      </div>
      <div v-else-if="searched && !results.length" class="empty-state">没有找到相关手机。</div>
      <div v-else-if="results.length" class="results-grid">
        <article
          v-for="phone in results"
          :key="phone.id || `${phone.companyCode}-${phone.slug || phone.phonename}`"
          class="result-card"
          @click="goToPhoneDetail(phone)"
        >
          <div class="result-image">
            <img
              :src="imageOrPlaceholder(phone.imgurl)"
              :alt="phone.phonename"
              width="300"
              height="220"
              loading="lazy"
              decoding="async"
              @error="handleImageError"
            />
          </div>
          <div class="result-info">
            <div class="brand-logo">
              <img
                v-if="getPhoneBrandLogo(phone)"
                :src="getPhoneBrandLogo(phone)"
                :alt="phone.company || phone.companyCode || '品牌'"
                loading="lazy"
                decoding="async"
              />
              <span v-else>{{ phone.company || phone.companyCode || '品牌待补充' }}</span>
            </div>
            <h2>{{ phone.phonename }}</h2>
            <dl>
              <div>
                <dt>处理器</dt>
                <dd>{{ phone.socname || '待补充' }}</dd>
              </div>
              <div>
                <dt>价格</dt>
                <dd>{{ formatPrice(phone) }}</dd>
              </div>
              <div>
                <dt>电池</dt>
                <dd>{{ formatBattery(phone.battery) }}</dd>
              </div>
            </dl>
          </div>
        </article>
      </div>
    </div>
  </div>
</template>

<script>
import { getBrands, searchPhones } from '@/services/phoneApi.js'
import { slugify } from '@/utils/slugify.js'
import {
  PLACEHOLDER_IMAGE,
  imageOrPlaceholder as resolveImageOrPlaceholder,
} from '@/utils/image.js'

export default {
  name: 'SearchPage',
  data() {
    return {
      keyword: '',
      results: [],
      loading: false,
      searched: false,
      errorMessage: '',
      brandLogoMap: {},
      searchTimer: null,
      searchRequestId: 0,
      searchController: null,
      syncingFromRoute: false,
      placeholderImage: PLACEHOLDER_IMAGE,
    }
  },
  watch: {
    '$route.query.q': {
      handler: 'searchFromRoute',
      immediate: true,
    },
    keyword(value) {
      if (this.syncingFromRoute) return
      this.updateRouteQuery(value)
    },
  },
  beforeUnmount() {
    window.clearTimeout(this.searchTimer)
    if (this.searchController) {
      this.searchController.abort()
    }
  },
  mounted() {
    this.loadBrandLogos()
  },
  methods: {
    async loadBrandLogos() {
      try {
        const brands = await getBrands()
        this.brandLogoMap = brands.reduce((map, brand) => {
          if (brand.code && brand.logo) {
            map[String(brand.code).toUpperCase()] = brand.logo
          }
          if (brand.name && brand.logo) {
            map[String(brand.name).toUpperCase()] = brand.logo
          }
          return map
        }, {})
      } catch (error) {
        console.error(error)
        this.brandLogoMap = {}
      }
    },
    submitSearch() {
      window.clearTimeout(this.searchTimer)
      this.updateRouteQuery(this.keyword, true)
      this.runSearch(this.keyword)
    },
    updateRouteQuery(value, replaceSame = false) {
      const q = String(value || '').trim()
      const currentQ = String(this.$route.query.q || '')

      if (!replaceSame && currentQ === q) {
        this.queueSearch(q)
        return
      }

      this.$router.replace({
        name: 'Search',
        query: q ? { q } : {},
      })
    },
    searchFromRoute(q) {
      const nextKeyword = String(q || '')

      if (this.keyword !== nextKeyword) {
        this.syncingFromRoute = true
        this.keyword = nextKeyword
        this.$nextTick(() => {
          this.syncingFromRoute = false
        })
      }

      this.queueSearch(nextKeyword)
    },
    queueSearch(keyword) {
      window.clearTimeout(this.searchTimer)
      this.searchTimer = window.setTimeout(() => {
        this.runSearch(keyword)
      }, 250)
    },
    async runSearch(keyword) {
      const q = String(keyword || '').trim()
      const requestId = this.searchRequestId + 1
      this.searchRequestId = requestId
      this.errorMessage = ''

      // Cancel any in-flight search so a slow earlier request can never
      // overwrite a newer one (request race).
      if (this.searchController) {
        this.searchController.abort()
      }

      if (!q) {
        this.searchController = null
        this.results = []
        this.loading = false
        this.searched = false
        return
      }

      const controller = new AbortController()
      this.searchController = controller

      this.loading = true
      this.searched = true
      try {
        const results = await searchPhones(q, { limit: 50, signal: controller.signal })
        if (requestId === this.searchRequestId) {
          this.results = Array.isArray(results) ? results : []
        }
      } catch (error) {
        if (error?.name === 'AbortError') {
          return
        }
        if (requestId === this.searchRequestId) {
          console.error(error)
          this.results = []
          this.errorMessage = '搜索失败，请稍后重试。'
        }
      } finally {
        if (requestId === this.searchRequestId) {
          this.loading = false
        }
      }
    },
    goToPhoneDetail(phone) {
      if (phone.id) {
        this.$router.push({
          name: 'PhoneDetailById',
          params: { id: phone.id },
        })
        return
      }

      this.$router.push({
        name: 'PhoneDetail',
        params: {
          brandName: phone.companyCode || phone.company,
          phoneNameSlug: phone.slug || slugify(phone.phonename),
        },
      })
    },
    formatPrice(phone) {
      if (phone.displayPrice) return phone.displayPrice
      return Number(phone.price) > 0 ? `￥${phone.price}` : '暂无价格'
    },
    imageOrPlaceholder(image) {
      return resolveImageOrPlaceholder(image, this.placeholderImage)
    },
    handleImageError(event) {
      if (event?.target?.src && !event.target.src.endsWith(this.placeholderImage)) {
        event.target.src = this.placeholderImage
      }
    },
    getPhoneBrandLogo(phone) {
      if (phone.brandLogo) return phone.brandLogo
      const companyCode = String(phone.companyCode || '').toUpperCase()
      const companyName = String(phone.company || '').toUpperCase()
      return this.brandLogoMap[companyCode] || this.brandLogoMap[companyName] || ''
    },
    formatBattery(battery) {
      return Number(battery) > 0 ? `${battery} mAh` : '待补充'
    },
  },
}
</script>

<style scoped>
.search-page {
  padding: 24px 0 56px;
}

.search-page .container {
  width: min(1440px, calc(100% - 32px)) !important;
  max-width: 1440px !important;
  padding-right: 15px !important;
  padding-left: 15px !important;
}

.search-panel {
  margin-bottom: 28px;
  padding: 28px;
  border: 1px solid var(--border-soft);
  border-radius: 8px;
  background-color: var(--surface-bg);
}

.search-panel h1 {
  margin: 0 0 18px;
  color: var(--text-main);
  font-size: 1.8rem;
  font-weight: 650;
}

.search-form {
  display: flex;
  gap: 12px;
}

.search-form .form-control {
  min-height: 44px;
}

.search-form .btn {
  min-width: 96px;
}

.results-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.result-card {
  display: grid;
  grid-template-rows: 220px 1fr;
  border: 1px solid var(--border-soft);
  border-radius: 8px;
  background-color: var(--surface-bg);
  overflow: hidden;
  cursor: pointer;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;
}

.result-card:hover {
  transform: translateY(-4px);
  border-color: rgba(var(--app-primary-rgb), 0.35);
  box-shadow: 0 12px 24px rgba(var(--app-primary-rgb), 0.1);
}

.result-image {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 22px;
  background-color: var(--surface-muted);
}

.result-image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.result-info {
  padding: 18px;
}

.brand-logo {
  display: flex;
  align-items: center;
  min-height: 30px;
  margin-bottom: 6px;
}

.brand-logo img {
  max-width: 92px;
  max-height: 26px;
  object-fit: contain;
}

.brand-logo span {
  color: var(--app-primary);
  font-size: 0.9rem;
  font-weight: 600;
}

.result-info h2 {
  min-height: 2.6rem;
  margin: 0 0 14px;
  color: var(--text-main);
  font-size: 1.15rem;
  font-weight: 650;
  line-height: 1.3;
}

dl {
  margin: 0;
}

dl div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 7px 0;
  border-top: 1px solid var(--border-soft);
}

dt {
  color: var(--text-muted);
  font-weight: 500;
}

dd {
  margin: 0;
  color: var(--text-main);
  font-weight: 600;
  text-align: right;
}

.empty-state {
  padding: 42px 20px;
  border: 1px solid var(--border-soft);
  border-radius: 8px;
  background-color: var(--surface-bg);
  color: var(--text-muted);
  text-align: center;
}

@media (max-width: 575.98px) {
  .search-panel {
    padding: 20px;
  }

  .search-form {
    flex-direction: column;
  }

  .search-form .btn {
    width: 100%;
  }
}
</style>
