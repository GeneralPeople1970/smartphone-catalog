<template>
  <div class="app-container">
    <NavBar />
    <router-view />
    <FooterBar />
  </div>
</template>

<script>
import NavBar from './components/NavBar.vue'
import FooterBar from './components/FooterBar.vue'

export default {
  components: {
    NavBar,
    FooterBar,
  },
}
</script>

<style>
body {
  background-color: #f2f2f2;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.app-container {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* .container {
  max-width: 1440px;
  padding: 0 15px;
} */

/* 页面内容区域 */
.page-content {
  flex: 1;
  padding: 20px 0;
}
</style>
