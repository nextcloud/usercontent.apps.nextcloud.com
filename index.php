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

header('Content-Security-Policy: default-src \'none\'');
header('X-Frame-Options: deny');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=15768000; includeSubDomains; preload');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment');

$cacheItem = $_SERVER['PATH_INFO'];
if (strpos($cacheItem, '/../') !== false || strrchr($cacheItem, '/') === '/..') {
	die('Traversal detected');
}
if (file_exists(__DIR__ . '/cache/' . $cacheItem)) {
	header('Expires: Sun, 17 Jan 2038 19:14:07 GMT');
	echo file_get_contents(__DIR__ . '/cache/' . $cacheItem);
} else {
	die('File not found');
}
