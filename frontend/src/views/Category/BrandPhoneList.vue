<template>
  <section class="page-content">
    <div class="container py-4">
      <div class="brand-header">
        <h1>{{ brandTitle }}</h1>
        <input
          v-model="searchKeyword"
          type="search"
          class="form-control brand-search"
          :placeholder="`仅搜索${brandDisplayName}型号`"
          :aria-label="`搜索${brandDisplayName}型号`"
        />
      </div>
      <div v-if="loading && !phones.length" class="text-center py-5 text-muted">
        {{ loadingText }}
      </div>
      <div v-if="errorMessage" class="alert alert-warning" role="alert">{{ errorMessage }}</div>
      <div v-if="!loading && !errorMessage && !phones.length" class="empty-state">
        {{ searchActive ? '没有找到该品牌下的相关型号。' : '暂无机型。' }}
      </div>
      <div class="phone-list">
        <PhoneCard v-for="phone in phones" :key="phone.id" :phone="phone" variant="brand" />
      </div>
      <div v-if="hasMore || errorMessage" class="text-center mt-4">
        <button class="btn btn-outline-dark" type="button" :disabled="loading" @click="loadMore">
          <i
            class="bi"
            :class="errorMessage ? 'bi-arrow-clockwise' : 'bi-plus-lg'"
            aria-hidden="true"
          ></i>
          {{ loading ? '正在加载...' : errorMessage ? '重试' : '加载更多' }}
        </button>
      </div>
    </div>
  </section>
</template>

<script>
import { getPhonesByBrand, searchPhonesByBrand } from '@/services/phoneApi.js'
import { getBrandByRouteName } from '@/constants/brands.js'
import PhoneCard from '@/components/PhoneCard.vue'
import { requestError } from '@/utils/phone.js'
import { createLatestRequest } from '../../../../resources/js/latest-request.js'

const pageState = () => ({ data: [], cursor: null, hasMore: false, loading: false, error: '' })

export default {
  name: 'BrandPhoneList',
  components: { PhoneCard },
  data() {
    return {
      list: pageState(),
      search: pageState(),
      searchKeyword: '',
      activeSearchKeyword: null,
      searchTimer: null,
      syncingSearchKeyword: false,
      listRequest: createLatestRequest(),
      searchRequest: createLatestRequest(),
    }
  },
  computed: {
    brandInfo() {
      return getBrandByRouteName(this.$route.name)
    },
    brandDisplayName() {
      return this.brandInfo.displayName
    },
    brandCode() {
      return this.brandInfo.code
    },
    brandTitle() {
      return this.brandCode === 'APPLE' ? this.brandDisplayName : `${this.brandDisplayName}手机`
    },
    searchActive() {
      return Boolean(this.searchKeyword.trim())
    },
    currentPage() {
      return this.searchActive ? this.search : this.list
    },
    phones() {
      return this.currentPage.data
    },
    loading() {
      return this.currentPage.loading
    },
    errorMessage() {
      return this.currentPage.error
    },
    hasMore() {
      return this.currentPage.hasMore
    },
    loadingText() {
      return this.searchActive ? '正在搜索该品牌型号...' : '正在加载手机数据...'
    },
  },
  watch: {
    '$route.name': { handler: 'handleBrandChange', immediate: true },
    '$route.query.q': 'searchFromRoute',
    searchKeyword(value) {
      if (!this.syncingSearchKeyword) this.updateRouteQuery(value)
    },
  },
  beforeUnmount() {
    window.clearTimeout(this.searchTimer)
    this.listRequest.cancel()
    this.searchRequest.cancel()
  },
  methods: {
    handleBrandChange() {
      window.clearTimeout(this.searchTimer)
      this.listRequest.cancel()
      this.searchRequest.cancel()
      this.list = pageState()
      this.search = pageState()
      this.activeSearchKeyword = null
      this.searchFromRoute(this.$route.query.q)
      this.fetchPhones()
    },
    searchFromRoute(value) {
      const keyword = String(value || '')
      if (this.searchKeyword !== keyword) {
        this.syncingSearchKeyword = true
        this.searchKeyword = keyword
        this.$nextTick(() => {
          this.syncingSearchKeyword = false
        })
      }
      this.queueBrandSearch()
    },
    updateRouteQuery(value) {
      this.queueBrandSearch()
      const q = String(value || '').trim()
      if (String(this.$route.query.q || '') === q) return
      const query = { ...this.$route.query }
      if (q) query.q = q
      else delete query.q
      this.$router.replace({ name: this.$route.name, query })
    },
    queueBrandSearch() {
      window.clearTimeout(this.searchTimer)
      if (this.activeSearchKeyword === this.searchKeyword.trim()) return
      this.searchRequest.cancel()
      this.search = pageState()
      this.activeSearchKeyword = null
      if (!this.searchActive) return
      this.search.loading = true
      this.searchTimer = window.setTimeout(() => this.runBrandSearch(), 250)
    },
    fetchPhones(append = false) {
      return this.fetchPage('list', append)
    },
    runBrandSearch(append = false) {
      window.clearTimeout(this.searchTimer)
      const keyword = this.searchKeyword.trim()
      if (!keyword) {
        this.searchRequest.cancel()
        this.search = pageState()
        this.activeSearchKeyword = null
        return
      }
      if (this.activeSearchKeyword !== keyword) append = false
      this.activeSearchKeyword = keyword
      return this.fetchPage('search', append)
    },
    loadMore() {
      if (this.loading) return
      const append = Boolean(this.currentPage.cursor)
      return this.searchActive ? this.runBrandSearch(append) : this.fetchPhones(append)
    },
    async fetchPage(kind, append) {
      if (append && (this[kind].loading || !this[kind].hasMore)) return
      if (!append) this[kind] = pageState()
      const state = this[kind]
      const request = this[`${kind}Request`].start()
      const options = { cursor: append ? state.cursor : undefined, signal: request.signal }
      state.loading = true
      state.error = ''
      try {
        const page =
          kind === 'list'
            ? await getPhonesByBrand(this.brandCode, options)
            : await searchPhonesByBrand(this.brandCode, this.activeSearchKeyword, options)
        if (!request.current()) return
        state.data = append ? [...state.data, ...page.data] : page.data
        state.cursor = page.meta.nextCursor
        state.hasMore = page.meta.hasMore
      } catch (error) {
        if (request.current() && error?.name !== 'AbortError') {
          state.error = requestError(
            error,
            kind === 'list' ? '手机数据加载失败，请稍后重试。' : '品牌内搜索失败，请稍后重试。',
          )
        }
      } finally {
        if (request.current()) state.loading = false
      }
    },
  },
}
</script>

<style scoped>
.container {
  width: min(1440px, calc(100% - 32px));
  max-width: 1440px;
}
.brand-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 24px;
}
.brand-header h1 {
  margin: 0;
  color: var(--text-main);
  font-size: 1.4rem;
  overflow-wrap: anywhere;
}
.brand-search {
  width: min(100%, 420px);
  min-height: 42px;
}
.phone-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
  gap: 24px;
}
.empty-state {
  padding: 42px 20px;
  text-align: center;
  color: var(--text-muted);
}
@media (max-width: 575.98px) {
  .brand-header {
    align-items: stretch;
    flex-direction: column;
  }
  .brand-search {
    width: 100%;
  }
}
</style>
