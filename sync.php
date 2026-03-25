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
$allVersions = json_decode($allVersionsJson, true);
$supportedVersionObjects = array_filter($allVersions, fn (array $ver): bool => $ver['isSupported'] && str_ends_with($ver['version'], '.0.0'));
$supportedVersions = array_map(fn (array $ver): string => $ver['version'], $supportedVersionObjects);

/**
 * @param array $apps decoded JSON from appstore
 */
function handleApps(array $apps): void {
	foreach ($apps as $app) {
		foreach ($app['screenshots'] as $screenshot) {
			$url = $screenshot['url'];
			if (!file_exists(__DIR__ . '/cache/' . base64_encode($url))) {
				$trimmedUrl = trim($url);
				if (substr($trimmedUrl, 0, 8) === 'https://') {
					$data = file_get_contents($trimmedUrl);
					file_put_contents(__DIR__ . '/cache/' . base64_encode($url), $data);
					echo(
					sprintf(
						"Synced url %s\n",
						$url
					)
					);
				}
			}
		}
	}
}

foreach($supportedVersions as $version) {
	$json = file_get_contents(
		sprintf('https://apps.nextcloud.com/api/v1/platform/%s/apps.json', $version)
	);

	$apps = json_decode($json, true);
    handleApps($apps);
}

$json = file_get_contents('https://apps.nextcloud.com/api/v1/appapi_apps.json');
$apps = json_decode($json, true);
handleApps($apps);
