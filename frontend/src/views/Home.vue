<template>
  <div class="home-page">
    <h1 class="visually-hidden">智能手机参数站</h1>
    <div class="container">
      <HomepageCarousel :images="carouselImages" />
      <div v-if="slidesError" class="alert alert-warning mt-4" role="alert">{{ slidesError }}</div>
    </div>

    <section id="home-search" class="home-search-section">
      <div class="container">
        <form class="search-form" @submit.prevent="submitSearch">
          <input
            v-model="keyword"
            type="search"
            class="form-control"
            placeholder="输入手机型号、处理器或品牌"
            aria-label="搜索手机"
          />
        </form>
        <div v-if="loading" class="text-center py-5 text-muted">正在搜索...</div>
        <div v-else-if="errorMessage" class="alert alert-warning mt-4" role="alert">
          {{ errorMessage }}
        </div>
        <div v-else-if="searched && !results.length" class="empty-state">没有找到相关手机。</div>
        <div v-else-if="results.length" class="search-results-grid mt-4">
          <PhoneCard v-for="phone in results" :key="phone.id" :phone="phone" variant="search" />
        </div>
      </div>
    </section>

    <template v-if="!searchActive">
      <section
        v-if="featuredLoading || featuredError || homepageFeaturedPhones.length"
        class="featured-phones hot-phones"
      >
        <div class="container">
          <h2 class="section-heading">热门机型</h2>
          <p v-if="featuredLoading" class="text-center text-muted">正在加载...</p>
          <div v-else-if="featuredError" class="alert alert-warning" role="alert">
            {{ featuredError }}
          </div>
          <div v-else class="featured-grid">
            <PhoneCard
              v-for="phone in homepageFeaturedPhones"
              :key="phone.id"
              :phone="phone"
              recommended
            />
          </div>
        </div>
      </section>
      <section class="featured-phones recent-phones">
        <div class="container">
          <h2 class="section-heading">近期推出</h2>
          <p v-if="recentLoading" class="text-center text-muted">正在加载近期机型...</p>
          <div v-else-if="recentError" class="alert alert-warning" role="alert">
            {{ recentError }}
          </div>
          <div v-else class="featured-grid">
            <PhoneCard v-for="phone in recentPhones" :key="phone.id" :phone="phone" />
          </div>
          <div class="text-center mt-4">
            <router-link to="/category" class="btn btn-outline-dark">查看所有品牌</router-link>
          </div>
        </div>
      </section>
      <section class="brands-section">
        <div class="container">
          <h2 class="section-heading">热门品牌</h2>
          <p v-if="brandsLoading" class="text-center text-muted">正在加载...</p>
          <div v-else-if="brandsError" class="alert alert-warning" role="alert">
            {{ brandsError }}
          </div>
          <div v-else class="row text-center brand-logos">
            <div
              v-for="brand in popularBrands"
              :key="brand.code || brand.name"
              class="col-6 col-md-3"
            >
              <router-link :to="brand.path" class="brand-link">
                <img
                  :src="brand.logo"
                  :alt="brand.displayName"
                  class="brand-logo-img"
                  loading="lazy"
                  decoding="async"
                />
                <p class="mt-2">{{ brand.displayName }}</p>
              </router-link>
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script>
import {
  getBrands,
  getFeaturedPhones,
  getHomepageFeaturedPhones,
  getHomepageSlides,
  searchPhones,
} from '@/services/phoneApi.js'
import PhoneCard from '@/components/PhoneCard.vue'
import HomepageCarousel from '@/components/HomepageCarousel.vue'
import { requestError } from '@/utils/phone.js'
import { createLatestRequest } from '../../../resources/js/latest-request.js'

export default {
  name: 'HomePage',
  components: { PhoneCard, HomepageCarousel },
  data() {
    return {
      homepageFeaturedPhones: [],
      recentPhones: [],
      popularBrands: [],
      carouselImages: [],
      featuredLoading: false,
      recentLoading: false,
      brandsLoading: false,
      featuredError: '',
      recentError: '',
      brandsError: '',
      slidesError: '',
      keyword: '',
      results: [],
      loading: false,
      searched: false,
      errorMessage: '',
      searchTimer: null,
      syncingFromRoute: false,
      activeSearchKeyword: '',
      searchRequest: createLatestRequest(),
      homeRequest: createLatestRequest(),
      slidesRequest: createLatestRequest(),
    }
  },
  computed: {
    searchActive() {
      return Boolean(this.keyword.trim())
    },
  },
  watch: {
    '$route.query.q': { handler: 'searchFromRoute', immediate: true },
    keyword(value) {
      if (!this.syncingFromRoute) this.updateRouteQuery(value)
    },
  },
  mounted() {
    this.fetchHomeData()
    this.fetchHomepageSlides()
  },
  beforeUnmount() {
    window.clearTimeout(this.searchTimer)
    this.searchRequest.cancel()
    this.homeRequest.cancel()
    this.slidesRequest.cancel()
  },
  methods: {
    submitSearch() {
      this.updateRouteQuery(this.keyword)
      window.clearTimeout(this.searchTimer)
      this.runSearch(this.keyword)
    },
    updateRouteQuery(value) {
      const q = String(value || '').trim()
      if (String(this.$route.query.q || '') === q) return this.queueSearch(q)
      this.$router.replace({
        name: 'Home',
        query: q ? { q } : {},
        hash: this.$route.hash === '#home-search' ? '#home-search' : '',
      })
    },
    searchFromRoute(q) {
      const next = String(q || '')
      if (this.keyword !== next) {
        this.syncingFromRoute = true
        this.keyword = next
        this.$nextTick(() => {
          this.syncingFromRoute = false
        })
      }
      this.queueSearch(next)
    },
    queueSearch(keyword) {
      window.clearTimeout(this.searchTimer)
      const q = String(keyword || '').trim()
      if (this.activeSearchKeyword === q) return
      this.searchRequest.cancel()
      this.activeSearchKeyword = null
      this.loading = Boolean(q)
      this.results = []
      this.errorMessage = ''
      this.searched = false
      if (!q) {
        this.activeSearchKeyword = ''
        return
      }
      this.searchTimer = window.setTimeout(() => this.runSearch(q), 250)
    },
    async runSearch(keyword) {
      window.clearTimeout(this.searchTimer)
      const q = String(keyword || '').trim()
      const request = this.searchRequest.start()
      this.activeSearchKeyword = q
      this.errorMessage = ''
      if (!q) {
        this.results = []
        this.loading = false
        this.searched = false
        return
      }
      this.loading = true
      this.searched = true
      try {
        const results = await searchPhones(q, { limit: 50, signal: request.signal })
        if (request.current()) this.results = results
      } catch (error) {
        if (request.current() && error?.name !== 'AbortError') {
          this.results = []
          this.errorMessage = requestError(error, '搜索失败，请稍后重试。')
        }
      } finally {
        if (request.current()) this.loading = false
      }
    },
    async fetchHomeData() {
      const request = this.homeRequest.start()
      const load = async (
        fetcher,
        dataKey,
        loadingKey,
        errorKey,
        message,
        transform = (data) => data,
      ) => {
        this[loadingKey] = true
        this[errorKey] = ''
        try {
          const data = await fetcher({ signal: request.signal })
          if (request.current()) this[dataKey] = transform(data)
        } catch (error) {
          if (request.current() && error?.name !== 'AbortError')
            this[errorKey] = requestError(error, message)
        } finally {
          if (request.current()) this[loadingKey] = false
        }
      }
      await Promise.all([
        load(
          getHomepageFeaturedPhones,
          'homepageFeaturedPhones',
          'featuredLoading',
          'featuredError',
          '热门机型加载失败。',
        ),
        load(
          getFeaturedPhones,
          'recentPhones',
          'recentLoading',
          'recentError',
          '近期机型加载失败。',
        ),
        load(
          getBrands,
          'popularBrands',
          'brandsLoading',
          'brandsError',
          '品牌加载失败。',
          (brands) =>
            [...brands]
              .sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0))
              .slice(0, 8)
              .map((brand) => ({ ...brand, displayName: brand.displayName || brand.name })),
        ),
      ])
    },
    async fetchHomepageSlides() {
      const request = this.slidesRequest.start()
      this.slidesError = ''
      try {
        const images = await getHomepageSlides({ signal: request.signal })
        if (request.current()) this.carouselImages = images
      } catch (error) {
        if (request.current() && error?.name !== 'AbortError')
          this.slidesError = requestError(error, '轮播图加载失败。')
      }
    },
  },
}
</script>

<style scoped>
.home-page {
  color: var(--text-main);
  background: var(--surface-bg);
}
.container {
  width: min(1440px, calc(100% - 32px));
  max-width: 1440px;
}
.home-search-section,
.featured-phones,
.brands-section {
  padding: 24px 0;
}
.search-form .form-control {
  min-height: 52px;
  border-width: 2px;
  background: var(--surface-muted);
  font-size: 1rem;
  padding: 12px 16px;
}
.search-results-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
  gap: 20px;
}
.featured-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 24px;
}
.section-heading {
  text-align: center;
  font-size: 1.6rem;
  margin: 0 0 24px;
}
.empty-state {
  padding: 42px 20px;
  color: var(--text-muted);
  text-align: center;
}
.brand-logos {
  row-gap: 24px;
}
.brand-link {
  display: block;
  padding: 15px;
  text-decoration: none;
  color: var(--text-main);
}
.brand-logo-img {
  max-height: 80px;
  max-width: 100%;
  width: auto;
  object-fit: contain;
}
@media (max-width: 767.98px) {
  .featured-grid {
    grid-template-columns: 1fr;
  }
}
@media (min-width: 768px) and (max-width: 991.98px) {
  .featured-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
