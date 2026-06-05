<template>
  <div class="home-page">
    <h1 class="visually-hidden">智能手机参数站 - 全面手机参数查询与对比</h1>

    <div
      v-if="carouselImages.length"
      id="heroCarousel"
      class="carousel slide carousel-fade container mt-4 mb-5"
      data-bs-ride="carousel"
    >
      <div v-if="carouselImages.length > 1" class="carousel-indicators">
        <button
          v-for="(image, index) in carouselImages"
          :key="image.id || image.image"
          type="button"
          data-bs-target="#heroCarousel"
          :data-bs-slide-to="index"
          :class="{ active: index === 0 }"
          :aria-current="index === 0 ? 'true' : undefined"
          :aria-label="`Slide ${index + 1}`"
        ></button>
      </div>
      <div class="carousel-inner rounded-lg shadow-sm">
        <div
          v-for="(image, index) in carouselImages"
          :key="image.id || image.image"
          class="carousel-item"
          :class="{ active: index === 0 }"
        >
          <component
            :is="image.linkUrl ? 'a' : 'div'"
            :href="image.linkUrl || undefined"
            class="carousel-link"
          >
            <img
              class="d-block w-100 carousel-img"
              :src="image.image"
              :alt="image.title || '首页轮播图'"
            />
          </component>
        </div>
      </div>
      <a
        v-if="carouselImages.length > 1"
        class="carousel-control-prev"
        href="#heroCarousel"
        role="button"
        data-bs-slide="prev"
      >
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a
        v-if="carouselImages.length > 1"
        class="carousel-control-next"
        href="#heroCarousel"
        role="button"
        data-bs-slide="next"
      >
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>

    <section v-if="homepageFeaturedPhones.length" class="featured-phones hot-phones py-5">
      <div class="container">
        <div class="section-heading">
          <h2 class="text-dark">热门机型</h2>
          <p>后台推荐的热门机型，快速查看核心参数。</p>
        </div>
        <div class="featured-grid">
          <article
            v-for="phone in homepageFeaturedPhones"
            :key="phone.id || `${phone.companyCode}-${phone.phonename}`"
            class="featured-card"
            @click="goToPhoneDetail(phone)"
          >
            <div class="featured-media">
              <img :src="phone.imgurl || '/img/placeholder.png'" :alt="phone.phonename" />
            </div>
            <div class="featured-content">
              <h3>{{ getPhoneTitle(phone) }}</h3>
              <div class="phone-brand-logo">
                <img
                  v-if="phone.brandLogo"
                  :src="phone.brandLogo"
                  :alt="phone.company || phone.companyCode"
                />
                <span v-else>{{ phone.company || phone.companyCode || '品牌待补充' }}</span>
              </div>
              <p v-if="getPhoneDescription(phone)" class="featured-description">
                {{ getPhoneDescription(phone) }}
              </p>
              <ul class="phone-specs">
                <li>
                  <span>处理器</span><strong>{{ phone.socname || '待补充' }}</strong>
                </li>
                <li>
                  <span>价格</span><strong>{{ formatPrice(phone) }}</strong>
                </li>
                <li>
                  <span>电池容量</span><strong>{{ formatBattery(phone.battery) }}</strong>
                </li>
              </ul>
              <div class="featured-footer">
                <span>查看完整参数</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="featured-phones recent-phones py-5">
      <div class="container">
        <div class="section-heading">
          <h2 class="text-dark">近期推出</h2>
          <p>近期发布机型，快速查看核心参数。</p>
        </div>
        <div v-if="recentLoading" class="text-center py-5 text-muted">正在加载近期机型...</div>
        <div v-else class="featured-grid">
          <article
            v-for="phone in recentPhones"
            :key="phone.id || `${phone.companyCode}-${phone.phonename}`"
            class="featured-card"
            @click="goToPhoneDetail(phone)"
          >
            <div class="featured-media">
              <img :src="phone.imgurl || '/img/placeholder.png'" :alt="phone.phonename" />
            </div>
            <div class="featured-content">
              <h3>{{ phone.phonename }}</h3>
              <div class="phone-brand-logo">
                <img
                  v-if="phone.brandLogo"
                  :src="phone.brandLogo"
                  :alt="phone.company || phone.companyCode"
                />
                <span v-else>{{ phone.company || phone.companyCode || '品牌待补充' }}</span>
              </div>
              <ul class="phone-specs">
                <li>
                  <span>处理器</span><strong>{{ phone.socname || '待补充' }}</strong>
                </li>
                <li>
                  <span>价格</span><strong>{{ formatPrice(phone) }}</strong>
                </li>
                <li>
                  <span>电池容量</span><strong>{{ formatBattery(phone.battery) }}</strong>
                </li>
              </ul>
              <div class="featured-footer">
                <span>查看完整参数</span>
              </div>
            </div>
          </article>
        </div>
        <div class="text-center mt-5">
          <router-link to="/category" class="btn btn-lg btn-outline-dark view-all-button">
            查看所有品牌
          </router-link>
        </div>
      </div>
    </section>

    <section class="brands-section py-5 bg-white">
      <div class="container">
        <h2 class="text-center mb-5 text-dark">热门品牌</h2>
        <div class="row text-center brand-logos">
          <div
            v-for="brand in popularBrands"
            :key="brand.code || brand.name"
            class="col-6 col-md-3 mb-4"
          >
            <router-link :to="brand.path" class="brand-link">
              <img
                :src="brand.logo"
                :alt="brand.displayName || brand.name"
                class="img-fluid brand-logo-img"
              />
              <p class="brand-name mt-2">{{ brand.displayName || brand.name }}</p>
            </router-link>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
import {
  getBrands,
  getFeaturedPhones,
  getHomepageFeaturedPhones,
  getHomepageSlides,
} from '@/services/phoneApi.js'
import { slugify } from '@/utils/slugify.js'
import { Carousel } from 'bootstrap'

export default {
  name: 'HomePage',
  data() {
    return {
      homepageFeaturedPhones: [],
      recentPhones: [],
      popularBrands: [],
      recentLoading: false,
      carouselImages: [],
    }
  },
  async mounted() {
    await Promise.all([this.fetchHomepageSlides(), this.fetchHomeData()])
  },
  methods: {
    slugify,
    getPhoneTitle(phone) {
      return phone.recommendTitle || phone.phonename
    },
    getPhoneDescription(phone) {
      return phone.recommendDescription || phone.feature || ''
    },
    formatPrice(phone) {
      if (phone?.displayPrice) return phone.displayPrice
      return Number(phone?.price) > 0 ? `￥${phone.price}` : '暂无价格'
    },
    formatBattery(battery) {
      return Number(battery) > 0 ? `${battery} mAh` : '电池待补充'
    },
    async fetchHomeData() {
      this.recentLoading = true
      try {
        const [homepageFeaturedPhones, recentPhones, brands] = await Promise.all([
          getHomepageFeaturedPhones(),
          getFeaturedPhones(),
          getBrands(),
        ])
        this.homepageFeaturedPhones = Array.isArray(homepageFeaturedPhones)
          ? homepageFeaturedPhones
          : []
        this.recentPhones = Array.isArray(recentPhones) ? recentPhones : []
        this.popularBrands = [...brands]
          .sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0))
          .slice(0, 8)
          .map((brand) => ({
            ...brand,
            displayName: brand.displayName || brand.name,
          }))
      } catch (error) {
        console.error(error)
        this.homepageFeaturedPhones = []
        this.recentPhones = []
        this.popularBrands = []
      } finally {
        this.recentLoading = false
      }
    },
    async fetchHomepageSlides() {
      try {
        this.carouselImages = await getHomepageSlides()
        this.$nextTick(() => {
          const element = document.getElementById('heroCarousel')
          if (element && this.carouselImages.length > 1) {
            Carousel.getOrCreateInstance(element)
          }
        })
      } catch (error) {
        console.error(error)
        this.carouselImages = []
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
          phoneNameSlug: phone.slug || this.slugify(phone.phonename),
        },
      })
    },
  },
}
</script>

<style scoped>
.home-page {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: #1f2d3d;
  background-color: #fff;
}

.carousel-inner {
  border-radius: 12px;
  overflow: hidden;
}

.carousel-link {
  display: block;
}

.carousel-img {
  height: 450px;
  object-fit: cover;
  width: 100%;
}

.featured-phones {
  background-color: #f5f9ff;
  border-top: 1px solid #e8f1ff;
  border-bottom: 1px solid #e8f1ff;
}

.hot-phones {
  background-color: #fff;
  border-top: 0;
}

.recent-phones {
  background-color: #f5f9ff;
}

.section-heading {
  max-width: 720px;
  margin: 0 auto 2.25rem;
  text-align: center;
}

.section-heading h2 {
  color: #123b66;
  font-weight: 600;
  margin-bottom: 0.6rem;
}

.section-heading p {
  margin: 0;
  color: #6c757d;
  font-size: 1rem;
}

.featured-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1.5rem;
}

.featured-card {
  display: grid;
  grid-template-rows: 250px 1fr;
  min-height: 100%;
  border: 1px solid #dcecff;
  border-radius: 8px;
  background-color: #fff;
  overflow: hidden;
  transition:
    transform 0.3s ease,
    box-shadow 0.3s ease;
  cursor: pointer;
}

.featured-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 14px 30px rgba(0, 91, 187, 0.13);
}

.featured-media {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 22px;
  background: linear-gradient(180deg, #ffffff 0%, #f2f8ff 100%);
  border-bottom: 1px solid #e5f0ff;
}

.featured-media img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.featured-content {
  display: flex;
  flex-direction: column;
  padding: 1.25rem;
}

.featured-content h3 {
  min-height: 2.6rem;
  margin: 0 0 0.85rem;
  color: #17324d;
  font-size: 1.25rem;
  font-weight: 650;
  line-height: 1.25;
}

.featured-description {
  display: -webkit-box;
  min-height: 2.8rem;
  margin: -0.25rem 0 0.85rem;
  overflow: hidden;
  color: #5f7186;
  font-size: 0.92rem;
  line-height: 1.5;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.phone-brand-logo {
  display: flex;
  align-items: center;
  min-height: 34px;
  margin: -0.35rem 0 1rem;
}

.phone-brand-logo img {
  max-width: 92px;
  max-height: 28px;
  object-fit: contain;
}

.phone-brand-logo span {
  color: #007bff;
  font-size: 0.95rem;
  font-weight: 600;
}

.phone-specs {
  flex: 1;
  margin: 0 0 1rem;
  padding: 0;
  list-style: none;
}

.phone-specs li {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.58rem 0;
  border-bottom: 1px solid #edf5ff;
}

.phone-specs span {
  flex: 0 0 4.5rem;
  color: #6c7f95;
  font-size: 0.9rem;
}

.phone-specs strong {
  color: #263f58;
  font-size: 0.95rem;
  font-weight: 600;
  text-align: right;
}

.featured-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-top: 0.85rem;
  border-top: 1px solid #dcecff;
}

.featured-footer span {
  color: #007bff;
  font-size: 0.92rem;
  font-weight: 600;
}

.view-all-button {
  border: 1px solid #007bff;
  color: #007bff;
  font-size: 1rem;
  padding: 0.65rem 1.55rem;
  border-radius: 4px;
  transition: all 0.3s ease;
}

.view-all-button:hover {
  background-color: #007bff;
  color: white;
  transform: translateY(-3px);
  box-shadow: 0 5px 10px rgba(0, 123, 255, 0.22);
}

.brands-section {
  background-color: white;
}

.brands-section h2 {
  color: #123b66;
  font-weight: 600;
}

.brand-link {
  display: block;
  padding: 15px;
  border-radius: 8px;
  transition:
    transform 0.3s ease,
    box-shadow 0.3s ease;
  text-decoration: none;
  color: #333;
}

.brand-link:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 16px rgba(0, 91, 187, 0.1);
}

.brand-logo-img {
  max-height: 80px;
  width: auto;
  object-fit: contain;
  margin: 0 auto;
}

.brand-name {
  font-size: 1.1rem;
  font-weight: 500;
  color: #17324d;
}

.container {
  max-width: 1440px;
  padding: 0 15px;
}

@media (max-width: 767.98px) {
  .featured-grid {
    grid-template-columns: 1fr;
  }

  .featured-card {
    grid-template-rows: 220px 1fr;
  }
}

@media (min-width: 768px) and (max-width: 991.98px) {
  .featured-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
