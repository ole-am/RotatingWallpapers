<?php

declare(strict_types=1);

namespace OCA\RotatingWallpapers\Repair;

use OCA\RotatingWallpapers\AppInfo\Application;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ClearAppData implements IRepairStep {

	public function __construct(private readonly IConfig $config) {
	}

	public function getName(): string {
		return 'Clear RotatingWallpapers AppData';
	}

	public function run(IOutput $output): void {
		$appDataPath = $this->getAppDataBasePath();
		if (!is_dir($appDataPath)) {
			return;
		}

		$this->deleteRecursively($appDataPath);
	}

	private function getAppDataBasePath(): string {
		$dataDir    = rtrim((string) $this->config->getSystemValue('datadirectory', ''), "/\\");
		$instanceId = (string) $this->config->getSystemValue('instanceid', '');

		if ($dataDir === '' || $instanceId === '') {
			throw new \RuntimeException('Failed to resolve Nextcloud appdata path.');
		}

		return $dataDir . '/appdata_' . $instanceId . '/' . Application::APP_ID;
	}

	private function deleteRecursively(string $path): void {
		if (is_link($path) || is_file($path)) {
			@unlink($path);
			return;
		}

		$items = @scandir($path);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$this->deleteRecursively($path . '/' . $item);
		}

		@rmdir($path);
	}
}
