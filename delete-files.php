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

/* File       : delete-files.php.
 * Description: Deletes all files associated with the stipulated FUPS process.
 *              Part of the web app functionality of FUPS.
 */

require_once __DIR__.'/common.php';

$err = '';
$num_files_deleted = 0;
if (!isset($_GET['token'])) $err = 'Fatal error: I did not detect a URL query parameter "token".';
else {
	$token = $_GET['token'];
	if (validate_token($token, $err)) {
		try_delete_file(make_settings_filename    ($token), 'settings'      , true , $err, $num_files_deleted);
		try_delete_file(make_status_filename      ($token), 'status'        , false, $err, $num_files_deleted);
		try_delete_file(make_errs_filename        ($token), 'error'         , false, $err, $num_files_deleted);
		try_delete_file(make_errs_admin_filename  ($token), 'errors (admin)', false, $err, $num_files_deleted, false);
		try_delete_file(make_output_filename      ($token), 'output'        , false, $err, $num_files_deleted, false);
		try_delete_file(make_serialize_filename   ($token), 'serialisation' , true , $err, $num_files_deleted);
		try_delete_file(make_cookie_filename      ($token), 'cookie'        , true , $err, $num_files_deleted, false);
		try_delete_file(make_cancellation_filename($token), 'cancellation'  , true , $err, $num_files_deleted, false);
	}
}

function try_delete_file($filename, $name, $sensitive, &$err, &$num_files_deleted, $add_err_if_file_not_present = true) {
	global $fups_url_homepage;

	if (!is_file($filename)) {
		if ($add_err_if_file_not_present) {
			$err .= ($err ? ' Another' : 'An');
			$err .= ' error occurred: the '.$name.' file does not exist on disk; possibly you have already deleted it or it was never created in the first place.';
		}
	} else if (!unlink($filename)) {
		$err .= ($err ? ' Another' : 'An');
		$err .= ' error occurred: failed to delete the '.$name.' file '.($sensitive ? '(contains username and password if you supplied them).' : '(does NOT contain either username or password).');
	} else	$num_files_deleted++;
}

$page = substr(__FILE__, strlen(FUPS_INC_ROOT));
fups_output_page_start($page, 'FUPS: file deletion page', 'Permanently remove from the web server all files used to scrape your posts.');
?>
			<ul class="fups_listmin">
				<li><a href="<?php echo $fups_url_homepage; ?>">&lt;&lt; Back to the FUPS homepage</a></li>
			</ul>

			<h2>FUPS: file deletion page</h2>
<?php
if ($err) {
?>
			<p class="fups_error"><?php echo $err; ?></p>
<?php
}
if ($num_files_deleted > 0) {
?>
			<p>Successfully deleted <?php echo $num_files_deleted; ?> file(s) from this web server.</p>
<?php
}

fups_output_page_end($page);
?>
