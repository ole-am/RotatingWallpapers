<?php

declare(strict_types=1);

namespace OCA\RotatingWallpapers\Controller;

use OCA\RotatingWallpapers\Service\WallpaperStateService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class AdminController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly WallpaperStateService $wallpaperState,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all wallpaper images
	 *
	 * @return DataResponse<Http::STATUS_OK, array{wallpapers: list<array{filename: string, url: string}>}, array{}>
	 *
	 * 200: Wallpapers returned
	 */
	#[ApiRoute(verb: 'GET', url: '/getAllWallpapers')]
	public function getAllWallpapers(): DataResponse {
		$wallpapersDir = $this->wallpaperState->getWallpapersDir();

		if (!is_dir($wallpapersDir)) {
			return new DataResponse(['wallpapers' => []]);
		}

		$publicWallpaper   = $this->wallpaperState->getPublicWallpaper();
		$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
		$wallpapers        = [];

		$files = scandir($wallpapersDir);
		if ($files === false) {
			return new DataResponse(['wallpapers' => []]);
		}

		foreach ($files as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			if ($file === $publicWallpaper) {
				continue;
			}
			$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
			if (!in_array($ext, $allowedExtensions, true)) {
				continue;
			}
			$wallpapers[] = [
				'filename' => $file,
				'url' => $this->wallpaperState->getImageUrl($file),
			];
		}

		return new DataResponse(['wallpapers' => $wallpapers]);
	}

	/**
	 * Upload a wallpaper
	 *
	 * @return DataResponse<Http::STATUS_OK, array{filename: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Image uploaded successfully
	 * 400: Invalid file
	 */
	#[ApiRoute(verb: 'POST', url: '/uploadWallpaper')]
	public function uploadWallpaper(): DataResponse {
		$file = $this->request->getUploadedFile('file');

		if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
			return new DataResponse(['error' => 'Image upload failed. Please check the PHP configuration (upload_max_filesize / post_max_size).'], Http::STATUS_BAD_REQUEST);
		}

		$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$detectedMime = $finfo->file($file['tmp_name']);
		if ($detectedMime === false || !in_array($detectedMime, $allowedMimeTypes, true)) {
			return new DataResponse(['error' => 'Invalid file type. Only jpg, png and webp-files are allowed.'], Http::STATUS_BAD_REQUEST);
		}

		$extensionMap = [
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/webp' => 'webp',
		];
		$extension = $extensionMap[$detectedMime];

		$wallpapersDir = $this->wallpaperState->getWallpapersDir();
		if (!is_dir($wallpapersDir)) {
			mkdir($wallpapersDir, 0755, true);
		}

		$uniqueFilename = bin2hex(random_bytes(16)) . '.' . $extension;
		$destination = $wallpapersDir . $uniqueFilename;

		if (!move_uploaded_file($file['tmp_name'], $destination)) {
			return new DataResponse(['error' => 'Failed to save file'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse(['filename' => $uniqueFilename]);
	}

	/**
	 * Delete a wallpaper
	 *
	 * @param string $filename The filename to delete
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Image deleted successfully
	 * 400: File not found or invalid
	 */
	#[ApiRoute(verb: 'DELETE', url: '/deleteWallpaper/{filename}')]
	public function deleteWallpaper(string $filename): DataResponse {
		if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..')) {
			return new DataResponse(['error' => 'Invalid filename'], Http::STATUS_BAD_REQUEST);
		}

		$wallpapersDir = $this->wallpaperState->getWallpapersDir();
		$filePath = $wallpapersDir . $filename;

		if (!file_exists($filePath)) {
			return new DataResponse(['error' => 'File not found'], Http::STATUS_BAD_REQUEST);
		}

		$realPath = realpath($filePath);
		$realDir  = realpath($wallpapersDir);
		if ($realPath === false || $realDir === false || !str_starts_with($realPath, $realDir . DIRECTORY_SEPARATOR)) {
			return new DataResponse(['error' => 'Invalid file path'], Http::STATUS_BAD_REQUEST);
		}

		unlink($filePath);

		return new DataResponse([]);
	}

	/**
	 * Get app configuration
	 *
	 * @return DataResponse<Http::STATUS_OK, array{rotationMode: string}, array{}>
	 *
	 * 200: Config returned
	 */
	#[ApiRoute(verb: 'GET', url: '/getConfig')]
	public function getConfig(): DataResponse {
		$settings = $this->wallpaperState->getSettings();
		if ($settings['publicWallpaper'] !== '') {
			$settings['publicWallpaperUrl'] = $this->wallpaperState->getPublicWallpaperUrl();
		}
		return new DataResponse($settings);
	}

	/**
	 * Save app configuration
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: Config saved
	 */
	#[ApiRoute(verb: 'POST', url: '/saveConfig')]
	public function saveConfig(): DataResponse {
		$rotationMode = $this->request->getParam('rotationMode', 'every1h');

		$validModes = $this->wallpaperState->getValidModes();
		if (!in_array($rotationMode, $validModes, true)) {
			$rotationMode = 'every1h';
		}

		$this->wallpaperState->saveSettings($rotationMode);

		return new DataResponse([]);
	}

	/**
	 * Set the public (guest) wallpaper
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Public wallpaper set
	 * 400: Invalid filename
	 */
	#[ApiRoute(verb: 'POST', url: '/setPublicWallpaper')]
	public function setPublicWallpaper(): DataResponse {
		$filename = (string)$this->request->getParam('filename', '');

		if ($filename === '') {
			return new DataResponse(['error' => 'No filename provided'], Http::STATUS_BAD_REQUEST);
		}

		if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..')) {
			return new DataResponse(['error' => 'Invalid filename'], Http::STATUS_BAD_REQUEST);
		}

		$wallpapersDir = $this->wallpaperState->getWallpapersDir();
		$filePath = $wallpapersDir . $filename;
		if (!file_exists($filePath)) {
			return new DataResponse(['error' => 'File not found'], Http::STATUS_BAD_REQUEST);
		}

		$realPath = realpath($filePath);
		$realDir  = realpath($wallpapersDir);
		if ($realPath === false || $realDir === false || !str_starts_with($realPath, $realDir . DIRECTORY_SEPARATOR)) {
			return new DataResponse(['error' => 'Invalid file path'], Http::STATUS_BAD_REQUEST);
		}

		$this->wallpaperState->setPublicWallpaper($filename);

		return new DataResponse([]);
	}

	/**
	 * Clear the public (guest) wallpaper
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: Public wallpaper cleared
	 */
	#[ApiRoute(verb: 'POST', url: '/clearPublicWallpaper')]
	public function clearPublicWallpaper(): DataResponse {
		$this->wallpaperState->setPublicWallpaper(null);
		return new DataResponse([]);
	}
}
