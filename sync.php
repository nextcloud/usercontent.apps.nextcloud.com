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
	'9.2.0',
	'10.0.0',
	'11.0.0',
	'12.0.0',
	'13.0.0',
	'14.0.0',
];

foreach($supportedVersions as $version) {
	$json = file_get_contents(
		sprintf(
			'https://apps.nextcloud.com/api/v1/platform/%s/apps.json', $version
		)
	);

	$apps = json_decode($json, true);
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
