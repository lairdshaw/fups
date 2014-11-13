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

/* File       : cancel.php.
 * Description: Updates the status file of the relevant FUPS process to
 *              indicate that the user wants the process cancelled. Lets
 *              the user know the result, and directs the user back to
 *              the status page to wait for acknowledgement of cancellation.
 *              Part of the web app functionality of FUPS.
 */

require_once __DIR__.'/common.php';

$err = '';
if (!isset($_GET['token'])) $err = 'Fatal error: I did not detect a URL query parameter "token".';
else {
	$token = $_GET['token'];
	if (validate_token($token, $err)) {
		$cancellation_filename = make_cancellation_filename($token, $err);
		if (!@file_put_contents($cancellation_filename, '') === false) $err = 'A fatal error occurred: failed to write to the cancellation file.';
	}
}

$page = substr(__FILE__, strlen(FUPS_INC_ROOT));
fups_output_page_start($page, 'FUPS cancellation page: cancel an in-progress task', 'Cancel an in-progress FUPS task.');

if (!$err) {
?>
			<ul class="fups_listmin">
				<li><a href="run.php?token=<?php echo $token.(isset($_GET['ajax']) ? '&amp;ajax=yes' : '');; ?>">&lt;&lt; Back to the FUPS status page</a></li>
			</ul>
<?php
}
?>
			<h2>FUPS cancellation page: cancel an in-progress task</h2>
<?php
if ($err) {
?>
			<p class="fups_error"><?php echo $err; ?></p>
<?php
} else {
?>
			<p>Successfully wrote to the cancellation file; the task should pick up on this soon and quit. If you wish to delete the files associated with this task on the server, then please return to <a href="run.php?token=<?php echo $token.(isset($_GET['ajax']) ? '&amp;ajax=yes' : '');; ?>">the status page</a> and wait for the message that the script has encountered the cancellation request and exited. When that occurs, the link to delete all files will be presented to you.</p>
<?php
}

fups_output_page_end($page);
?>
