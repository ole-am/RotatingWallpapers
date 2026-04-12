<?php

declare(strict_types=1);

namespace OCA\RotatingWallpapers\Listener;

use OCA\RotatingWallpapers\AppInfo\Application;
use OCP\Util;

class WallpaperInjector {

	public static function inject(string $bgUrl, string $stateUrl): void {
		// CSS: body background as instant fallback (before JS runs) + two crossfade layers.
		// url() quotes are omitted intentionally – Util::addHeader HTML-escapes quotes,
		// which would break the CSS. Unquoted url() is valid as long as the URL has no
		// spaces or special chars (our paths never do).
		Util::addHeader('style', [], implode('', [
			'body {',
			' background-image: url(' . $bgUrl . ') !important;',
			' background-size: cover !important;',
			' background-position: center !important;',
			'}',
			'#rotatingwallpapers-bg-a,#rotatingwallpapers-bg-b {',
			' position: fixed; top: 0; left: 0; width: 100%; height: 100%;',
			' background-size: cover; background-position: center; background-repeat: no-repeat;',
			' z-index: -1; transition: opacity 1s ease-in-out; pointer-events: none;',
			'}',
		]));

		// Pass URLs to the compiled JS via <meta> tags (safe: no inline script needed).
		Util::addHeader('meta', [
			'name'    => 'rotatingwallpapers-bg-url',
			'content' => $bgUrl,
		]);
		Util::addHeader('meta', [
			'name'    => 'rotatingwallpapers-state-url',
			'content' => $stateUrl,
		]);

		Util::addScript(Application::APP_ID, Application::APP_ID . '-wallpaper');
	}
}
