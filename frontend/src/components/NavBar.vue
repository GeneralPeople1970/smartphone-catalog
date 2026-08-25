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
        <!-- 桌面端用户名按钮和退出登录 -->
        <div class="shared-desktop-actions">
          <a :href="userHref" :title="userTitle" class="shared-user-chip">{{ userLabel }}</a>
          <form v-if="canLogout" method="POST" :action="logoutUrl">
            <input type="hidden" name="_token" :value="csrfToken" />
            <button type="submit" class="shared-nav-logout">退出登录</button>
          </form>
        </div>
      </div>
    </div>

    <!-- 主导航菜单 -->
    <div class="shared-main-nav">
      <div class="shared-nav-container">
        <div class="shared-nav-content" :class="{ 'shared-nav-content-open': mobileMenuOpen }">
          <!-- 移动端用户名按钮和退出登录 -->
          <div class="shared-mobile-actions">
            <a :href="userHref" :title="userTitle" class="shared-user-chip">{{ userLabel }}</a>
            <form v-if="canLogout" method="POST" :action="logoutUrl">
              <input type="hidden" name="_token" :value="csrfToken" />
              <button type="submit" class="shared-nav-logout">退出登录</button>
            </form>
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

function readInitialAuth() {
  const auth = window.__SMARTPHONE_CATALOG_AUTH__

  if (auth?.authenticated && auth.user?.name) {
    return { name: auth.user.name, csrfToken: auth.csrfToken || '' }
  }

  return { name: '', csrfToken: '' }
}

export default {
  name: 'NavBar',
  data() {
    const initialAuth = readInitialAuth()

    return {
      authUserName: initialAuth.name,
      csrfToken: initialAuth.csrfToken,
      logoutUrl: '/logout',
      mobileMenuOpen: false,
      logoUrl: '/assets/logo.png',
    }
  },
  computed: {
    currentRouteName() {
      return String(this.$route.name || '')
    },
    isHomeActive() {
      return this.currentRouteName === 'Home' || this.currentRouteName === 'Search'
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
    // The username chip is the front/back switch. Here it goes to the backend;
    // the backend top bar (navigation.blade.php) sends it back to the frontend.
    userHref() {
      if (!this.authUserName) {
        return '/login'
      }

      return '/dashboard'
    },
    userTitle() {
      return this.authUserName ? '前往后台控制台' : '注册或登录'
    },
    // The logout button mirrors the backend top bar. It needs the session CSRF
    // token, so it stays hidden until /api/me (or the bootstrap payload) hands
    // one over — a form without a token would only ever 419.
    canLogout() {
      return Boolean(this.authUserName) && Boolean(this.csrfToken)
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
        if (data?.authenticated && data.user?.name) {
          this.authUserName = data.user.name
          this.csrfToken = data.csrfToken || ''
        } else {
          this.authUserName = ''
          this.csrfToken = ''
        }
      } catch (error) {
        console.error(error)
        this.authUserName = ''
        this.csrfToken = ''
      }
    },
  },
}
</script>
