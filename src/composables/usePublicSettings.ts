import { ref } from 'vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import type { Wallpaper } from './useWallpapers'

export function usePublicSettings() {
	const publicWallpaper = ref<Wallpaper | null>(null)
	const publicUploadFileInput = ref<HTMLInputElement | null>(null)

	async function loadPublicSettings() {
		try {
			const response = await axios.get(generateOcsUrl('apps/rotatingwallpapers/getConfig'))
			const cfg = response.data.ocs.data
			publicWallpaper.value = cfg.publicWallpaper && cfg.publicWallpaperUrl
				? { filename: cfg.publicWallpaper, url: cfg.publicWallpaperUrl }
				: null
		} catch {
			// keep default
		}
	}

	async function uploadPublicWallpaper() {
		if (!publicUploadFileInput.value?.files?.length) return

		const file = publicUploadFileInput.value.files[0] as File
		const formData = new FormData()
		formData.append('file', file)

		try {
			const uploadResp = await axios.post(
				generateOcsUrl('apps/rotatingwallpapers/uploadWallpaper'),
				formData,
			)
			const filename: string = uploadResp.data.ocs.data.filename
			await axios.post(generateOcsUrl('apps/rotatingwallpapers/setPublicWallpaper'), { filename })
			await loadPublicSettings()
		} catch (e: unknown) {
			const msg = (e as { response?: { data?: { ocs?: { data?: { error?: string } } } } })
				?.response?.data?.ocs?.data?.error ?? 'Upload fehlgeschlagen'
			alert(msg)
		} finally {
			if (publicUploadFileInput.value) publicUploadFileInput.value.value = ''
		}
	}

	async function removePublicWallpaper() {
		await axios.post(generateOcsUrl('apps/rotatingwallpapers/clearPublicWallpaper'))
		publicWallpaper.value = null
	}

	return { publicWallpaper, publicUploadFileInput, loadPublicSettings, uploadPublicWallpaper, removePublicWallpaper }
}
