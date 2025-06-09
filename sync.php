#!/usr/bin/env php

<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

if(php_sapi_name() !== 'cli') {
	die('Can only be invoked from CLI');
}

$supportedVersions = [
	'16.0.0',
	'17.0.0',
	'18.0.0',
	'19.0.0',
	'20.0.0',
	'21.0.0',
	'22.0.0',
	'23.0.0',
	'24.0.0',
	'25.0.0',
	'26.0.0',
	'27.0.0',
	'28.0.0',
	'29.0.0',
	'30.0.0',
	'31.0.0',
];

/**
 * @param array $apps decoded JSON from appstore
 */
function handleApps(array $apps): void {
	foreach($apps as $app) {
		foreach($app['screenshots'] as $screenshot) {
			$url = $screenshot['url'];
			if(!file_exists(__DIR__ . '/cache/' . base64_encode($url))) {
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
		sprintf(
			'https://apps.nextcloud.com/api/v1/platform/%s/apps.json', $version
		)
	);

	$apps = json_decode($json, true);
    handleApps($apps);
}

$json = file_get_contents('https://apps.nextcloud.com/api/v1/appapi_apps.json');
$apps = json_decode($json, true);
handleApps($apps);
