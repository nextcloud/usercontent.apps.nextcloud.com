#!/usr/bin/env php

<?php

/**
 * SPDX-FileCopyrightText: 2016-2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

if (php_sapi_name() !== 'cli') {
	die('Can only be invoked from CLI');
}

$allVersionsJson = file_get_contents('https://apps.nextcloud.com/api/v1/platforms.json');

if ($allVersionsJson === false) {
	die('Unable to read platforms.json');
}

$allVersions = json_decode($allVersionsJson, true);
$supportedVersionObjects = array_filter($allVersions, fn (array $ver): bool => $ver['isSupported'] && str_ends_with($ver['version'], '.0.0'));
$supportedVersions = array_map(fn (array $ver): string => $ver['version'], $supportedVersionObjects);

const MAX_SCREENSHOT_SIZE = 1e7; // 10 MB

/**
 * @param array $apps decoded JSON from appstore
 */
function handleApps(array $apps): void {
	foreach ($apps as $app) {
		foreach ($app['screenshots'] as $screenshot) {
			$url = $screenshot['url'];
			$cacheUrl = __DIR__ . '/cache/' . base64_encode($url);

			if (file_exists($cacheUrl)) {
				continue;
			}

			$trimmedUrl = trim($url);
			if (!str_starts_with($trimmedUrl, 'https://')) {
				continue;
			}

			$data = file_get_contents($trimmedUrl);
			file_put_contents($cacheUrl, $data);

			$mimeType = mime_content_type($cacheUrl);
			if (!str_starts_with($mimeType, 'image/')) {
				// Replace unknown data with a warning image
				$data = imagecreatetruecolor(640, 360);

				if ($data === false) {
					// This should never happen, but just in case, replace unknown data with an empty file instead
					file_put_contents($cacheUrl, '');
					echo(sprintf("Synced url %s (image not recognized, unable to generate warning image)\n", $url));
					continue;
				}

				$textColorError = imagecolorallocate($data, 255, 0, 0); // red
				$textColorNormal = imagecolorallocate($data, 255, 255, 255); // white
				imagestring($data, 5, 8, 150, 'Preview not available', $textColorError);
				imagestring($data, 5, 8, 170, 'Image not recognized', $textColorNormal);
				imagestring($data, 5, 8, 190, basename($url), $textColorNormal);

				imagepng($data, $cacheUrl);
				echo(sprintf("Synced url %s (image not recognized)\n", $url));
				continue;
			}

			if (filesize($cacheUrl) > MAX_SCREENSHOT_SIZE) {
				// Replace large image with a warning image
				$data = imagecreatetruecolor(640, 360);

				if ($data === false) {
					// This should never happen, but just in case, replace large image with an empty file instead
					file_put_contents($cacheUrl, '');
					echo(sprintf("Synced url %s (image exceeds file size limit, unable to generate warning image)\n", $url));
					continue;
				}

				$textColorError = imagecolorallocate($data, 255, 0, 0); // red
				$textColorNormal = imagecolorallocate($data, 255, 255, 255); // white
				imagestring($data, 5, 8, 150, 'Preview not available', $textColorError);
				imagestring($data, 5, 8, 170, 'Image exceeds 10MB file size limit', $textColorNormal);
				imagestring($data, 5, 8, 190, basename($url), $textColorNormal);

				imagepng($data, $cacheUrl);
				echo(sprintf("Synced url %s (image exceeds file size limit)\n", $url));
				continue;
			}

			echo(sprintf("Synced url %s\n", $url));
		}
	}
}

foreach($supportedVersions as $version) {
	$json = file_get_contents(
		sprintf('https://apps.nextcloud.com/api/v1/platform/%s/apps.json', $version)
	);

	if ($json === false) {
		echo(sprintf("Unable to read apps.json for version %s\n", $version));
		continue;
	}

	$apps = json_decode($json, true);
    handleApps($apps);
}

$json = file_get_contents('https://apps.nextcloud.com/api/v1/appapi_apps.json');

if ($json === false) {
	die('Unable to read appapi_apps.json');
}

$apps = json_decode($json, true);
handleApps($apps);
