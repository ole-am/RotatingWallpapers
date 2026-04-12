<?php

declare(strict_types=1);

use OCA\RotatingWallpapers\AppInfo\Application;
use OCP\Util;

Util::addScript(Application::APP_ID, Application::APP_ID . '-admin');
Util::addStyle(Application::APP_ID, Application::APP_ID . '-admin');
?>

<div id="rotatingwallpapers-admin"></div>
