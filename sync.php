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
const RETRY_BACKOFF_S = 6 * 3600; // don't retry a failed fetch more often than this

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
 * Marks a screenshot fetch as failed, so it gets retried later instead of being
 * treated as permanently done, and renders a placeholder in the meantime.
 *
 * @param string $cacheUrl path to screenshot in cache
 * @param string $failMarker path to the retry marker for this screenshot
 * @param string $url HTTP URL of screenshot
 * @param string $message warning message to display
 */
function markFailedFetch(string $cacheUrl, string $failMarker, string $url, string $message): void {
	touch($failMarker);
	generateWarningImage($cacheUrl, $url, $message);
}

/**
 * @param UrlValidator $validator
 * @param array $screenshot decoded JSON of a single screenshot entry
 */
function handleScreenshot(UrlValidator $validator, array $screenshot): void {
	$url = $screenshot['url'];
	$trimmedUrl = trim($url);

	if (!str_starts_with($trimmedUrl, 'https://')) {
		return;
	}

	$cacheUrl = __DIR__ . '/cache/' . base64_encode($url);
	$failMarker = __DIR__ . '/cache-failed/' . base64_encode($url);

	if (file_exists($cacheUrl)) {
		if (!file_exists($failMarker)) {
			// Already fetched successfully, nothing to do
			return;
		}

		if ((time() - filemtime($failMarker)) < RETRY_BACKOFF_S) {
			// Fetch failed recently, don't hammer the origin - try again later
			return;
		}

		// Backoff has elapsed, fall through and retry the fetch
	}

	if (!is_dir(dirname($failMarker))) {
		mkdir(dirname($failMarker), 0755, true);
	}

	try {
		$validator->validate($url);
	} catch (UrlValidationException $e) {
		markFailedFetch($cacheUrl, $failMarker, $url, 'Failed to fetch image');
		return;
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
		markFailedFetch($cacheUrl, $failMarker, $url, 'Failed to fetch image');
		return;
	}

	if (strlen($data) > MAX_SCREENSHOT_SIZE) {
		markFailedFetch($cacheUrl, $failMarker, $url, 'Image exceeds file size limit');
		return;
	}

	$tempUrl = tempnam(sys_get_temp_dir(), 'screenshot');
	if ($tempUrl === false) {
		// This should never happen, but just in case, treat this as an internal error
		// Do nothing for now and try again later
		return;
	}

	file_put_contents($tempUrl, $data);

	$mimeType = mime_content_type($tempUrl);
	if ($mimeType === false || !str_starts_with($mimeType, 'image/')) {
		unlink($tempUrl);
		markFailedFetch($cacheUrl, $failMarker, $url, 'Image not recognized');
		return;
	}

	rename($tempUrl, $cacheUrl);

	if (file_exists($failMarker)) {
		unlink($failMarker);
	}

	echo(sprintf("Synced url %s\n", $url));
}

/**
 * @param array $apps decoded JSON from appstore
 */
function handleApps(array $apps): void {
	$validator = new UrlValidator();

	foreach ($apps as $app) {
		foreach ($app['screenshots'] as $screenshot) {
			try {
				handleScreenshot($validator, $screenshot);
			} catch (\Throwable $e) {
				// A single broken screenshot must never abort the whole sync run
				echo(sprintf("Error while syncing %s: %s\n", $screenshot['url'] ?? '?', $e->getMessage()));
			}
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
