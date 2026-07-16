<template>
  <main class="page-content not-found-page d-flex align-items-center">
    <section class="container text-center" aria-labelledby="not-found-title">
      <p class="status-code mb-2" aria-hidden="true">404</p>
      <h1 id="not-found-title" class="mb-3">页面未找到</h1>
      <p class="text-muted mb-4">该地址不存在，或页面已被移动。</p>
      <router-link to="/" class="btn btn-primary">返回首页</router-link>
    </section>
  </main>
</template>

<script>
export default {
  name: 'NotFoundPage',
}
</script>

<style scoped>
.not-found-page {
  min-height: 55vh;
}

.status-code {
  color: var(--app-primary);
  font-size: clamp(4rem, 14vw, 8rem);
  font-weight: 800;
  line-height: 1;
}
</style>
