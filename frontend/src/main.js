// src/main.js
import { createApp } from 'vue'
import App from './App.vue'
import router from './router'

// 1. 导入 jQuery 并显式挂载到全局 window 对象
import $ from 'jquery'
window.$ = window.jQuery = $ // 这一行是关键！

// 2. 导入 Popper.js (Bootstrap 4 的依赖)
import 'popper.js'

// 3. 导入 Bootstrap 4 的 JavaScript (bundle 版本包含了 Bootstrap 插件)
import 'bootstrap/dist/js/bootstrap.bundle.min.js'

// 4. 导入 Bootstrap 4 的 CSS
import 'bootstrap/dist/css/bootstrap.min.css'

// 5. 导入 Font Awesome (因为你的 NavBar.vue 中使用了搜索图标)
// import '@fortawesome/fontawesome-free/css/all.min.css'

createApp(App).use(router).mount('#app')
