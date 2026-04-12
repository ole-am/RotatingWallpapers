/**
 * Wallpaper crossfade and auto-rotation for Nextcloud user pages.
 * URLs are passed via <meta> tags injected by BeforeTemplateRenderedListener.
 */

const bgUrlMeta = document.querySelector<HTMLMetaElement>('meta[name="rotatingwallpapers-bg-url"]')
const stateUrlMeta = document.querySelector<HTMLMetaElement>('meta[name="rotatingwallpapers-state-url"]')

if (bgUrlMeta && stateUrlMeta) {
	const initialUrl = bgUrlMeta.content
	const stateUrl = stateUrlMeta.content

	const bgA = document.createElement('div')
	bgA.id = 'rotatingwallpapers-bg-a'
	const bgB = document.createElement('div')
	bgB.id = 'rotatingwallpapers-bg-b'
	let active: 'a' | 'b' = 'a'
	let crossfadeInProgress = false
	let currentAbortController: AbortController | null = null

	// Module/deferred scripts run after DOM is parsed — body is always available here.
	document.body.appendChild(bgA)
	document.body.appendChild(bgB)
	bgA.style.backgroundImage = `url(${initialUrl})`
	bgA.style.opacity = '1'
	bgB.style.opacity = '0'
	// Override the !important CSS fallback on body now that our divs are in place.
	// Also clear background-color: Nextcloud sets a solid background-color on body
	// (position:fixed, creates stacking context) which would cover our z-index:-1 divs.
	document.body.style.setProperty('background-image', 'none', 'important')
	document.body.style.setProperty('background-color', 'transparent', 'important')

	function crossfade(newUrl: string): void {
		// Guard against concurrent crossfades corrupting the active/div state.
		if (crossfadeInProgress) return
		crossfadeInProgress = true
		const next = active === 'a' ? bgB : bgA
		const curr = active === 'a' ? bgA : bgB
		const img = new Image()
		img.onload = () => {
			next.style.backgroundImage = `url(${newUrl})`
			next.style.opacity = '1'
			curr.style.opacity = '0'
			active = active === 'a' ? 'b' : 'a'
			crossfadeInProgress = false
		}
		// Release the lock on error so the next scheduled attempt can proceed.
		img.onerror = () => {
			crossfadeInProgress = false
		}
		img.src = newUrl
	}

	function scheduleNext(validUntil: number, rotationMode: string): void {
		if (rotationMode === 'static') return
		// Guard against NaN/Infinity from unexpected API responses.
		const safeValidUntil = Number.isFinite(validUntil) ? validUntil : 0
		const delay = Math.max(1000, safeValidUntil * 1000 - Date.now() + 500)
		setTimeout(async () => {
			// Cancel any still-running fetch from a previous cycle before starting a new one.
			currentAbortController?.abort()
			currentAbortController = new AbortController()
			try {
				const r = await fetch(stateUrl, { signal: currentAbortController.signal })
				const d = await r.json()
				if (d.hasImages && d.imageUrl) crossfade(d.imageUrl as string)
				scheduleNext(d.validUntil as number, d.rotationMode as string)
			} catch (err) {
				// Ignore intentional aborts (new cycle started before this one finished).
				if (err instanceof Error && err.name === 'AbortError') return
				// Retry in 30 s on network error.
				scheduleNext(Math.floor(Date.now() / 1000) + 30, rotationMode)
			}
		}, delay)
	}

	fetch(stateUrl)
		.then(r => r.json())
		.then(d => scheduleNext(d.validUntil as number, d.rotationMode as string))
		.catch(() => { /* no images or network error */ })
}
