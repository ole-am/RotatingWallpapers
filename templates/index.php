<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\RotatingWallpapers\AppInfo\Application::APP_ID, OCA\RotatingWallpapers\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\RotatingWallpapers\AppInfo\Application::APP_ID, OCA\RotatingWallpapers\AppInfo\Application::APP_ID . '-main');

?>

<div id="rotatingwallpapers"></div>
