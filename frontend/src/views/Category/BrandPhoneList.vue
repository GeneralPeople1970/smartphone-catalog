<template>
  <div class="page-content">
    <div class="container py-5 bg-white border rounded-lg">
      <div class="mx-4">
        <div class="brand-header">
          <h5>{{ brandTitle }}</h5>
          <div class="brand-search">
            <input
              v-model="searchKeyword"
              type="search"
              class="form-control"
              :placeholder="`仅搜索${brandDisplayName}型号`"
              :aria-label="`搜索${brandDisplayName}型号`"
            />
          </div>
        </div>

        <div class="main-content mt-3">
          <div class="content">
            <div v-if="loading" class="text-center py-5 text-muted">{{ loadingText }}</div>
            <div v-else-if="errorMessage" class="alert alert-warning" role="alert">
              {{ errorMessage }}
            </div>
            <div v-else-if="searchActive && !phones.length" class="empty-state">
              没有找到该品牌下的相关型号。
            </div>
            <div v-else class="phone-list">
              <div
                v-for="phone in phones"
                :key="phone.id"
                class="phone-card"
                @click="goToPhoneDetail(phone)"
              >
                <div class="phone-image">
                  <img
                    :src="imageOrPlaceholder(phone.imgurl)"
                    :alt="phone.phonename"
                    @error="handleImageError"
                  />
                </div>
                <div class="phone-info">
                  <h3>{{ phone.phonename }}</h3>
                  <p>处理器：{{ phone.socname || '待补充' }}</p>
                  <p>价格：{{ formatPrice(phone) }}</p>
                  <p>电池容量：{{ formatBattery(phone.battery) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { getPhonesByBrand, searchPhonesByBrand } from '@/services/phoneApi.js'
import { getBrandByRouteName } from '@/constants/brands.js'
import { slugify } from '@/utils/slugify.js'
import {
  PLACEHOLDER_IMAGE,
  imageOrPlaceholder as resolveImageOrPlaceholder,
} from '@/utils/image.js'

export default {
  name: 'BrandPhoneList',
  data() {
    return {
      allPhones: [],
      phones: [],
      searchKeyword: '',
      loading: false,
      errorMessage: '',
      searchTimer: null,
      searchRequestId: 0,
      syncingSearchKeyword: false,
      placeholderImage: PLACEHOLDER_IMAGE,
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
    loadingText() {
      return this.searchActive ? '正在搜索该品牌型号...' : '正在加载手机数据...'
    },
  },
  watch: {
    '$route.name': {
      handler: 'handleBrandChange',
      immediate: true,
    },
    searchKeyword() {
      if (this.syncingSearchKeyword) return
      this.queueBrandSearch()
    },
  },
  beforeUnmount() {
    window.clearTimeout(this.searchTimer)
  },
  methods: {
    handleBrandChange() {
      window.clearTimeout(this.searchTimer)
      this.syncingSearchKeyword = true
      this.searchKeyword = ''
      this.$nextTick(() => {
        this.syncingSearchKeyword = false
      })
      this.fetchPhones()
    },
    async fetchPhones() {
      const requestId = this.searchRequestId + 1
      this.searchRequestId = requestId
      this.loading = true
      this.errorMessage = ''

      try {
        const phones = await getPhonesByBrand(this.brandCode)
        if (requestId === this.searchRequestId) {
          this.allPhones = phones
          this.phones = phones
        }
      } catch (error) {
        if (requestId === this.searchRequestId) {
          console.error(error)
          this.allPhones = []
          this.phones = []
          this.errorMessage = '手机数据加载失败，请稍后重试。'
        }
      } finally {
        if (requestId === this.searchRequestId) {
          this.loading = false
        }
      }
    },
    queueBrandSearch() {
      window.clearTimeout(this.searchTimer)
      this.searchTimer = window.setTimeout(() => {
        this.runBrandSearch()
      }, 250)
    },
    async runBrandSearch() {
      const q = this.searchKeyword.trim()
      const requestId = this.searchRequestId + 1
      this.searchRequestId = requestId
      this.errorMessage = ''

      if (!q) {
        this.phones = this.allPhones
        this.loading = false
        return
      }

      this.loading = true
      try {
        const phones = await searchPhonesByBrand(this.brandCode, q, { limit: 500 })
        if (requestId === this.searchRequestId) {
          this.phones = phones
        }
      } catch (error) {
        if (requestId === this.searchRequestId) {
          console.error(error)
          this.phones = []
          this.errorMessage = '品牌内搜索失败，请稍后重试。'
        }
      } finally {
        if (requestId === this.searchRequestId) {
          this.loading = false
        }
      }
    },
    formatPrice(phone) {
      if (phone.displayPrice) return phone.displayPrice
      return Number(phone.price) > 0 ? `￥${phone.price}` : '暂无价格'
    },
    formatBattery(battery) {
      return Number(battery) > 0 ? `${battery} mAh` : '待补充'
    },
    imageOrPlaceholder(image) {
      return resolveImageOrPlaceholder(image, this.placeholderImage)
    },
    handleImageError(event) {
      if (event?.target?.src && !event.target.src.endsWith(this.placeholderImage)) {
        event.target.src = this.placeholderImage
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
  },
}
</script>

<style scoped>
.page-content > .container {
  width: min(1440px, calc(100% - 32px)) !important;
  max-width: 1440px !important;
  padding-right: 15px !important;
  padding-left: 15px !important;
}

.brand-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 18px;
}

.brand-header h5 {
  margin: 0;
  color: var(--text-main);
  font-size: 1.2rem;
  font-weight: 650;
}

.brand-search {
  width: min(100%, 420px);
}

.brand-search .form-control {
  min-height: 42px;
}

.phone-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 2rem;
}

.phone-card {
  background-color: var(--surface-bg);
  border: 1px solid var(--border-soft);
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s;
  cursor: pointer;
}

.phone-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.phone-image {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 300px;
  width: 100%;
  padding: 18px;
  background-color: var(--surface-muted);
}

.phone-image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.phone-info {
  padding: 1rem;
}

.phone-info h3 {
  margin: 0 0 0.5rem 0;
  color: var(--text-main);
}

.phone-info p {
  margin: 0.3rem 0;
  color: var(--text-muted);
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
  .brand-header {
    align-items: stretch;
    flex-direction: column;
  }

  .brand-search {
    width: 100%;
  }
}
</style>
