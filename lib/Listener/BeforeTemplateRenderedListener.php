<?php

declare(strict_types=1);

namespace OCA\RotatingWallpapers\Listener;

use OCA\RotatingWallpapers\Service\WallpaperStateService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;

/**
 * @template-implements IEventListener<BeforeTemplateRenderedEvent>
 * @psalm-suppress UnusedClass
 */
class BeforeTemplateRenderedListener implements IEventListener {

	public function __construct(
		private readonly WallpaperStateService $wallpaperState,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		$response = $event->getResponse();
		$renderAs = $response->getRenderAs();

		$isUser  = $renderAs === TemplateResponse::RENDER_AS_USER;
		$isGuest = $renderAs === TemplateResponse::RENDER_AS_GUEST;
		$hasPublicWallpaper = $this->wallpaperState->getPublicWallpaper() !== null;
		$hasImages          = !empty($this->wallpaperState->getImageList());

		$shouldInject = ($isUser && $hasImages) || ($isGuest && ($hasPublicWallpaper || $hasImages));

		if ($shouldInject) {
			// Append a per-page-load cache-buster so the browser always makes a fresh
			// request to /wallpaper and the server-side rotation logic can decide which image to return.
			$bgUrl = $this->urlGenerator->linkToRouteAbsolute('rotatingwallpapers.wallpaper.serve')
				. '?v=' . time();
			$stateUrl = $this->urlGenerator->linkToRouteAbsolute('rotatingwallpapers.wallpaper.state');

			WallpaperInjector::inject($bgUrl, $stateUrl);
		}
	}
}
