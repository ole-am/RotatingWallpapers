<?php

declare(strict_types=1);

namespace OCA\RotatingWallpapers\Controller;

use OCA\RotatingWallpapers\Http\BinaryFileResponse;
use OCA\RotatingWallpapers\Service\WallpaperStateService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * @psalm-suppress UnusedClass
 */
class WallpaperController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly WallpaperStateService $wallpaperState,
		private readonly IUserSession $userSession,
		private readonly IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Return the current wallpaper state (image URL + timing) for client-side polling.
	 * Guests receive the static public image state (if configured) or the normal rotation state.
	 *
	 * @return JSONResponse
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/wallpaperState')]
	public function state(): JSONResponse {
		$isGuest         = !$this->userSession->isLoggedIn();
		$publicWallpaper = $this->wallpaperState->getPublicWallpaper();

		if ($isGuest && $publicWallpaper !== null) {
			return new JSONResponse([
				'hasImages'    => true,
				'imageUrl'     => $this->wallpaperState->getPublicWallpaperUrl(),
				'validUntil'   => 0,
				'rotationMode' => 'static',
			]);
		}

		$images = $this->wallpaperState->getImageList();
		if (empty($images)) {
			return new JSONResponse(['hasImages' => false]);
		}

		// Triggers server-side rotation if the current interval has expired
		$this->wallpaperState->getCurrentImage();
		$timing = $this->wallpaperState->getTimingState();

		return new JSONResponse([
			'hasImages'    => true,
			'imageUrl'     => $this->urlGenerator->linkToRoute('rotatingwallpapers.wallpaper.serve') . '?v=' . time(),
			'validUntil'   => $timing['validUntil'],
			'rotationMode' => $timing['rotationMode'],
		]);
	}

	/**
	 * Serve the current rotating background image.
	 * Guests receive the static public image (if configured) or the current rotation image.
	 *
	 * @return RedirectResponse|NotFoundResponse
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/wallpaper')]
	public function serve(): RedirectResponse|NotFoundResponse {
		$isGuest         = !$this->userSession->isLoggedIn();
		$publicWallpaper = $this->wallpaperState->getPublicWallpaper();

		if ($isGuest && $publicWallpaper !== null) {
			$response = new RedirectResponse($this->wallpaperState->getPublicWallpaperUrl(), Http::STATUS_TEMPORARY_REDIRECT);
			$response->addHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
			$response->addHeader('Pragma', 'no-cache');
			return $response;
		}

		$filename = $this->wallpaperState->getCurrentImage();

		if ($filename === null) {
			return new NotFoundResponse();
		}

		$response = new RedirectResponse($this->wallpaperState->getImageUrl($filename), Http::STATUS_TEMPORARY_REDIRECT);
		$response->addHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
		$response->addHeader('Pragma', 'no-cache');
		return $response;
	}

	/**
	 * Serve a rotation wallpaper image from AppData by filename.
	 *
	 * @param string $filename
	 * @return BinaryFileResponse|NotFoundResponse
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/wallpaperImage/{filename}')]
	public function serveWallpaper(string $filename): BinaryFileResponse|NotFoundResponse {
		if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..')) {
			return new NotFoundResponse();
		}

		$wallpapersDir = $this->wallpaperState->getWallpapersDir();
		$filePath      = $wallpapersDir . $filename;

		if (!is_file($filePath)) {
			return new NotFoundResponse();
		}

		$realPath = realpath($filePath);
		$realDir  = realpath(rtrim($wallpapersDir, '/'));
		if ($realPath === false || $realDir === false || !str_starts_with($realPath, $realDir . DIRECTORY_SEPARATOR)) {
			return new NotFoundResponse();
		}

		$data = @file_get_contents($realPath);
		if ($data === false) {
			return new NotFoundResponse();
		}

		$finfo    = new \finfo(FILEINFO_MIME_TYPE);
		$mimeType = $finfo->file($realPath) ?: 'application/octet-stream';

		return new BinaryFileResponse($data, $mimeType);
	}

	/**
	 * Serve the public (guest/login) wallpaper from AppData.
	 *
	 * @return BinaryFileResponse|NotFoundResponse
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/publicWallpaperImage')]
	public function servePublicWallpaper(): BinaryFileResponse|NotFoundResponse {
		$filePath = $this->wallpaperState->getPublicWallpaperPath();

		if (!is_file($filePath)) {
			return new NotFoundResponse();
		}

		$data = @file_get_contents($filePath);
		if ($data === false) {
			return new NotFoundResponse();
		}

		$finfo    = new \finfo(FILEINFO_MIME_TYPE);
		$mimeType = $finfo->file($filePath) ?: 'application/octet-stream';

		return new BinaryFileResponse($data, $mimeType);
	}
}
