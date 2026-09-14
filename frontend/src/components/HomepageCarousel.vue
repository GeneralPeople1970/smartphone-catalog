<template>
  <div v-if="images.length" id="heroCarousel" ref="element" class="carousel slide carousel-fade">
    <div v-if="images.length > 1" class="carousel-indicators">
      <button
        v-for="(image, index) in images"
        :key="image.id"
        type="button"
        data-bs-target="#heroCarousel"
        :data-bs-slide-to="index"
        :class="{ active: index === 0 }"
        :aria-label="`轮播图 ${index + 1}`"
      />
    </div>
    <div class="carousel-inner">
      <div
        v-for="(image, index) in images"
        :key="image.id"
        class="carousel-item"
        :class="{ active: index === 0 }"
      >
        <component
          :is="safeExternalUrl(image.linkUrl) ? 'a' : 'div'"
          :href="safeExternalUrl(image.linkUrl) || undefined"
        >
          <PhoneImage
            class="carousel-img"
            :src="image.image"
            :alt="image.title || '首页轮播图'"
            width="1600"
            height="450"
            decoding="async"
            :fetchpriority="index === 0 ? 'high' : 'auto'"
          />
        </component>
      </div>
    </div>
    <template v-if="images.length > 1">
      <button
        class="carousel-control-prev"
        type="button"
        data-bs-target="#heroCarousel"
        data-bs-slide="prev"
        aria-label="上一张"
      >
        <span class="carousel-control-prev-icon" aria-hidden="true" />
      </button>
      <button
        class="carousel-control-next"
        type="button"
        data-bs-target="#heroCarousel"
        data-bs-slide="next"
        aria-label="下一张"
      >
        <span class="carousel-control-next-icon" aria-hidden="true" />
      </button>
    </template>
  </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import Carousel from 'bootstrap/js/dist/carousel'
import EventHandler from 'bootstrap/js/dist/dom/event-handler'
import PhoneImage from './PhoneImage.vue'
import { safeExternalUrl } from '@/utils/url.js'

const props = defineProps({ images: { type: Array, default: () => [] } })
const element = ref(null)
let instance
let instanceElement
let instanceSlides = []
function dispose() {
  if (!instance) return

  // The data API can queue another to()/cycle() call for the slid event.
  EventHandler.off(instanceElement, '.bs.carousel')
  // pause() signals the wrapper, but Bootstrap's transition callbacks listen
  // on each slide. Finish them before dispose() clears the instance's element.
  for (const slide of instanceSlides) slide.dispatchEvent(new Event('transitionend'))
  instance.pause()
  window.clearTimeout(instance.touchTimeout)
  instance.dispose()
  instance = null
  instanceElement = null
  instanceSlides = []
}
watch(
  [element, () => props.images],
  () => {
    dispose()
    if (element.value && props.images.length > 1) {
      instanceElement = element.value
      instanceSlides = [...instanceElement.querySelectorAll('.carousel-item')]
      instance = Carousel.getOrCreateInstance(element.value, { ride: 'carousel' })
    }
  },
  { flush: 'post' },
)
onBeforeUnmount(dispose)
</script>

<style scoped>
.carousel {
  margin-top: 24px;
}
.carousel-inner {
  border-radius: 8px;
  overflow: hidden;
}
.carousel-img {
  display: block;
  width: 100%;
  height: 450px;
  object-fit: cover;
}
@media (max-width: 767.98px) {
  .carousel-img {
    height: 260px;
  }
}
</style>
