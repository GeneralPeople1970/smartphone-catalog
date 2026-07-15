import { createApp } from 'vue'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap-icons/font/bootstrap-icons.css'
// Bootstrap's JS bundle is intentionally NOT imported here: the only component
// that needs JS is the homepage carousel, which imports the single Carousel
// plugin directly (see views/Home.vue). The mobile menu and theme panel are
// plain Vue. This keeps the whole Bootstrap JS bundle out of the initial load.
import '../../resources/css/shared-navigation.css'
import App from './App.vue'
import router from './router'

createApp(App).use(router).mount('#app')
