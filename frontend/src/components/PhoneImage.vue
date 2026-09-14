<template>
  <img :src="resolved" :alt="alt" @error="failed = true" />
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { imageOrPlaceholder, PLACEHOLDER_IMAGE } from '@/utils/image.js'

const props = defineProps({
  src: { type: [String, Number], default: '' },
  alt: { type: String, default: '' },
})
const failed = ref(false)
const resolved = computed(() => (failed.value ? PLACEHOLDER_IMAGE : imageOrPlaceholder(props.src)))
watch(
  () => props.src,
  () => {
    failed.value = false
  },
)
</script>
