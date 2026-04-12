<script setup lang="ts">
defineProps<{
	src: string
}>()

const emit = defineEmits<{
	remove: []
}>()
</script>

<template>
	<div class="wallpaper-image">
		<img :src="src">
		<button class="wallpaper-image__remove" @click="emit('remove')">
			<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 10 10">
				<line x1="1" y1="1" x2="9" y2="9" stroke="white" stroke-width="2" stroke-linecap="round"/>
				<line x1="9" y1="1" x2="1" y2="9" stroke="white" stroke-width="2" stroke-linecap="round"/>
			</svg>
		</button>
	</div>
</template>

