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

/* File       : fups.php.
 * Description: The main scraping script, initiated either automatically by
 *              the web app (run.php) or manually e.g. via the commandline.
 *              Manual initiation should be as follows:
 *
 *              php path/to/fups.php -i path/to/existing/optionsfile.txt -o path/to/desired/output_dir/
 *
 *              The optionsfile.txt file should contain a series of lines of
 *              supported options, each option followed by an equals sign
 *              and then its value. The first option should be "forum_type",
 *              which should be set to one of (at time of writing) "phpbb" or
 *              "xenforo". Further options can be determined by consulting the
 *              $required_settings and $optional_settings arrays in each of
 *              classes/CphpBB.php and classes/CXenForo.php.
 */

if (php_sapi_name() != 'cli') {
	echo 'This script can only be run from the commandline. It appears that it was NOT run from the commandline in this instance, so it is now exiting.';
	exit(1);
}

require_once __DIR__.'/common.php';
require_once __DIR__.'/classes/CFUPSBase.php';

# Parse and validate commandline arguments, exiting on error.
if (!isset($argv[1])) {
	FUPSBase::exit_err_s('Fatal error: No commandline arguments supplied.'."\n", __FILE__, __METHOD__, __LINE__);
} else {
	$chained = false;
	$relogin = false;
	$web_initiated = null;
	$settings_filename = false;
	$output_dirname = false;
	$quiet = false;
	$skip_topic = false;
	static $errmsg_mixed_cmdline_args = 'Fatal error: web-initiated (-t) and commandline (-i, -o and -q) arguments specified simultaneously.';
	$i = 1;
	while ($i < $argc) {
		switch ($argv[$i]) {
		case '-i':
			if ($web_initiated === true) {
				FUPSBase::exit_err_s($errmsg_mixed_cmdline_args, __FILE__, __METHOD__, __LINE__);
			}
			$web_initiated = false;
			if ($argc < $i + 1) {
				FUPSBase::exit_err_s('Fatal error: no input file specified after "-i" in commandline arguments.', __FILE__, __METHOD__, __LINE__);
			} else	$settings_filename = $argv[$i + 1];
			$i += 2;
			break;
		case '-o':
			if ($web_initiated === true) {
				FUPSBase::exit_err_s($errmsg_mixed_cmdline_args, __FILE__, __METHOD__, __LINE__);
			}
			$web_initiated = false;
			if ($argc < $i + 1) {
				FUPSBase::exit_err_s('Fatal error: no output directory specified after "-o" in commandline arguments.', __FILE__, __METHOD__, __LINE__);
			} else	$output_dirname = $argv[$i + 1];
			$i += 2;
			break;
		case '-q':
			if ($web_initiated === true) {
				FUPSBase::exit_err_s($errmsg_mixed_cmdline_args, __FILE__, __METHOD__, __LINE__);
			}
			$web_initiated = false;
			$quiet = true;
			$i++;
			break;
		case '-t':
			if ($web_initiated === false) {
				FUPSBase::exit_err_s($errmsg_mixed_cmdline_args, __FILE__, __METHOD__, __LINE__);
			}
			$web_initiated = true;
			if ($argc < $i + 1) {
				FUPSBase::exit_err_s('Fatal error: no token specified after "-t" in commandline arguments.', __FILE__, __METHOD__, __LINE__);
			} else	$token = $argv[$i + 1];
			$i += 2;
			break;
		case '-c':
			$chained = true;
			$i++;
			break;
		case '-r':
			$relogin = true;
			$i++;
			break;
		case '-s':
			$skip_topic = true;
			$i++;
			break;
		default:
			FUPSBase::exit_err_s('Fatal error: unknown commandline argument specified: "'.$argv[$i].'".', __FILE__, __METHOD__, __LINE__);
			break;
		}
	}
	if ($web_initiated) {
		$settings_filename = make_settings_filename($token);
	} else if ($web_initiated === false) {
		if (!$settings_filename || !$output_dirname) {
			FUPSBase::exit_err_s('Fatal error: no '.(!$settings_filename ? 'settings' : 'output').' filename specified in commandline arguments.', __FILE__, __METHOD__, __LINE__);
		}
	} else {
		FUPSBase::exit_err_s('Fatal error: $web_initiated is uninitialised after parsing commandline arguments (this error should never occur, and indicates a bug).', __FILE__, __METHOD__, __LINE__);
	}
}

$forum_type = FUPSBase::read_forum_type_from_settings_file_s($settings_filename);
$forum_type_caps = FUPSBase::get_canonical_forum_type_s($forum_type);
if (!$forum_type_caps) {
	FUPSBase::exit_err_s('Fatal error: missing or invalid forum_type in settings file "'.$settings_filename.'": "'.$forum_type.'".', __FILE__, __METHOD__, __LINE__);
}

require_once __DIR__.'/classes/C'.$forum_type_caps.'.php';
if ($chained) {
	$token_or_settings_filename = $web_initiated ? $token : $settings_filename;
	$FUPS = unserialize(file_get_contents(make_serialize_filename($token_or_settings_filename)));
	if ($skip_topic) {
		$FUPS->skip_current_topic();
	}
} else {
	if ($web_initiated) {
		$params = array('token' => $token);
	} else {
		$params = array(
			'settings_filename' => $settings_filename,
			'output_dirname'    => $output_dirname,
			'quiet'             => $quiet,
		);
	}
	$class = $forum_type_caps.'FUPS';
	$FUPS = new $class($web_initiated, $params);
}
$FUPS->run($relogin || !$chained);

?>
