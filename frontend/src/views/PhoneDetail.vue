<template>
  <div class="phone-detail-page container py-5 bg-white border rounded-lg">
    <div v-if="loading" class="text-center py-5 text-muted">正在加载手机详情...</div>
    <div v-else-if="phone">
      <h1 class="mb-4">{{ phone.phonename }}</h1>
      <div class="row">
        <div class="col-md-6">
          <img
            :src="phone.imgurl || 'https://img.picui.cn/free/2025/06/15/684eea6ca37d0.png'"
            alt="手机图片"
            class="img-fluid rounded mb-4 shadow-sm"
          />
        </div>
        <div class="col-md-6">
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><strong>处理器:</strong> {{ phone.socname }}</li>
            <li class="list-group-item"><strong>价格:</strong> {{ formatPrice(phone.price) }}</li>
            <li class="list-group-item"><strong>屏幕材质:</strong> {{ phone.screenm }}</li>
            <li class="list-group-item"><strong>电池容量:</strong> {{ phone.battery }} mAh</li>
            <li class="list-group-item"><strong>充电功率:</strong> {{ phone.charge || 'N/A' }}</li>
            <li class="list-group-item"><strong>存储:</strong> {{ phone.storeage || 'N/A' }}</li>
            <li class="list-group-item"><strong>重量:</strong> {{ phone.weight }} g</li>
            <li class="list-group-item"><strong>特色功能:</strong> {{ phone.feature || '无' }}</li>
            <li class="list-group-item">
              <strong>上市日期:</strong> {{ phone.saledate ? formatDate(phone.saledate) : 'N/A' }}
            </li>
            <li class="list-group-item">
              <strong>官方链接:</strong>
              <a
                v-if="phone.official"
                :href="phone.official"
                target="_blank"
                rel="noopener noreferrer"
                >点击查看</a
              >
              <span v-else>无</span>
            </li>
          </ul>
          <button class="btn btn-primary mt-4" @click="goBack">返回</button>
        </div>
      </div>
    </div>
    <div v-else class="alert alert-warning" role="alert">找不到该手机的详细信息。</div>
  </div>
</template>

<script>
import { getPhoneById, getPhoneDetail } from '@/services/phoneApi.js'

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
    }
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
      this.$router.go(-1)
    },
    formatPrice(price) {
      return Number(price) > 0 ? `¥${price}` : '暂无价格'
    },
    formatDate(dateNum) {
      if (!dateNum) return 'N/A'
      const year = String(dateNum).substring(0, 4)
      const month = String(dateNum).substring(4, 6)
      const day = String(dateNum).substring(6, 8)
      return `${year}-${month}-${day}`
    },
  },
}
</script>

<style scoped>
.phone-detail-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.img-fluid {
  max-height: 500px;
  object-fit: contain;
  /* 确保图片完整显示 */
  width: 100%;
  /* 填充父容器宽度 */
}

.list-group-item {
  border-left: none;
  border-right: none;
  padding: 0.75rem 0;
}

.list-group-item:first-child {
  border-top: none;
}

.list-group-item:last-child {
  border-bottom: none;
}

.btn-primary {
  background-color: #007bff;
  border-color: #007bff;
}

.btn-primary:hover {
  background-color: #0056b3;
  border-color: #0056b3;
}
</style>
