import { createApp } from 'vue'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap-icons/font/bootstrap-icons.css'
import 'bootstrap'
import '../../resources/css/shared-navigation.css'
import App from './App.vue'
import router from './router'

createApp(App).use(router).mount('#app')
