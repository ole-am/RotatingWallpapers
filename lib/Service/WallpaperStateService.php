<?php

declare(strict_types=1);

namespace OCA\RotatingWallpapers\Service;

use OCA\RotatingWallpapers\AppInfo\Application;
use OCP\IConfig;
use OCP\IURLGenerator;

class WallpaperStateService {

	public function __construct(
		private readonly IURLGenerator $urlGenerator,
		private readonly IConfig $config,
	) {
	}

	// -------------------------------------------------------------------------
	// Path helpers
	// -------------------------------------------------------------------------

	public function getAppDataBasePath(): string {
		$dataDir    = rtrim((string) $this->config->getSystemValue('datadirectory', ''), "/\\");
		$instanceId = (string) $this->config->getSystemValue('instanceid', '');

		return $dataDir . '/appdata_' . $instanceId . '/' . Application::APP_ID;
	}

	public function getWallpapersDir(): string {
		return $this->getAppDataBasePath() . '/wallpapers/';
	}

	public function getPublicWallpaperPath(): string {
		return $this->getAppDataBasePath() . '/publicWallpaper.png';
	}

	public function getConfigPath(): string {
		return $this->getAppDataBasePath() . '/config.json';
	}

	// -------------------------------------------------------------------------
	// Config I/O
	// -------------------------------------------------------------------------

	/** @return array{rotationMode: string, currentImage: string, validUntil: int} */
	private function readConfig(): array {
		$defaults = ['rotationMode' => 'every1h', 'currentImage' => '', 'validUntil' => 0];
		$path     = $this->getConfigPath();
		if (!is_file($path)) {
			return $defaults;
		}
		$raw = @file_get_contents($path);
		if ($raw === false) {
			return $defaults;
		}
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return $defaults;
		}
		return array_merge($defaults, $decoded);
	}

	/** @param array<string, mixed> $config */
	private function writeConfig(array $config): void {
		$path = $this->getConfigPath();
		@file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	// -------------------------------------------------------------------------
	// Public settings API (used by AdminController)
	// -------------------------------------------------------------------------

	public function getTimingState(): array {
		$cfg = $this->readConfig();
		return [
			'validUntil'   => (int) $cfg['validUntil'],
			'rotationMode' => (string) $cfg['rotationMode'],
		];
	}

	public function getSettings(): array {
		$cfg             = $this->readConfig();
		$publicWallpaper = $this->getPublicWallpaper();
		return [
			'rotationMode'   => (string) $cfg['rotationMode'],
			'publicWallpaper' => $publicWallpaper ?? '',
		];
	}

	public function getPublicWallpaper(): ?string {
		return is_file($this->getPublicWallpaperPath()) ? 'publicWallpaper.png' : null;
	}

	public function setPublicWallpaper(?string $filename): void {
		if ($filename === null) {
			@unlink($this->getPublicWallpaperPath());
			return;
		}

		$source = $this->getWallpapersDir() . $filename;
		@rename($source, $this->getPublicWallpaperPath());
	}

	public function saveSettings(string $rotationMode): void {
		$cfg         = $this->readConfig();
		$currentMode = (string) $cfg['rotationMode'];

		// Reset the rotation timer whenever the mode changes so the new interval
		// takes effect on the very next page load instead of waiting out the old one.
		if ($currentMode !== $rotationMode) {
			$cfg['currentImage'] = '';
			$cfg['validUntil']   = 0;
		}

		$cfg['rotationMode'] = $rotationMode;
		$this->writeConfig($cfg);
	}

	// -------------------------------------------------------------------------
	// Rotation modes
	// -------------------------------------------------------------------------

	/** @return list<string> */
	public function getValidModes(): array {
		return ['every10sec', 'every5min', 'every30min', 'every1h', 'every3h', 'daily'];
	}

	public function getIntervalFor(string $mode): int {
		return match ($mode) {
			'every10sec' => 10,
			'every5min'  => 300,
			'every30min' => 1800,
			'every1h'    => 3600,
			'every3h'    => 10800,
			'daily'      => 86400,
			default      => 0,
		};
	}

	// -------------------------------------------------------------------------
	// Image list
	// -------------------------------------------------------------------------

	/** @return list<string> */
	public function getImageList(): array {
		$dir = $this->getWallpapersDir();
		if (!is_dir($dir)) {
			return [];
		}
		$files = scandir($dir);
		if ($files === false) {
			return [];
		}
		$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
		$images  = [];
		foreach ($files as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
			if (in_array($ext, $allowed, true)) {
				$images[] = $file;
			}
		}
		return $images;
	}

	// -------------------------------------------------------------------------
	// Core rotation logic
	// -------------------------------------------------------------------------

	public function getCurrentImage(): ?string {
		$images = $this->getImageList();
		if (empty($images)) {
			return null;
		}

		$cfg  = $this->readConfig();
		$mode = (string) $cfg['rotationMode'];

		$storedImage = (string) $cfg['currentImage'];
		$validUntil  = (int) $cfg['validUntil'];

		if ($validUntil > time() && $storedImage !== '' && in_array($storedImage, $images, true)) {
			return $storedImage;
		}

		$newImage            = $this->pickNewImage($images, $storedImage);
		$cfg['currentImage'] = $newImage;
		$cfg['validUntil']   = time() + $this->getIntervalFor($mode);
		$this->writeConfig($cfg);

		return $newImage;
	}

	public function getImageUrl(string $filename): string {
		return $this->urlGenerator->linkToRoute(
			'rotatingwallpapers.wallpaper.serveWallpaper',
			['filename' => $filename],
		);
	}

	public function getPublicWallpaperUrl(): string {
		$path  = $this->getPublicWallpaperPath();
		$mtime = is_file($path) ? (int) filemtime($path) : time();
		return $this->urlGenerator->linkToRoute('rotatingwallpapers.wallpaper.servePublicWallpaper') . '?v=' . $mtime;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/** @param list<string> $images */
	private function pickNewImage(array $images, string $previousImage): string {
		if (count($images) === 1) {
			return $images[0];
		}
		$candidates = array_values(array_filter($images, fn (string $f) => $f !== $previousImage));
		return $candidates[array_rand($candidates)];
	}
}
