<?php

declare(strict_types=1);

namespace OCA\RotatingWallpapers\Http;

use OCP\AppFramework\Http\Response;

class BinaryFileResponse extends Response {

	public function __construct(private string $data, string $mimeType) {
		parent::__construct();
		$this->addHeader('Content-Type', $mimeType);
		$this->addHeader('Content-Disposition', 'inline');
		$this->addHeader('Cache-Control', 'public, max-age=86400');
	}

	public function render(): string {
		return $this->data;
	}
}
