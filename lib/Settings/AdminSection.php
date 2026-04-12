<?php

declare(strict_types=1);

namespace OCA\RotatingWallpapers\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	public function __construct(
		private readonly IL10N $l,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return 'rotatingwallpapers';
	}

	public function getName(): string {
		return $this->l->t('Rotating Wallpapers');
	}

	public function getPriority(): int {
		return 10;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('rotatingwallpapers', 'app_admin.svg');
	}
}
