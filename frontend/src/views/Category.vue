<template>
  <div class="category">
    <div class="container">
      <h1 class="text-center mb-5 section-title">手机品牌</h1>
      <div v-if="loading" class="text-center py-5 text-muted">正在加载品牌数据...</div>
      <div v-else-if="errorMessage" class="alert alert-warning" role="alert">
        {{ errorMessage }}
      </div>
      <div v-else class="row">
        <div
          v-for="brand in brands"
          :key="brand.code || brand.name"
          class="col-6 col-md-4 col-lg-3 my-4"
        >
          <router-link :to="brand.path" class="brand-link">
            <div class="brand-card">
              <img
                :src="brand.logo"
                :alt="`${brand.displayName || brand.name}手机`"
                class="img-fluid"
                @error="handleLogoError"
              />
              <h4>{{ brand.displayName || brand.name }}</h4>
              <p>{{ brand.code }} · {{ brand.phoneCount || 0 }} 台</p>
            </div>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { getBrands } from '@/services/phoneApi.js'

export default {
  name: 'CategoryPage',
  data() {
    return {
      brands: [],
      loading: false,
      errorMessage: '',
    }
  },
  async mounted() {
    await this.fetchBrands()
  },
  methods: {
    async fetchBrands() {
      this.loading = true
      this.errorMessage = ''
      try {
        const brands = await getBrands()
        this.brands = [...brands].sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0))
      } catch (error) {
        console.error(error)
        this.errorMessage = '品牌数据加载失败，请稍后重试。'
      } finally {
        this.loading = false
      }
    },
    handleLogoError(event) {
      if (event?.target) {
        event.target.style.visibility = 'hidden'
      }
    },
  },
}
</script>

<style scoped>
.category .container {
  width: min(1440px, calc(100% - 32px)) !important;
  max-width: 1440px !important;
  padding-right: 15px !important;
  padding-left: 15px !important;
}

.brand-card {
  border-radius: 8px;
  padding: 22px 16px 18px;
  text-align: center;
  border: 1px solid var(--border-soft);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;
  height: 100%;
  background-color: var(--surface-bg);
}

.brand-link,
.brand-link:hover {
  text-decoration: none;
}

.brand-card:hover {
  transform: translateY(-5px);
  border-color: rgba(var(--app-primary-rgb), 0.35);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
}

.brand-card img {
  max-width: 100%;
  height: 96px;
  margin-bottom: 18px;
  object-fit: contain;
}

.brand-card h4 {
  color: var(--text-main);
  margin: 0;
  font-weight: 600;
  font-size: 16px;
}

.brand-card p {
  margin: 5px 0 0;
  font-size: 14px;
  color: var(--text-muted);
}

.tabs {
  margin-bottom: 20px;
  display: flex;
  justify-content: center;
}

.tabs .tab {
  padding: 10px 20px;
  cursor: pointer;
  opacity: 0.5;
}

.tabs .tab.active {
  opacity: 1;
  border-bottom: 2px solid var(--app-primary);
}

@media (max-width: 767.98px) {
  .brand-card {
    margin-bottom: 15px;
  }
}
</style>
