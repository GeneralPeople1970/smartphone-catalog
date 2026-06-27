<template>
  <nav class="shared-nav-shell">
    <!-- 顶部导航栏 -->
    <div class="shared-top-nav">
      <div class="shared-nav-container shared-top-nav-row">
        <router-link class="shared-nav-brand" to="/" @click="closeMenu">
          <img
            :src="logoUrl"
            class="shared-nav-logo"
            alt="智能手机参数站Logo - 提供手机参数查询与对比"
          />
          <span>智能手机参数站</span>
        </router-link>
        <!-- 导航栏切换按钮 -->
        <button
          class="shared-nav-toggle"
          type="button"
          :aria-expanded="mobileMenuOpen ? 'true' : 'false'"
          aria-label="Toggle navigation"
          @click="toggleMenu"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path
              v-if="!mobileMenuOpen"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16"
            />
            <path
              v-else
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>
        <!-- 桌面端搜索框和登录/注册按钮 -->
        <div class="shared-desktop-actions">
          <a :href="userHref" class="shared-user-chip">{{ userLabel }}</a>
          <ThemeControl />
        </div>
      </div>
    </div>

    <!-- 主导航菜单 -->
    <div class="shared-main-nav">
      <div class="shared-nav-container">
        <div class="shared-nav-content" :class="{ 'shared-nav-content-open': mobileMenuOpen }">
          <!-- 移动端搜索框和登录/注册按钮 -->
          <div class="shared-mobile-actions">
            <a :href="userHref" class="shared-user-chip">{{ userLabel }}</a>
            <ThemeControl />
          </div>
          <ul class="shared-nav-menu">
            <li class="shared-nav-item">
              <router-link
                class="shared-nav-link"
                :class="{ 'shared-nav-link-active': isHomeActive }"
                to="/"
                @click="closeMenu"
                >首页</router-link
              >
            </li>
            <li class="shared-nav-item">
              <router-link
                class="shared-nav-link"
                :class="{ 'shared-nav-link-active': isCategoryActive }"
                to="/category"
                @click="closeMenu"
                >分类</router-link
              >
            </li>
            <li class="shared-nav-item">
              <router-link
                class="shared-nav-link"
                :class="{ 'shared-nav-link-active': isSearchActive }"
                :to="{ name: 'Home', hash: '#home-search' }"
                @click="closeMenu"
                >搜索</router-link
              >
            </li>
          </ul>
        </div>
      </div>
    </div>
  </nav>
  <!-- Font Awesome 的外部链接，不再需要，因为已在 main.js 中导入 -->
  <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"> -->
</template>

<script>
import { getCurrentUser } from '@/services/phoneApi.js'
import ThemeControl from '@/components/ThemeControl.vue'

function readInitialAuth() {
  const auth = window.__SMARTPHONE_CATALOG_AUTH__

  return auth?.authenticated && auth.user?.name ? auth.user.name : ''
}

export default {
  name: 'NavBar',
  components: {
    ThemeControl,
  },
  data() {
    return {
      authUserName: readInitialAuth(),
      mobileMenuOpen: false,
      logoUrl: '/assets/logo.png',
    }
  },
  computed: {
    currentRouteName() {
      return String(this.$route.name || '')
    },
    isHomeActive() {
      return this.currentRouteName === 'Home' && !this.isSearchActive
    },
    isSearchActive() {
      return (
        this.currentRouteName === 'Search' ||
        (this.currentRouteName === 'Home' &&
          (this.$route.hash === '#home-search' || Boolean(this.$route.query.q)))
      )
    },
    isCategoryActive() {
      return (
        this.currentRouteName === 'Category' ||
        this.currentRouteName === 'PhoneDetail' ||
        this.currentRouteName === 'PhoneDetailById' ||
        this.currentRouteName.endsWith('List')
      )
    },
    userLabel() {
      return this.authUserName || '注册/登录'
    },
    userHref() {
      return this.authUserName ? '/dashboard' : '/login'
    },
  },
  mounted() {
    this.loadCurrentUser()
  },
  methods: {
    toggleMenu() {
      this.mobileMenuOpen = !this.mobileMenuOpen
    },
    closeMenu() {
      this.mobileMenuOpen = false
    },
    async loadCurrentUser() {
      try {
        const data = await getCurrentUser()
        this.authUserName = data?.authenticated && data.user?.name ? data.user.name : ''
      } catch (error) {
        console.error(error)
        this.authUserName = ''
      }
    },
  },
}
</script>
