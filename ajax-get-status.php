<?php

/* 
 * FUPS: Forum user-post scraper. An extensible PHP framework for scraping and
 * outputting the posts of a specified user from a specified forum/board
 * running supported forum software. Can be run as either a web app or a
 * commandline script.
 *
 * Copyright (C) 2013-2014 Laird Shaw.
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

/* File       : ajax-get-status.php.
 * Description: The server-side element to supply on demand the scraping status
 *              to the FUPS web app (accessed from Javascript within run.php).
 */

require_once __DIR__.'/common.php';

header('Content-Type:text/plain');

define('MAX_WAIT_SECONDS', 90);

$token = $_GET['token'];
$old_filesize = isset($_GET['filesize']) ? $_GET['filesize'] : 0;
$old_ts = isset($_GET['ts']) ? $_GET['ts'] : '';

if (validate_token($token, $err)) {
	$status_filename = make_status_filename($token);
	$errs_filename = make_errs_filename($token);
	$output_filename = make_output_filename($token);
}

$org_errs = @file_get_contents($errs_filename);
$i = 0;
while ($i++ < MAX_WAIT_SECONDS) {
	clearstatcache();
	$filesize = filesize($status_filename);
	$ts = filemtime($status_filename);
	$status = @file_get_contents($status_filename);
	$errs   = @file_get_contents($errs_filename  );
	if ($filesize != $old_filesize || $ts != $old_ts || $errs != $org_errs) {
		get_failed_done_cancelled($status, $done, $cancelled, $failed);
		echo $filesize."\n";
		echo $ts."\n";
		output_update_html($token, $status, $done, $cancelled, $failed, $err, $errs, true);
		exit;
	}
	sleep(1);
}

echo $old_filesize."\n";
echo $old_ts."\n";
?>
			<!-- <?php echo FUPS_FAILED_STR; ?> -->
			<h3>Script seems hung</h3>

			<div>

				<a href="<?php echo 'run.php?ajax=yes&amp;token='.$token; ?>">Check progress</a>

				<p>It appears that progress has halted unexpectedly - neither the status file nor the error file have changed in <?php echo MAX_WAIT_SECONDS; ?> seconds. It is likely that an error has caused the process to exit before finishing. We are sorry about this failure. In case you want to be sure that progress has indeed halted, you are welcome to click the "Check progress" link, but otherwise, this page will no longer automatically refresh.</p>
			</div>
