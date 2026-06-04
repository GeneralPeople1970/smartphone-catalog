<template>
  <div>
    <!-- 顶部导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
      <div class="container">
        <router-link class="navbar-brand" to="/">
          <img :src="logoUrl" class="mr-3" alt="智能手机参数站Logo - 提供手机参数查询与对比" />
          智能手机参数站
        </router-link>
        <!-- 导航栏切换按钮 (Bootstrap 4 语法) -->
        <button
          class="navbar-toggler"
          type="button"
          data-toggle="collapse"
          data-target="#navbarContent"
          aria-controls="navbarContent"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>
        <!-- 桌面端搜索框和登录/注册按钮 -->
        <div class="form-inline d-none d-lg-flex align-items-center ml-auto">
          <div class="auth-section">
            <a v-if="authUserName" href="/dashboard" class="admin-user-name">{{ authUserName }}</a>
            <a v-else href="/login" class="admin-login-link">注册/登录</a>
          </div>
        </div>
      </div>
    </nav>

    <!-- 主导航菜单 -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top mb-4">
      <div class="container">
        <div id="navbarContent" class="collapse navbar-collapse">
          <!-- 移动端搜索框和登录/注册按钮 -->
          <div class="d-lg-none w-100 mb-3 text-center">
            <div class="d-flex justify-content-end">
              <!--   auth-section d-flex justify-content-center -->
              <a v-if="authUserName" href="/dashboard" class="admin-user-name">{{
                authUserName
              }}</a>
              <a v-else href="/login" class="admin-login-link">注册/登录</a>
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

export default {
  name: 'NavBar',
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
/* 导航栏整体阴影 */
.navbar {
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

/* 品牌Logo和文字样式 */
.navbar-brand {
  font-weight: 700;
  font-size: 2rem;
  display: flex;
  align-items: center;
  /* 垂直居中对齐 Logo 和文字 */
}

.navbar-brand img {
  width: 40px;
  height: 50px;
  object-fit: contain;
  /* 确保 Logo 图片不变形 */
}

/* 导航链接样式 */
.nav-link {
  font-size: 1.25rem;
  position: relative;
}

/* 导航链接下划线 */
.nav-link::after {
  content: '';
  position: absolute;
  bottom: -5px;
  /* 调整下划线位置 */
  left: 0;
  width: 0;
  height: 3px;
  /* 下划线厚度 */
  background-color: #007bff;
  /* 下划线颜色 */
  transition: width 0.3s ease-in-out;
  /* 平滑过渡效果 */
}

/* 导航链接悬停时显示下划线 */
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
  color: #007bff !important;
}

/* 下拉菜单样式（如果以后需要） */
.dropdown-menu {
  border: none;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.dropdown-item {
  color: #666;
}

/* 全局容器最大宽度 */
.container {
  max-width: 1440px;
  padding: 0 15px;
}

/* 搜索框和认证区域的对齐 */
.d-lg-flex.align-items-center.ml-auto {
  margin-left: auto !important;
  /* 强制推到最右边 */
}

.admin-login-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  padding: 0.38rem 0.9rem;
  border: 1px solid #007bff;
  border-radius: 4px;
  background-color: #007bff;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 500;
  line-height: 1;
  text-decoration: none;
  transition:
    color 0.2s ease,
    border-color 0.2s ease,
    background-color 0.2s ease;
}

.admin-login-link:hover,
.admin-login-link:focus {
  color: #fff;
  text-decoration: none;
  border-color: #0056b3;
  background-color: #0056b3;
}

.admin-login-link:active {
  background-color: #004c9f;
}

.admin-user-name {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  padding: 0.38rem 0.9rem;
  border: 1px solid #d7e3f2;
  border-radius: 4px;
  background-color: #f8fbff;
  color: #17324d;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1;
  text-decoration: none;
  transition:
    color 0.2s ease,
    border-color 0.2s ease,
    background-color 0.2s ease;
}

.admin-user-name:hover,
.admin-user-name:focus {
  border-color: #007bff;
  background-color: #eef6ff;
  color: #007bff;
  text-decoration: none;
}

/* 移动端特定样式调整 */
@media (max-width: 991.98px) {
  .d-lg-none .input-group,
  .d-lg-none .auth-section {
    max-width: 300px;
    /* 限制移动端搜索框和按钮的宽度 */
    margin: 0 auto;
    /* 水平居中 */
  }

  .d-lg-none .auth-section {
    padding-top: 1rem;
    /* 增加与搜索框的间距 */
  }

  .admin-login-link {
    min-height: 32px;
    padding: 0.36rem 0.8rem;
    font-size: 0.9rem;
  }

  .admin-user-name {
    min-height: 32px;
    padding: 0.36rem 0.8rem;
    font-size: 0.9rem;
  }
}
</style>
