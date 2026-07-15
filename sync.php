#!/usr/bin/env php

<?php

/**
 * SPDX-FileCopyrightText: 2016-2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

include_once __DIR__ . '/vendor/autoload.php';

use CraftCms\UrlValidator\UrlValidationException;
use CraftCms\UrlValidator\UrlValidator;

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

const MAX_SCREENSHOT_SIZE = 2 * 1024 * 1024; // 2 MiB
const HTTP_TIMEOUT_S  = 30;

/**
 * @param string $cacheUrl path to screenshot in cache
 * @param string $url HTTP URL of screenshot
 * @param string $message warning message to display
 */
function generateWarningImage(string $cacheUrl, string $url, string $message): void {
	$data = imagecreatetruecolor(640, 360);

	if ($data === false) {
		// This should never happen, but just in case, replace current cache data with an empty file instead
		file_put_contents($cacheUrl, '');
		echo(sprintf("Synced url %s (%s, unable to generate warning image)\n", $url, $message));
		return;
	}

	$textColorError = imagecolorallocate($data, 255, 0, 0); // red
	$textColorNormal = imagecolorallocate($data, 255, 255, 255); // white
	imagestring($data, 5, 8, 150, 'Preview not available', $textColorError);
	imagestring($data, 5, 8, 170, $message, $textColorNormal);
	imagestring($data, 5, 8, 190, basename($url), $textColorNormal);

	imagepng($data, $cacheUrl);
	echo(sprintf("Synced url %s (%s)\n", $url, $message));
}

/**
 * @param array $apps decoded JSON from appstore
 */
function handleApps(array $apps): void {
	$validator = new UrlValidator();

	foreach ($apps as $app) {
		foreach ($app['screenshots'] as $screenshot) {
			$url = $screenshot['url'];
			$trimmedUrl = trim($url);

			if (!str_starts_with($trimmedUrl, 'https://')) {
				continue;
			}

			$cacheUrl = __DIR__ . '/cache/' . base64_encode($url);

			if (file_exists($cacheUrl)) {
				continue;
			}

			try {
				$validator->validate($url);
			} catch (UrlValidationException $e) {
				generateWarningImage($cacheUrl, $url, 'Failed to fetch image');
				continue;
			}

			$ctx = stream_context_create([
				'http' => [
					'timeout' => HTTP_TIMEOUT_S,
					'max_redirects' => 3,
					'user_agent' => 'nextcloud-usercontent-sync/1.0',
				],
			]);

			$data = @file_get_contents($trimmedUrl, false, $ctx, 0, MAX_SCREENSHOT_SIZE + 1);

			if ($data === false) {
				generateWarningImage($cacheUrl, $url, 'Failed to fetch image');
				continue;
			}

			if (strlen($data) > MAX_SCREENSHOT_SIZE) {
				generateWarningImage($cacheUrl, $url, 'Image exceeds file size limit');
				continue;
			}

			$tempUrl = tempnam(sys_get_temp_dir(), 'screenshot');
			if ($tempUrl === false) {
				// This should never happen, but just in case, treat this as an internal error
				// Do nothing for now and try again later
				continue;
			}

			file_put_contents($tempUrl, $data);

			$mimeType = mime_content_type($tempUrl);
			if (!str_starts_with($mimeType, 'image/')) {
				unlink($tempUrl);
				generateWarningImage($cacheUrl, $url, 'Image not recognized');
				continue;
			}

			rename($tempUrl, $cacheUrl);
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
