<template>
  <div>
    <!-- 顶部导航栏 -->
    <nav class="navbar navbar-expand-lg bg-body site-top-nav">
      <div class="container">
        <router-link class="navbar-brand" to="/">
          <img :src="logoUrl" class="me-3" alt="智能手机参数站Logo - 提供手机参数查询与对比" />
          智能手机参数站
        </router-link>
        <!-- 导航栏切换按钮 -->
        <button
          class="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#navbarContent"
          aria-controls="navbarContent"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>
        <!-- 桌面端搜索框和登录/注册按钮 -->
        <div class="d-none d-lg-flex align-items-center ms-auto">
          <div class="auth-section d-flex align-items-center gap-2">
            <a v-if="authUserName" href="/dashboard" class="admin-user-name">{{ authUserName }}</a>
            <a v-else href="/login" class="admin-login-link">注册/登录</a>
            <ThemeControl />
          </div>
        </div>
      </div>
    </nav>

    <!-- 主导航菜单 -->
    <nav class="navbar navbar-expand-lg bg-body sticky-top mb-4 site-main-nav">
      <div class="container">
        <div id="navbarContent" class="collapse navbar-collapse">
          <!-- 移动端搜索框和登录/注册按钮 -->
          <div class="d-lg-none w-100 mb-3 text-center">
            <div class="d-flex align-items-center justify-content-end gap-2">
              <!--   auth-section d-flex justify-content-center -->
              <a v-if="authUserName" href="/dashboard" class="admin-user-name">{{
                authUserName
              }}</a>
              <a v-else href="/login" class="admin-login-link">注册/登录</a>
              <ThemeControl />
            </div>
          </div>
          <ul class="navbar-nav mx-auto">
            <li class="nav-item mx-lg-5 px-lg-5">
              <router-link class="nav-link" :class="{ 'nav-link-active': isHomeActive }" to="/"
                >首页</router-link
              >
            </li>
            <li class="nav-item mx-lg-5 px-lg-5">
              <router-link
                class="nav-link"
                :class="{ 'nav-link-active': isCategoryActive }"
                to="/category"
                >分类</router-link
              >
            </li>
            <li class="nav-item mx-lg-5 px-lg-5">
              <router-link
                class="nav-link"
                :class="{ 'nav-link-active': isSearchActive }"
                to="/search"
                >搜索</router-link
              >
            </li>
          </ul>
        </div>
      </div>
    </nav>
  </div>
  <!-- Font Awesome 的外部链接，不再需要，因为已在 main.js 中导入 -->
  <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"> -->
</template>

<script>
import { getCurrentUser } from '@/services/phoneApi.js'
import ThemeControl from '@/components/ThemeControl.vue'

export default {
  name: 'NavBar',
  components: {
    ThemeControl,
  },
  data() {
    return {
      authUserName: '',
      logoUrl: '/assets/logo.png',
    }
  },
  computed: {
    currentRouteName() {
      return String(this.$route.name || '')
    },
    isHomeActive() {
      return this.currentRouteName === 'Home'
    },
    isSearchActive() {
      return this.currentRouteName === 'Search'
    },
    isCategoryActive() {
      return (
        this.currentRouteName === 'Category' ||
        this.currentRouteName === 'PhoneDetail' ||
        this.currentRouteName === 'PhoneDetailById' ||
        this.currentRouteName.endsWith('List')
      )
    },
  },
  mounted() {
    this.loadCurrentUser()
  },
  methods: {
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

<style scoped>
.navbar {
  font-family: var(--nav-font-family);
  box-shadow: var(--nav-shadow);
}

.site-top-nav {
  height: var(--shared-nav-height);
  min-height: var(--shared-nav-height);
  background: var(--nav-surface-bg) !important;
  padding: 0;
}

.site-top-nav > .container {
  height: var(--shared-nav-height);
  min-height: var(--shared-nav-height);
}

.site-main-nav {
  min-height: 54px;
  background: var(--nav-surface-bg) !important;
}

.navbar-brand {
  display: flex;
  align-items: center;
  min-width: 0;
  color: var(--nav-text-main);
  font-size: var(--nav-brand-font-size);
  font-weight: var(--nav-brand-font-weight);
}

.navbar-brand img {
  width: var(--nav-brand-logo-width);
  height: var(--nav-brand-logo-height);
  object-fit: contain;
}

.nav-link {
  font-size: 1.25rem;
  position: relative;
}

.nav-link::after {
  content: '';
  position: absolute;
  bottom: -5px;
  left: 0;
  width: 0;
  height: 3px;
  background-color: var(--app-primary);
  transition: width 0.3s ease-in-out;
}

.nav-link:hover::after {
  width: 100%;
}

.nav-link.router-link-active::after,
.nav-link.router-link-exact-active::after,
.nav-link.nav-link-active::after {
  width: 100%;
}

.nav-link.router-link-active,
.nav-link.router-link-exact-active,
.nav-link.nav-link-active {
  color: var(--app-primary) !important;
}

.dropdown-menu {
  border: none;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.dropdown-item {
  color: #666;
}

.d-lg-flex.align-items-center.ms-auto {
  margin-left: auto !important;
}

.admin-login-link,
.admin-user-name {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 36px;
  max-width: min(34vw, 220px);
  padding: 0.44rem 0.95rem;
  border: 1px solid var(--app-primary);
  border-radius: 4px;
  background-color: var(--app-primary);
  color: #fff;
  font-size: 0.95rem;
  font-weight: 650;
  line-height: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-decoration: none;
  transition:
    color 0.2s ease,
    border-color 0.2s ease,
    background-color 0.2s ease;
}

.admin-login-link:hover,
.admin-login-link:focus,
.admin-user-name:hover,
.admin-user-name:focus {
  color: #fff;
  text-decoration: none;
  border-color: var(--app-primary-hover);
  background-color: var(--app-primary-hover);
}

.admin-login-link:active,
.admin-user-name:active {
  background-color: var(--app-primary-hover);
}

@media (max-width: 991.98px) {
  .site-top-nav,
  .site-top-nav > .container {
    height: var(--shared-nav-mobile-height);
    min-height: var(--shared-nav-mobile-height);
  }

  .navbar-brand {
    font-size: var(--nav-brand-mobile-font-size);
  }

  .navbar-brand img {
    width: var(--nav-brand-mobile-logo-width);
    height: var(--nav-brand-mobile-logo-height);
  }

  .d-lg-none .input-group,
  .d-lg-none .auth-section {
    max-width: 300px;
    margin: 0 auto;
  }

  .d-lg-none .auth-section {
    padding-top: 1rem;
  }

  .admin-login-link,
  .admin-user-name {
    min-height: 32px;
    max-width: 64vw;
    padding: 0.36rem 0.8rem;
    font-size: 0.9rem;
  }
}
</style>
