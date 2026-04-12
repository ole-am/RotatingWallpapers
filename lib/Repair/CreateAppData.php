<?php

declare(strict_types=1);

namespace OCA\RotatingWallpapers\Repair;

use OCA\RotatingWallpapers\AppInfo\Application;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class CreateAppData implements IRepairStep {

	public function __construct(private readonly IConfig $config) {
	}

	public function getName(): string {
		return 'Create RotatingWallpapers AppData directories';
	}

	public function run(IOutput $output): void {
		$basePath      = $this->getAppDataBasePath();
		$wallpapersDir = $basePath . '/wallpapers';

		if (!is_dir($wallpapersDir)) {
			if (!@mkdir($wallpapersDir, 0770, true) && !is_dir($wallpapersDir)) {
				$output->warning('Could not create wallpapers directory: ' . $wallpapersDir);
				return;
			}
		}

		$configPath = $basePath . '/config.json';
		if (!is_file($configPath)) {
			$defaults = ['rotationMode' => 'every1h', 'currentImage' => '', 'validUntil' => 0];
			@file_put_contents($configPath, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		}
	}

	private function getAppDataBasePath(): string {
		$dataDir    = rtrim((string) $this->config->getSystemValue('datadirectory', ''), "/\\");
		$instanceId = (string) $this->config->getSystemValue('instanceid', '');

		return $dataDir . '/appdata_' . $instanceId . '/' . Application::APP_ID;
	}
}
