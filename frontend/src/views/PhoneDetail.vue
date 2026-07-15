<template>
  <main class="phone-detail-page">
    <div class="container">
      <div v-if="loading" class="detail-state text-muted">正在加载手机详情...</div>

      <article v-else-if="phone" class="detail-shell">
        <button type="button" class="back-button" @click="goBack">返回</button>

        <section class="detail-hero">
          <div class="detail-media">
            <img
              :src="imageOrPlaceholder(phone.imgurl)"
              :alt="phone.phonename"
              width="560"
              height="560"
              decoding="async"
              fetchpriority="high"
              @error="handleImageError"
            />
          </div>

          <div class="detail-summary">
            <div v-if="phone.brandLogo" class="brand-logo-badge">
              <img
                :src="phone.brandLogo"
                :alt="brandLogoAlt"
                loading="lazy"
                decoding="async"
                @error="hideBrokenLogo"
              />
            </div>
            <h1>{{ phone.phonename }}</h1>
            <p v-if="phone.feature" class="feature-text">{{ phone.feature }}</p>

            <div class="summary-grid">
              <div v-for="item in summaryItems" :key="item.label" class="summary-item">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </div>
            </div>

            <a
              v-if="officialHref()"
              class="official-link"
              :href="officialHref()"
              target="_blank"
              rel="noopener noreferrer"
            >
              官方链接
            </a>
          </div>
        </section>

        <section class="spec-section-list">
          <div v-for="section in specSections" :key="section.title" class="spec-section">
            <h2>{{ section.title }}</h2>
            <dl class="spec-grid">
              <div v-for="item in section.items" :key="item.label" class="spec-item">
                <dt>{{ item.label }}</dt>
                <dd>
                  <img
                    v-if="item.kind === 'brandLogo'"
                    class="spec-brand-logo"
                    :src="item.value"
                    :alt="brandLogoAlt"
                    loading="lazy"
                    decoding="async"
                    @error="hideBrokenLogo"
                  />
                  <template v-else>{{ item.value }}</template>
                </dd>
              </div>
            </dl>
          </div>
        </section>
      </article>

      <div v-else class="alert alert-warning" role="alert">找不到该手机的详细信息。</div>
    </div>
  </main>
</template>

<script>
import { getPhoneById, getPhoneDetail } from '@/services/phoneApi.js'
import {
  PLACEHOLDER_IMAGE,
  imageOrPlaceholder as resolveImageOrPlaceholder,
} from '@/utils/image.js'
import { safeExternalUrl } from '@/utils/url.js'

export default {
  props: {
    id: {
      type: [String, Number],
      required: false,
      default: null,
    },
    brandName: {
      type: String,
      required: false,
      default: '',
    },
    phoneNameSlug: {
      type: String,
      required: false,
      default: '',
    },
  },
  data() {
    return {
      phone: null,
      loading: false,
      placeholderImage: PLACEHOLDER_IMAGE,
    }
  },
  computed: {
    summaryItems() {
      return [
        { label: '价格', value: this.formatPrice(this.phone?.price) },
        { label: '处理器', value: this.displayValue(this.phone?.socname) },
        { label: '电池', value: this.formatBattery(this.phone?.battery) },
        { label: '重量', value: this.formatWeight(this.phone?.weight) },
      ]
    },
    brandLogoAlt() {
      return `${this.phone?.company || this.phone?.companyCode || '手机品牌'} logo`
    },
    specSections() {
      const coreItems = [
        { label: '处理器', value: this.displayValue(this.phone?.socname) },
        { label: 'CPU', value: this.displayValue(this.phone?.cpu) },
        { label: 'GPU', value: this.displayValue(this.phone?.gpu) },
        { label: '运行内存', value: this.displayValue(this.phone?.ramfadsf) },
        {
          label: '机身存储',
          value: this.displayValue(this.phone?.romagbcz || this.phone?.storeage),
        },
      ]

      if (this.phone?.brandLogo) {
        coreItems.unshift({
          label: '品牌',
          value: this.phone.brandLogo,
          kind: 'brandLogo',
        })
      }

      return [
        {
          title: '核心参数',
          items: coreItems,
        },
        {
          title: '屏幕与机身',
          items: [
            { label: '屏幕材质', value: this.displayValue(this.phone?.screenm) },
            { label: '屏幕色彩', value: this.displayValue(this.phone?.screencolor) },
            { label: '机身材质', value: this.displayValue(this.phone?.material) },
            { label: '电池容量', value: this.formatBattery(this.phone?.battery) },
            { label: '充电功率', value: this.displayValue(this.phone?.charge) },
            { label: '重量', value: this.formatWeight(this.phone?.weight) },
          ],
        },
        {
          title: '连接与功能',
          items: [
            { label: 'Wi-Fi', value: this.displayValue(this.phone?.wifi) },
            { label: '蓝牙', value: this.displayValue(this.phone?.bluetooth) },
            { label: '定位', value: this.displayValue(this.phone?.location) },
            { label: '系统 UI', value: this.displayValue(this.phone?.osui) },
            { label: '传感器', value: this.displayValue(this.phone?.sensor) },
            { label: '特色功能', value: this.displayValue(this.phone?.feature) },
          ],
        },
      ]
    },
  },
  watch: {
    '$route.params': {
      handler: 'fetchPhoneDetails',
      immediate: true,
    },
  },
  methods: {
    async fetchPhoneDetails() {
      this.loading = true
      try {
        if (this.id) {
          this.phone = await getPhoneById(this.id)
        } else if (this.brandName && this.phoneNameSlug) {
          this.phone = await getPhoneDetail(this.brandName, this.phoneNameSlug)
        } else {
          this.phone = null
        }
      } catch (error) {
        console.error(error)
        this.phone = null
      } finally {
        this.loading = false
      }
    },
    goBack() {
      if (window.history.length > 1) {
        this.$router.go(-1)
        return
      }

      this.$router.push('/category')
    },
    displayValue(value) {
      const normalized = String(value ?? '').trim()
      return normalized && normalized !== '0' ? normalized : '待补充'
    },
    formatPrice(price) {
      return Number(price) > 0 ? `¥${price}` : '暂无价格'
    },
    formatBattery(battery) {
      return Number(battery) > 0 ? `${battery} mAh` : '待补充'
    },
    formatWeight(weight) {
      return Number(weight) > 0 ? `${weight} g` : this.displayValue(weight)
    },
    imageOrPlaceholder(image) {
      return resolveImageOrPlaceholder(image, this.placeholderImage)
    },
    officialHref() {
      return safeExternalUrl(this.phone?.official)
    },
    handleImageError(event) {
      if (event?.target?.src && !event.target.src.endsWith(this.placeholderImage)) {
        event.target.src = this.placeholderImage
      }
    },
    hideBrokenLogo(event) {
      if (event?.target) {
        event.target.style.display = 'none'
      }
    },
  },
}
</script>

<style scoped>
.phone-detail-page {
  padding: 1rem 0 3rem;
  color: var(--text-main);
}

.detail-state {
  padding: 4rem 0;
  text-align: center;
}

.detail-shell {
  display: grid;
  gap: 1.25rem;
}

.back-button {
  width: fit-content;
  border: 1px solid var(--border-soft);
  border-radius: 6px;
  background: var(--surface-bg);
  color: var(--text-main);
  padding: 0.45rem 0.9rem;
  font-weight: 650;
}

.back-button:hover {
  border-color: var(--app-primary);
  color: var(--app-primary);
}

.detail-hero {
  display: grid;
  grid-template-columns: minmax(360px, 0.95fr) minmax(0, 1.35fr);
  gap: clamp(1.25rem, 2vw, 2.5rem);
  border: 1px solid var(--border-soft);
  border-radius: 8px;
  background: var(--surface-bg);
  padding: clamp(1.25rem, 2vw, 2rem);
}

.detail-media {
  display: flex;
  min-height: clamp(380px, 35vw, 600px);
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: var(--surface-muted);
  padding: 1.5rem;
}

.detail-media img {
  width: 100%;
  max-height: 560px;
  object-fit: contain;
}

.detail-summary {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.brand-logo-badge {
  display: inline-flex;
  width: fit-content;
  min-height: 42px;
  align-items: center;
  justify-content: center;
  border: 0;
  background: transparent;
  padding: 0;
}

.brand-logo-badge img {
  max-width: 120px;
  max-height: 30px;
  object-fit: contain;
}

.detail-summary h1 {
  margin: 0.8rem 0 0;
  color: var(--text-main);
  font-size: clamp(1.75rem, 4vw, 2.65rem);
  font-weight: 750;
  line-height: 1.16;
}

.feature-text {
  margin: 0.9rem 0 0;
  color: var(--text-muted);
  line-height: 1.6;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
  margin-top: 1.25rem;
}

.summary-item {
  border: 1px solid var(--border-soft);
  border-radius: 8px;
  background: var(--surface-muted);
  padding: 0.85rem;
}

.summary-item span {
  display: block;
  color: var(--text-muted);
  font-size: 0.82rem;
  font-weight: 700;
}

.summary-item strong {
  display: block;
  margin-top: 0.35rem;
  color: var(--text-main);
  font-size: 1rem;
  line-height: 1.35;
}

.official-link {
  display: inline-flex;
  width: 100%;
  margin-top: 1.25rem;
  border: 1px solid var(--app-primary);
  border-radius: 6px;
  background: var(--app-primary);
  color: #fff;
  align-items: center;
  justify-content: center;
  padding: 0.72rem 1rem;
  font-weight: 700;
  text-decoration: none;
  transition:
    border-color 0.18s ease,
    background 0.18s ease;
}

.official-link:hover {
  border-color: var(--app-primary-hover);
  background: var(--app-primary-hover);
  color: #fff;
}

.spec-section-list {
  display: grid;
  gap: 1rem;
}

.spec-section {
  border: 1px solid var(--border-soft);
  border-radius: 8px;
  background: var(--surface-bg);
  padding: 1.1rem 1.25rem;
}

.spec-section h2 {
  margin: 0 0 0.9rem;
  color: var(--text-main);
  font-size: 1.1rem;
  font-weight: 750;
}

.spec-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
  column-gap: 2rem;
  margin: 0;
}

.spec-item {
  display: grid;
  grid-template-columns: 7rem minmax(0, 1fr);
  gap: 1rem;
  border-top: 1px solid var(--border-soft);
  padding: 0.75rem 0;
}

.spec-item:nth-child(-n + 2) {
  border-top: 0;
}

.spec-item dt {
  color: var(--text-muted);
  font-weight: 700;
}

.spec-item dd {
  margin: 0;
  color: var(--text-main);
  word-break: break-word;
}

.spec-brand-logo {
  max-width: 110px;
  max-height: 26px;
  object-fit: contain;
}

@media (max-width: 991.98px) {
  .detail-hero {
    grid-template-columns: 1fr;
  }

  .detail-media {
    min-height: 300px;
  }

  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .spec-grid {
    grid-template-columns: 1fr;
  }

  .spec-item:nth-child(-n + 2) {
    border-top: 1px solid var(--border-soft);
  }

  .spec-item:first-child {
    border-top: 0;
  }
}

@media (max-width: 575.98px) {
  .detail-hero,
  .spec-section {
    padding: 1rem;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .spec-item {
    grid-template-columns: 1fr;
    gap: 0.25rem;
  }
}
</style>
