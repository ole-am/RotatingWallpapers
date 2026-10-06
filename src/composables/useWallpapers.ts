import { ref } from 'vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export interface Wallpaper {
	filename: string
	url: string
}

export function useWallpapers() {
	const images = ref<Wallpaper[]>([])
	const uploadFileInput = ref<HTMLInputElement | null>(null)

	async function loadWallpapers() {
		const response = await axios.get(generateOcsUrl('apps/rotatingwallpapers/getAllWallpapers'))
		images.value = response.data.ocs.data.wallpapers
	}

	async function uploadImage() {
		if (!uploadFileInput.value?.files?.length) return

		const files = Array.from(uploadFileInput.value.files)
		let errorMsg: string | null = null

		for (const file of files) {
			const formData = new FormData()
			formData.append('file', file)

			try {
				await axios.post(generateOcsUrl('apps/rotatingwallpapers/uploadWallpaper'), formData)
			} catch (e: unknown) {
				const msg = (e as { response?: { data?: { ocs?: { data?: { error?: string } } } } })
					?.response?.data?.ocs?.data?.error ?? 'Upload fehlgeschlagen'
				errorMsg = msg
			}
		}

		try {
			await loadWallpapers()
		} catch {
			// ignore error loading wallpapers
		} finally {
			if (errorMsg) {
				alert(errorMsg)
			}
			if (uploadFileInput.value) uploadFileInput.value.value = ''
		}
	}

	async function removeImage(filename: string, onPublicRemoved: () => void, publicFilename?: string) {
		await axios.delete(
			generateOcsUrl('apps/rotatingwallpapers/deleteWallpaper/' + encodeURIComponent(filename)),
		)
		images.value = images.value.filter(img => img.filename !== filename)
		if (publicFilename === filename) {
			await axios.post(generateOcsUrl('apps/rotatingwallpapers/clearPublicWallpaper'))
			onPublicRemoved()
		}
	}

	return { images, uploadFileInput, loadWallpapers, uploadImage, removeImage }
}
