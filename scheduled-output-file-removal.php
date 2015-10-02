<?php

/*
 * FUPS: Forum user-post scraper. An extensible PHP framework for scraping and
 * outputting the posts of a specified user from a specified forum/board
 * running supported forum software. Can be run as either a web app or a
 * commandline script.
 *
 * Copyright (C) 2013-2015 Laird Shaw.
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

/* File       : scheduled-output-file-removal.php.
 * Description: Intended to be run as a daily cron job, to delete all
 *              data and output files older than the settings.php
 *              value FUPS_SCHEDULED_DELETION_MIN_AGE_IN_DAYS. If you
 *              set up this daily cron job, the command you should use
 *              for it is this:
 *
 *              /path/to/php /path/to/fups/scheduled-output-file-removal.php
 *
 *              You should then edit the following defines in settings.php
 *              (initialised from settings.default.php):
 *
 *              FUPS_ROUTINE_DELETION_POLICY
 *              FUPS_SCHEDULED_DELETION_TASK_INTERVAL_IN_DAYS (only if you set
 *               your cron task to run at an interval of other than daily).
 *
 *              Part of the web app functionality of FUPS.
 */

require_once __DIR__.'/common.php';

$prefix = 'fups.output.';
$min_delete_age = FUPS_SCHEDULED_DELETION_MIN_AGE_IN_DAYS * 24 * 60 * 60; // in seconds

delete_files_in_dir_older_than_r(FUPS_DATADIR, $min_delete_age, false, array('.htaccess'));
delete_files_in_dir_older_than_r(FUPS_OUTPUTDIR, $min_delete_age, false, array());

/* $excluded_files applies to the top level only */
function delete_files_in_dir_older_than_r($dir, $min_delete_age, $delete_dir_too = false, $excluded_files = array()) {
	static $excluded_dirs = array('.', '..');

	$dir_is_empty = true;
	// Stat before making changes.
	$dir_m_time = stat($dir)['mtime'];

	if ($dh = opendir($dir)) {
		while (($file = readdir($dh)) !== false) {
			// Ignore . and ..
			if (in_array($file, $excluded_dirs)) continue;

			if (in_array($file, $excluded_files)) {
				$dir_is_empty = false;
				continue;
			}
			$filepath = $dir.'/'.$file;
			if (is_file($filepath)) {
				if (time() - stat($filepath)['mtime'] > $min_delete_age) {
					if (!unlink($filepath)) {
						fwrite(STDERR, 'Non-fatal error: failed to unlink file "'.$filepath."\"\n");
						$dir_is_empty = false;
					}
				} else	$dir_is_empty = false;
			} else if (is_dir($filepath) && !delete_files_in_dir_older_than_r($filepath, $min_delete_age, true)) {
				$dir_is_empty = false;
			}
		}
	} else	fwrite(STDERR, 'Non-fatal error: failed to open directory "'.$dir."\".\n");
	closedir($dh);

	if ($delete_dir_too && $dir_is_empty && time() - $dir_m_time > $min_delete_age) {
		if (!rmdir($dir)) {
			fwrite(STDERR, 'Non-fatal error: failed to remove directory "'.$dir."\"\n");
			$dir_is_empty = false;
		}
	}

	return $dir_is_empty;
}

?>
