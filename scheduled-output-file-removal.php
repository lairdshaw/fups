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

$min_delete_age = FUPS_SCHEDULED_DELETION_MIN_AGE_IN_DAYS * 24 * 60 * 60; // in seconds

$errs = '';
delete_files_in_dir_older_than_r(FUPS_DATADIR, $min_delete_age, false, array(), $num_files_del, $num_dirs_del, $errs);
delete_files_in_dir_older_than_r(FUPS_OUTPUTDIR, $min_delete_age, false, array('.htaccess'), $num_files_del, $num_dirs_del, $errs);
if ($errs) {
	$ferr = fopen('php://stderr', 'a');
	fwrite($ferr, $errs);
}

?>
