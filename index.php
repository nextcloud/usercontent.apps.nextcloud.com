<?php

/**
 * SPDX-FileCopyrightText: 2016-2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

header('Content-Security-Policy: default-src \'none\'');
header('X-Frame-Options: deny');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=15768000; includeSubDomains; preload');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment');

$cacheItem = substr($_SERVER['REQUEST_URI'], 1);
$realPath = realpath(__DIR__ . '/cache/' . $cacheItem);
$cacheDir = realpath(__DIR__ . '/cache/');

if ($realPath === false || strpos($realPath, $cacheDir . DIRECTORY_SEPARATOR) !== 0) {
	die('File not found');
}

header('Expires: Sun, 17 Jan 2038 19:14:07 GMT');
echo file_get_contents($realPath);
