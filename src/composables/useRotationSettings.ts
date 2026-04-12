import { ref, watch } from 'vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export type RotationMode = 'every10sec' | 'every5min' | 'every30min' | 'every1h' | 'every3h' | 'daily'

export function useRotationSettings() {
	const rotationMode = ref<RotationMode>('every1h')

	async function loadRotationSettings() {
		try {
			const response = await axios.get(generateOcsUrl('apps/rotatingwallpapers/getConfig'))
			rotationMode.value = response.data.ocs.data.rotationMode ?? 'every1h'
		} catch {
			// keep default
		}
	}

	async function saveRotationSettings() {
		try {
			await axios.post(generateOcsUrl('apps/rotatingwallpapers/saveConfig'), {
				rotationMode: rotationMode.value,
			})
		} catch (e: unknown) {
			const msg = (e as { response?: { data?: { ocs?: { data?: { error?: string } } } } })
				?.response?.data?.ocs?.data?.error ?? 'Speichern fehlgeschlagen'
			console.error('Config save error:', msg)
		}
	}

	watch(rotationMode, saveRotationSettings)

	return { rotationMode, loadRotationSettings }
}
