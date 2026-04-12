<?php

declare(strict_types=1);

namespace OCA\RotatingWallpapers\Listener;

use OCA\RotatingWallpapers\Service\WallpaperStateService;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;

/**
 * @template-implements IEventListener<BeforeLoginTemplateRenderedEvent>
 * @psalm-suppress UnusedClass
 */
class BeforeLoginTemplateRenderedListener implements IEventListener {

	public function __construct(
		private readonly WallpaperStateService $wallpaperState,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeLoginTemplateRenderedEvent)) {
			return;
		}

		$hasPublicWallpaper = $this->wallpaperState->getPublicWallpaper() !== null;
		$hasImages          = !empty($this->wallpaperState->getImageList());

		if (!($hasPublicWallpaper || $hasImages)) {
			return;
		}

		$bgUrl    = $this->urlGenerator->linkToRouteAbsolute('rotatingwallpapers.wallpaper.serve')
			. '?v=' . time();
		$stateUrl = $this->urlGenerator->linkToRouteAbsolute('rotatingwallpapers.wallpaper.state');

		WallpaperInjector::inject($bgUrl, $stateUrl);
	}
}
