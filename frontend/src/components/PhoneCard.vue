<template>
  <router-link :to="phoneRoute(phone)" class="phone-card" :class="`phone-card-${variant}`">
    <div class="phone-card-media">
      <PhoneImage
        :src="phone.imgurl"
        :alt="phone.phonename"
        width="300"
        height="250"
        loading="lazy"
        decoding="async"
      />
    </div>
    <div class="phone-card-content">
      <div v-if="variant !== 'brand'" class="phone-card-brand-info">
        <img
          v-if="phone.brandLogo"
          :src="phone.brandLogo"
          :alt="phone.company"
          loading="lazy"
          @error="$event.target.style.display = 'none'"
        />
        <span v-else>{{ phone.company || phone.companyCode }}</span>
      </div>
      <h3>{{ recommended ? phone.recommendTitle || phone.phonename : phone.phonename }}</h3>
      <p v-if="recommended && description" class="phone-card-description">{{ description }}</p>
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
  </router-link>
</template>

<script setup>
import { computed } from 'vue'
import PhoneImage from './PhoneImage.vue'
import { formatPrice, formatBattery, phoneRoute } from '@/utils/phone.js'

const props = defineProps({
  phone: { type: Object, required: true },
  variant: { type: String, default: 'featured' },
  recommended: { type: Boolean, default: false },
})
const description = computed(() => props.phone.recommendDescription || props.phone.feature || '')
</script>

<style scoped>
.phone-card {
  display: grid;
  grid-template-rows: 250px 1fr;
  min-width: 0;
  border: 1px solid var(--border-soft);
  border-radius: 8px;
  overflow: hidden;
  background: var(--surface-bg);
  color: var(--text-main);
  text-decoration: none;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}
.phone-card:hover,
.phone-card:focus-visible {
  border-color: var(--app-primary);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
}
.phone-card-media {
  display: flex;
  padding: 22px;
  background: var(--surface-muted);
}
.phone-card-media img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.phone-card-content {
  min-width: 0;
  padding: 20px;
  overflow-wrap: anywhere;
}
.phone-card-brand-info {
  height: 32px;
  display: flex;
  align-items: center;
  margin-bottom: 8px;
}
.phone-card-brand-info img {
  max-width: 100px;
  max-height: 28px;
  object-fit: contain;
}
h3 {
  font-size: 1.2rem;
  line-height: 1.4;
  margin: 0 0 12px;
}
.phone-card-description {
  color: var(--text-muted);
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
}
dl {
  margin: 0;
}
dl div {
  display: grid;
  grid-template-columns: 4rem minmax(0, 1fr);
  gap: 10px;
  padding: 8px 0;
  border-top: 1px solid var(--border-soft);
}
dt {
  color: var(--text-muted);
  font-weight: 500;
}
dd {
  text-align: right;
  margin: 0;
  font-weight: 600;
}
.phone-card-search {
  grid-template-rows: 220px 1fr;
}
@media (max-width: 767.98px) {
  .phone-card {
    grid-template-rows: 220px 1fr;
  }
}
</style>
