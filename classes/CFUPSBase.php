<?php

/* 
 * FUPS: Forum user-post scraper. An extensible PHP framework for scraping and
 * outputting the posts of a specified user from a specified forum/board
 * running supported forum software. Can be run as either a web app or a
 * commandline script.
 *
 * Copyright (C) 2013-2017 Laird Shaw.
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

/* File       : classes/CFUPSBase.php.
 * Description: The base class for forum scraping. Cannot be instantiated
 *              due to abstract methods - only descendant classes for specific
 *              forums can be instantiated.
 */

require_once __DIR__.'/../common.php';
require_once __DIR__.'/../phpBB-days-and-months-intl.php';

define('FUPS_EMAIL_TYPE_NONFATAL' , 0);
define('FUPS_EMAIL_TYPE_FATAL'    , 1);
define('FUPS_EMAIL_TYPE_RESUMABLE', 2);

abstract class FUPSBase {
	# The maximum time in seconds before the script chains a new instance of itself and then exits,
	# to avoid timeouts due to exceeding the PHP commandline max_execution_time ini setting.
	public    $FUPS_CHAIN_DURATION =  null;
	protected $charset             =  null;
	protected $forum_idx           =  null;
	protected $forum_idx2          =  null;
	protected $have_written_to_admin_err_file = false;
	protected $private_settings  = array('login_user', 'login_password');
	/* Different skins sometimes output html different enough that
	 * a different regex is required for each skin to match the values that
	 * this script searches for: the array below collects all of these
	 * regexes in one place for easier maintenance, making it especially
	 * easier to support new skins (this array is only set "for real"
	 * in the descendant classes).
	 *
	 * It is not necessary for a skin to contain an entry for each regex
	 * type so long as some other skin's entry for that regex matches.
	 */
	protected $regexps = array(
		/* 'skin_template' => array(
			'board_title'              => a regex to extract the board's title from at least one forum page
			                              (this regex is tried for each page until it succeeds)
			'login_success'            => a regex to match the html of a successful-login page
			'login_required'           => a regex to match an error message that login is required to view
			                              member details
			'user_name'                => a regex to extract the user's name from the user's profile page
			'thread_author'            => a regex to extract the thread's author from the thread view page
			'search_results_not_found' => a regex to detect when a search results page returns no results
			'search_results_page_data' => a regex to be matched on the user's posts search page using
			                              preg_match_all with flags set to PREG_SET_ORDER so that each entry of
			                              $matches ends up with the following matches in the order specified in
			                              search_results_page_data_order.
			                              N.B. Must not match any results matched by any other skin's
			                              search_results_page_data regex - the results of all are combined!
			'search_results_page_data_order' => an array specifying the order in which the following matches occur
			                                    in the matches returned by the previous regex.
				= array(
					'title'   => the match index of the title of post,
					'ts'      => the match index of the timestamp of post,
					'forum'   => the match index of the title of forum,
					'topic'   => the match index of the thread topic,
					'forumid' => the match index of the forum id,
					'topicid' => the match index of the topic id,
					'postid'  => the match index of the post id,
				)
			'post_contents'            => a regex to match post id (first match) and post contents (second match)
			                              on a thread page; it is called with match_all so it will return all
			                              post ids and contents on the page
			'last_forum_page'           => a regex to match when this is the last page of a forum listing.
			'forum_page_topicids'       => a regex to match the topicids on the forum listing pages.
			'post_contents_ext'         => a regex to match extended information about a post (see below)
						       on a thread page; it is called with match_all with flags set to
						       PREG_SET_ORDER so that each entry of $matches ends up with the
						       matches in the order specified in post_contents_ext_order.
			'post_contents_ext_order'   => an array specifying the order in which the following matches occur
			                               in the matches returned by the previous regex.
				= array(
					'author'  => the match index of the name (not ID) of the post author,
					'title'   => the match index of the title of post,
					'ts'      => the match index of the timestamp of post,
					'postid'  => the match index of the post id,
					'contents'=> the match index of the post contents,
				)
			'forum_title'               => a regex to match the forum title on a (sub)forum page.
			'last_topic_page'           => a regex to match when this is the last page of a topic.
		),
		*/
	);
	protected $settings          = array();
	protected $progress_level    =       0;
	protected $org_start_time    =    null;
	protected $start_time        =    null;
	protected $web_initiated     =    null;
	protected $token             =   false;
	protected $settings_filename =   false;
	protected $output_dirname    =   false;
	protected $output_dirname_web =   null;
	protected $errs_filename     =   false;
	protected $cookie_filename   =   false;
	protected $ch                =    null;
	protected $forum_data        = array();
	protected $forum_pg          =    null;
	protected $forum_page_counter=    null;
	protected $last_url          =    null;
	protected $search_id         =    null;
	protected $post_search_counter =     0;
	protected $posts_not_found   = array();
	protected $empty_posts       = array();
	protected $posts_data        = array();
	protected $total_posts       =       0;
	protected $current_topic_id  =    null;
	protected $num_posts_retrieved =     0;
	protected $num_thread_infos_retrieved = 0;
	protected $search_page_num   =       0;
	protected $dbg               =   false;
	protected $quiet             =   false;
	protected $progress_levels   = array(
		// User-post extraction levels
		0 => 'init_user_post_search',
		1 => 'user_post_search',
		2 => 'posts_retrieval',
		3 => 'extract_per_thread_info',
		4 => 'topic_post_sort',
		5 => 'handle_missing_posts',
		6 => 'download_files',
		// Forum extraction levels
		7 => 'init_forums_extract',
		8 => 'forums_extract',
		9 => 'forum_topics_extract',
		// Generic levels
		10 => 'write_output',
		11 => 'check_send_non_fatal_err_email',
	);
	protected $was_chained       =   false;
	protected $downld_file_urls  = array();
	protected $downld_file_urls_downloaded      = array();
	protected $downld_file_urls_failed_download = array();

	public function __construct($web_initiated, $params, $do_not_init = false) {
		if (!$do_not_init) {
			$this->org_start_time = time();
			$this->start_time = $this->org_start_time;
			$this->web_initiated = $web_initiated;
			if ($this->web_initiated) {
				if (!isset($params['token'])) {
					$this->exit_err('Fatal error: $web_initiated was true but $params did not contain a "token" key.', __FILE__, __METHOD__, __LINE__);
				}
				$this->token = $params['token'];
				$this->settings_filename = make_settings_filename($this->token);
				$this->output_dirname    = make_output_dirname   ($this->token);
				$this->errs_filename     = make_errs_filename    ($this->token);
			} else {
				if (!isset($params['settings_filename'])) {
					$this->exit_err('Fatal error: $web_initiated was false but $params did not contain a "settings_filename" key.', __FILE__, __METHOD__, __LINE__);
				}
				$this->settings_filename = $params['settings_filename'];
				if (!isset($params['output_dirname'])) {
					$this->exit_err('Fatal error: $web_initiated was false but $params did not contain a "output_dirname" key.', __FILE__, __METHOD__, __LINE__);
				}
				$this->output_dirname = $params['output_dirname'];
				$len = strlen($this->output_dirname);
				// Make sure user-supplied (commandline interface) output directories end in a slash,
				// because when we generate them (web interface) we make sure they end in a slash,
				// and this way we can rely on them ending in a slash in all contexts. Note that here
				// we assume an empty output directory to refer to the root directory.
				if ($len <= 0 || $this->output_dirname[$len-1] != '/') {
					$this->output_dirname .= '/';
				}
				$this->quiet          = $params['quiet'         ];
			}

			if (FUPS_CHAIN_DURATION == -1) {
				$max_execution_time = ini_get('max_execution_time');
				if (is_numeric($max_execution_time) && $max_execution_time > 0) {
					$this->FUPS_CHAIN_DURATION = $max_execution_time * 3/4;
				} else	$this->FUPS_CHAIN_DURATION = FUPS_FALLBACK_FUPS_CHAIN_DURATION;
			} else $this->FUPS_CHAIN_DURATION = FUPS_CHAIN_DURATION;

			$this->write_status('Reading settings.');
			$missing = $this->read_settings();
			if ($missing) {
				$this->exit_err("The following settings were missing: ".implode(', ', $missing).'.', __FILE__, __METHOD__, __LINE__);
			}

			$this->dbg = $this->settings['debug'];

			date_default_timezone_set($this->settings['php_timezone']); // This timezone only matters when converting the earliest time setting.
			if (!empty($this->settings['start_from_date'])) {
				$this->settings['earliest'] = $this->strtotime_intl($this->settings['start_from_date']);
				if ($this->settings['earliest'] === false) $this->write_err("Error: failed to convert 'start_from_date' ({$this->settings['start_from_date']}) into a UNIX timestamp.");
			}

			if ($this->dbg) {
				$this->write_err('SETTINGS:');
				$this->write_err(var_export($this->settings, true));
			}
			$this->write_status('Finished reading settings.');

			$this->validate_settings();

			// Create output directory, appending .1 or .2 etc if necessary.
			// Do this last so we don't create it if settings validation fails.
			$max_attempts = 10000;
			$appendix = 0;
			// Strip off the trailing slash
			$dirname_org = substr($this->output_dirname, 0, strlen($this->output_dirname) - 1);
			$dirname = $dirname_org;
			if (file_exists($dirname) && !is_dir_empty($dirname)) {
				$this->write_err('Warning: Output directory "'.$this->output_dirname.'" already exists and is not empty. Attempting to generate a new one.');
				while (file_exists($dirname) && $appendix <= $max_attempts) $dirname = $dirname_org.'.'.(++$appendix);
				if ($appendix > $max_attempts) {
					$this->exit_err('Output directory "'.$this->output_dirname.'" already exists. Exceeded maximum attempts ('.$max_attempts.') in finding an alternative that does not exist. Tried "'.$dirname_org.'.1", "'.$dirname_org.'.2", "'.$dirname_org.'.3", etc.', __FILE__, __METHOD__, __LINE__);
				}
			}
			if (!file_exists($dirname) && !mkdir($dirname, 0775, true)) {
				$this->exit_err('Failed to create output directory "'.$dirname.'".', __FILE__, __METHOD__, __LINE__);
			} else if ($dirname != $dirname_org) $this->write_err('Info: Generated new output directory "'.$dirname.'".');
			$this->output_dirname = $dirname.'/';
			if ($this->web_initiated) {
				$this->output_dirname_web = make_output_dirname($this->token, /*$for_web*/true, $appendix == 0 ? '' : $appendix);
			}
		}
	}

	public function __wakeup() {
		$this->start_time = time();
		date_default_timezone_set($this->settings['php_timezone']);
		$this->was_chained = true;
		$this->write_status('Woke up in chained/resumed process.');
		// Reset num_posts_retrieved in case recovering from chained process with bug fixed in commit
		// https://github.com/lairdshaw/fups/commit/a8da8deff20e844132e993bda8e8a25652f57966
		$num_posts_retrieved = 0;
		foreach ($this->posts_data as $t_id => $t_arr) {
			foreach ($t_arr['posts'] as $p_id => $p_arr) {
				if ($p_arr['content'] || isset($this->empty_posts[$p_id]) || isset($this->posts_not_found[$p_id])) $num_posts_retrieved++;
			}
		}
		$this->num_posts_retrieved = $num_posts_retrieved;
	}

	static protected function absolutify_url_s($url, $root_rel_url_base, $path_rel_url_base, $current_protocol, $current_url) {
		$new_url = $url;
		if ($url) {
			$parsed = parse_url($url);
			if (!$parsed || !isset($parsed['scheme'])) {
				if ($url[0] == '#') {
					$new_url = $current_url.$url;
				} else {
					if (!isset($parsed['scheme']) && substr($url, 0, 2) == '//') {
						$new_url = $current_protocol.':'.$url;
					} else	$new_url = ($url[0] == '/' ? $root_rel_url_base : $path_rel_url_base).(substr($url, 0, 2) == './' ? substr($url, 2) : $url);
				}
			}
		}

		return $new_url;
	}

	protected function add_downld_file_failed_dnlds_output_file($eol, $eol_desc, $eol_prefix, &$output_info, $have_downld_files) {
		$downld_file_failed_dlds_filename = 'failed-file-downloads.'.$eol_prefix.'.txt';
		$downld_file_failed_dlds_filepath = $this->output_dirname.$downld_file_failed_dlds_filename;
		if (file_put_contents($downld_file_failed_dlds_filepath, implode($eol, array_keys($this->downld_file_urls_failed_download))) === false) {
			$this->write_err('Failed to write failed to "'.$downld_file_failed_dlds_filename.'".', __FILE__, __METHOD__, __LINE__);
		}
		$opts = array(
			'filename'    => $downld_file_failed_dlds_filename,
			'description' => 'A list of URLs of files that could not be downloaded, one per line. Plain text file with '.$eol_desc.' line endings. (These URLs have been left unmodified in the posts).',
			'size'        => stat($downld_file_failed_dlds_filepath)['size'],
		);
		if (!$have_downld_files) {
			$opts['url'] = $this->output_dirname_web.$downld_file_failed_dlds_filename;
		}
		$output_info[] = $opts;
	}

	protected function add_downld_file_map_output_file($eol, $eol_desc, $eol_prefix, &$output_info) {
		$downld_file_map_filename = 'sources-of-downloaded-files.'.$eol_prefix.'.txt';
		$downld_file_map_filepath = $this->output_dirname.$downld_file_map_filename;
		$contents = '';
		foreach ($this->downld_file_urls_downloaded as $org_url => $new_name) {
			$contents .= "$new_name$eol\t$org_url$eol";
		}
		if (file_put_contents($downld_file_map_filepath, $contents) === false) {
			$this->write_err('Failed to write output information to "'.$downld_file_map_filename.'".', __FILE__, __METHOD__, __LINE__);
		}
		$output_info[] = array(
			'filename'    => $downld_file_map_filename,
			'description' => 'A mapping of downloaded file filenames to the original URLs from which they were downloaded. Plain text with '.$eol_desc.' line endings.',
			'size'        => stat($downld_file_map_filepath)['size'],
		);
	}

	protected function archive_output($sourcepath, $zip_filename) {
		$ret = false;

		if (!class_exists('ZipArchive')) {
			$this->write_err('Unable to create output archive: the "ZipArchive" class does not exist. You can install it using these online instructions: <http://php.net/manual/en/zip.installation.php>.', __FILE__, __METHOD__, __LINE__);
		} else if (!chdir($sourcepath)) {
			$this->write_err('Failed to change to directory "'.$sourcepath.'". Files from this directory will not be included in the zip archive.', __FILE__, __METHOD__, __LINE__);
		} else {
			$zip = new ZipArchive();
			if ($zip->open($zip_filename, ZipArchive::CREATE) !== true) {
				$this->write_err('Unable to create zip archive "'.$zip_filename.'".', __FILE__, __METHOD__, __LINE__);
			} else {
				$sourcepath = str_replace('\\', '/', realpath($sourcepath));

				if (is_dir($sourcepath) === true) {
					if ($zip->addEmptyDir(basename($sourcepath)) === false) {
						$this->write_err('Failed to add directory "'.basename($sourcepath).'" to the zip archive.', __FILE__, __METHOD__, __LINE__);
					}
					if ($zip->addPattern('(.*)', '.', array('add_path' => basename($sourcepath).'/', 'remove_all_path' => true)) === false) {
						$this->write_err('Failed to add contents of directory "'.$sourcepath.'" to the zip archive.', __FILE__, __METHOD__, __LINE__);
					}

 					$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcepath), RecursiveIteratorIterator::SELF_FIRST);

					foreach ($files as $filepath) {
						// Ignore files; we only want directories
						if (!is_dir($filepath)) continue;

						$filepath = str_replace('\\', '/', $filepath);

						// Ignore "." and ".." directories
						if (in_array(substr($filepath, strrpos($filepath, '/')+1), array('.', '..'))) {
							continue;
						}

						$filepath = realpath($filepath);
						$local_filepath = basename($sourcepath).'/'.str_replace($sourcepath.'/', '', $filepath);

						if ($zip->addEmptyDir($local_filepath) === false) {
							$this->write_err('Failed to add directory "'.$local_filepath.'" to the zip archive.', __FILE__, __METHOD__, __LINE__);
						}
						if (!chdir($filepath)) {
							$this->write_err('Failed to change to directory "'.$local_filepath.'". Files from this directory will not be included in the zip archive.', __FILE__, __METHOD__, __LINE__);
						} else if ($zip->addPattern('(.*)', '.', array('add_path' => $local_filepath.'/', 'remove_all_path' => true)) === false) {
							$this->write_err('Failed to add contents of directory "'.$local_filepath.'" to the zip archive.', __FILE__, __METHOD__, __LINE__);
						}
					}
				} else if (is_file($sourcepath) === true) {
					$local_filepath = basename($sourcepath);
					if ($zip->addFile($sourcepath, $local_filepath) === false) {
						$this->write_err('Failed to add singular file "'.$local_filepath.'" to the zip archive.', __FILE__, __METHOD__, __LINE__);
					}
				}
				if (!$zip->close()) {
					$this->write_err('Failed to close the zip archive "'.$zip_filename.'".', __FILE__, __METHOD__, __LINE__);
				} else	$ret = true;
			}
		}

		return $ret;
	}

	protected function array_to_utf8(&$arr) {
		if ($this->charset !== null) {
			if (is_string($arr)) {
				$arr_new = iconv($this->charset, 'UTF-8', $arr);
				if ($arr_new !== false)
					$arr = $arr_new;
			} else if (is_array($arr)) {
				foreach ($arr as &$entry) {
					$this->array_to_utf8($entry);
				}
			}
		}
	}

	protected function check_do_chain() {
		if (time() - $this->start_time > $this->FUPS_CHAIN_DURATION) {
			curl_close($this->ch); // So we save the cookie file to disk for the chained process.
			$this->ch = null; // So an exception isn't raised for trying to serialise a resource.

			$serialize_filename = make_serialize_filename($this->web_initiated ? $this->token : $this->settings_filename);

			if ($this->dbg) $this->write_err('Set $serialize_filename to "'.$serialize_filename.'".');

			if (!file_put_contents($serialize_filename, serialize($this))) {
				$this->exit_err('file_put_contents returned false for the serialisation file.', __FILE__, __METHOD__, __LINE__);
			}

			$args = array(
				'chained' => true,
			);
			if ($this->web_initiated) {
				$args['token'] = $this->token;
			} else {
				$args['settings_filename'] = $this->settings_filename;
				$args['output_dirname'] = $this->output_dirname;
				$args['quiet'] = $this->quiet;
			}

			$cmd = make_php_exec_cmd($args);
			$this->write_status('Chaining next process.');
			if ($this->dbg) $this->write_err('Chaining process: about to run command: '.$cmd);
			if (!try_run_bg_proc($cmd)) {
				$this->exit_err_resumable('Apologies, the server encountered a technical error: it was unable to initiate a chained background process to continue the task of scraping, sorting and finally presenting your posts. The command used was:'.PHP_EOL.PHP_EOL.$cmd.PHP_EOL.PHP_EOL.'Any output was:'.PHP_EOL.implode(PHP_EOL, $output).PHP_EOL.PHP_EOL.'You might like to try again.', __FILE__, __METHOD__, __LINE__);
			}
			if ($this->dbg) $this->write_err('Exiting parent chaining process.');
			exit;
		}
	}

	protected function check_do_login() {}

	protected function check_do_send_errs() {
		if ($this->web_initiated) {
			$err_msg = $this->get_err_msgs_from_files_for_email();
			if ($err_msg) static::send_err_mail_to_admin_s($err_msg, $this->token, FUPS_EMAIL_TYPE_NONFATAL);
		}
	}

	protected function check_get_board_title($html) {
		if (!isset($this->settings['board_title'])) {
			# Try to discover the board's title
			if (!$this->skins_preg_match('board_title', $html, $matches)) {
				if ($this->dbg) $this->write_and_record_err_admin("Warning: couldn't find the site title. The URL of the searched page is ".$this->last_url, __FILE__, __METHOD__, __LINE__, $html);
			} else {
				$this->settings['board_title'] = $matches[1];
				if ($this->dbg) $this->write_err("Site title: {$this->settings['board_title']}");
			}
		}
	}

	protected function check_get_charset($html) {
		if ($this->charset === null && preg_match('#\\<meta\\s+http-equiv\\s*=\\s*"Content-Type"\\s+content\\s*=\\s*"text/html;\\s+charset=([^"]+)">#', $html, $matches)) {
			$this->charset = $matches[1];
			if ($this->dbg) $this->write_err('Set charset to "'.$this->charset.'".');
		}
	}

	protected function check_get_username() {
		# Discover user's name if extract_user was not present in settings file (NB might need to be logged in to do this).
		if (empty($this->settings['extract_user'])) {
			$this->write_status('Attempting to determine username.');
			$this->set_url($this->get_user_page_url());
			$html = $this->do_send();
			if (!$this->skins_preg_match('user_name', $html, $matches)) {
				$login_req = $this->skins_preg_match('login_required', $html, $matches);
				$err_msg = "Error: couldn't find the member name corresponding to specified user ID \"{$this->settings['extract_user_id']}\". ";
				if ($login_req) $err_msg .= 'The board requires that you be logged in to view member names. You can specify a login username and password in the settings on the previous page. If you already did specify them, then this error could be due to a wrong username/password combination. Instead of supplying login details, you can simply supply a value for "Extract User Username".';
				else $err_msg .= 'The URL of the searched page is <'.$this->last_url.'>.';
				$this->write_and_record_err_admin($err_msg, __FILE__, __METHOD__, __LINE__, $html);
				$this->settings['extract_user'] = '[unknown]';
			} else	$this->settings['extract_user'] = $matches[1];
		}
	}

	static public function class_file_to_forum_type_s($class_file) {
		return substr($class_file, 1, -4); # Omit initial "C" and trailing ".php"
	}

	static public function classname_to_forum_type_s($class) {
		return substr($class, 0, -4); # Omit trailing "FUPS"
	}

	public function do_send(&$redirect = false, $quit_on_error = true, &$err = false, $check_get_board_title = true) {
		static $retry_delays = array(0, 5, 5);
		static $first_so_no_wait = true;

		$html = '';

		if ($first_so_no_wait) $first_so_no_wait = false;
		else $this->wait_courteously();

		$err = false;
		for ($i = 0; $i < count($retry_delays); $i++) {
			$delay = $retry_delays[$i];
			if ($err) {
				if ($this->dbg) $this->write_err("Retrying after $delay seconds.");
				sleep($delay);
			}

			if ($this->dbg) $this->write_err("In do_send(), retrieving URL <{$this->last_url}>");
			// We emulate CURLOPT_FOLLOWLOCATION by grabbing headers and matching a "Location:"
			// header because some hosts (Hostgator!) currently have a version of cURL older
			// than that in which this bug was fixed: <http://sourceforge.net/p/curl/bugs/1159/>.
			// This bug is activated when following XenForo post URLs when CURLOPT_FOLLOWLOCATION
			// is set.
			$response = curl_exec($this->ch);
			if ($response === false) {
				$err = 'curl_exec returned false. curl_error returns: "'.curl_error($this->ch).'".';
				if ($this->dbg) $this->write_err($err, __FILE__, __METHOD__, __LINE__);
			} else {
				$header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
				$headers = substr($response, 0, $header_size);
				$html = substr($response, $header_size);
				$response_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
				if ($response_code != 200) {
					$location = false;
					if (preg_match('/^Location: (.*)$/im', $headers, $matches)) {
						$url = trim($matches[1]);
						// Strip from any # onwards - this appears to be buggy either in
						// certain older versions of cURL or receiving webservers.
						$tmp = explode('#', $url, 2);
						$url = $tmp[0];
						$redirect = $url;
						if ($redirect === false) {
							return '';
						}
						$this->validate_url($url, 'the redirected-to location', true);
						$this->set_url($url);
						if ($this->dbg) $this->write_err('In '.__METHOD__.'(): Found a "Location" header; following to <'.$url.'>.');
						$i--;
						continue;
					}
					$err = 'Received response other than 200 from server ('.$response_code.') for URL: '.$this->last_url;
					if ($this->dbg) $this->write_err($err, __FILE__, __METHOD__, __LINE__);
				} else	{
					$err = false;
					break;
				}
			}
			//if ($err) break;
		}
		if ($err) {
			if ($quit_on_error) $this->exit_err_resumable('Too many errors with request; abandoning page and quitting. Request URL is <'.$this->last_url.'>. Last error was: '.$err, __FILE__, __METHOD__, __LINE__);
		} else if ($check_get_board_title) {
			$this->check_get_board_title($html);
		}

		return $html;
	}

	# Non-static variant of the static variant below with additional functionality including resumability
	protected function exit_err($msg, $file, $method, $line, $html = false, $send_mail = true, $resumable = false) {
		# Do this before the call to write_err below otherwise we will include the written error twice in the email -
		# once (correctly) as a fatal error and once (incorrectly) as a prior non-fatal error.
		$existing_errs = $this->get_err_msgs_from_files_for_email(/*$skip_settings_and_classname*/true);

		$token = $this->web_initiated ? $this->token : false;
		$dbg   = $this->dbg;
		$this->write_err($msg, $file, $method, $line);
		$settings_str = $this->get_settings_str();

		if ($resumable) {
			$serialize_filename = make_serialize_filename($this->web_initiated ? $token : $this->settings_filename);

			if ($this->dbg) $this->write_err('Set $serialize_filename to "'.$serialize_filename.'".');

			curl_close($this->ch); // So we save the cookie file to disk for the chained process.
			$this->ch = null; // So an exception isn't raised for trying to serialise a resource.

			if (!file_put_contents($serialize_filename, serialize($this))) {
				$this->write_err('Error: unable to serialise session data to disk. Resumability may not be possible, or, if it is, FUPS may resume from an earlier point.');
			}

			if ($this->web_initiated) {
				$resumability_filename = make_resumability_filename($this->web_initiated ? $token : $this->settings_filename);
				if (!touch($resumability_filename)) {
					$this->write_err('Error: unable to create the resumability file on disk. Resumability may not be possible.');
				}
			}
		}

		static::exit_err_common_s($msg, $file, $method, $line, $this->have_written_to_admin_err_file, $existing_errs, get_class($this), $html, $settings_str, $send_mail && $this->web_initiated /*don't send mail for commandline runs*/, $token, $dbg, $resumable, $resumable && !$this->web_initiated);
	}

	protected function exit_err_resumable($msg, $file, $method, $line, $html = false, $send_mail = true) {
		$this->exit_err($msg, $file, $method, $line, $html, $send_mail, /*resumable*/true);
	}

	static public function exit_err_s($msg, $file, $method, $line, $html = false, $send_mail = true, $token = false, $dbg = false) {
		$ferr = fopen('php://stderr', 'a');
		static::write_err_s($ferr, $msg, $file, $method, $line);
		static::exit_err_common_s($msg, $file, $method, $line, false, '', null, $html, false, $send_mail, $token, $dbg, /*$resumable*/false, /*$add_cmdline_resume_note*/false);
	}

	static public function exit_err_common_s($msg, $file, $method, $line, $have_written_to_admin_err_file, $existing_errs, $classname = null, $html = false, $settings_str = false, $send_mail = true, $token = false, $dbg = false, $resumable = false, $add_cmdline_resume_note = false) {
		$full_admin_msg = static::record_err_admin_s($msg, $file, $method, $line, $have_written_to_admin_err_file, $classname, $html, $settings_str, $token, $dbg, $resumable);

		if ($existing_errs) $full_admin_msg .= PHP_EOL.PHP_EOL.PHP_EOL.$existing_errs;

		if ($send_mail) {
			static::send_err_mail_to_admin_s($full_admin_msg, $token, $resumable ? FUPS_EMAIL_TYPE_RESUMABLE : FUPS_EMAIL_TYPE_FATAL);
		}

		static::write_status_s('A fatal '.($resumable ? 'but resumable' : '').' error occurred. '.($add_cmdline_resume_note ? 'To resume this FUPS job, simply rerun the same command you just ran but with a -c argument. ' : '').($resumable ? 'EXITING BUT RESUMABLE' : 'EXITING'), $token);

		exit(1);
	}

	# Assumes search results are ordered from most recent post to oldest post.
	protected function find_author_posts_via_search_page() {
		$num_posts_found = 0;

		if ($this->dbg) $this->write_err('Reached search page with post_search_counter set to '.$this->post_search_counter.'.');

		if (!curl_setopt($this->ch, CURLOPT_POST, false)) {
			$this->write_err('Failed to set cURL option CURLOPT_POST to false.',__FILE__, __METHOD__, __LINE__);
		}

		$this->set_url($this->get_search_url());
		$html = $this->do_send();

		if ($this->skins_preg_match('search_results_not_found', $html, $matches)) {
			if ($this->dbg) $this->write_err('Matched "search_results_not_found" regex; we have finished finding posts.');
			$this->progress_level++;
			return 0;
		}

		if (!$this->skins_preg_match_all('search_results_page_data', $html, $matches, 'search_results_page_data_order', $combine = true)) {
			$this->write_and_record_err_admin('Error: couldn\'t find any search result matches on one of the search results pages.  The URL of the page is '.$this->last_url, __FILE__, __METHOD__, __LINE__, $html);
			$this->progress_level++;
			return 0;
		}

		$found_earliest = false;
		foreach ($matches as $match) {
			$forum   = $match[$match['match_indexes']['forum'  ]];
			$forumid = $match[$match['match_indexes']['forumid']];
			$topic   = $match[$match['match_indexes']['topic'  ]];
			$topicid = isset($match['match_indexes']['topicid']) ? $match[$match['match_indexes']['topicid']] : null;
			$postid  = $match[$match['match_indexes']['postid' ]];
			$posttitle = isset($match['match_indexes']['title']) && isset($match[$match['match_indexes']['title']]) ? $match[$match['match_indexes']['title']] : '';
			$ts_raw  = $match[$match['match_indexes']['ts'     ]];

			$this->ts_raw_hook($ts_raw);

			$ts = $this->strtotime_intl($ts_raw);
			if ($ts === false) {
				$err_msg = "Error: strtotime_intl failed for '$ts_raw'.";
				if ((!isset($this->settings['non_us_date_format']) || !$this->settings['non_us_date_format']) && strpos($ts_raw, '/') !== false) {
					$err_msg .= ' Hint: Perhaps you need to check the "Non-US date format" box on the previous page.';
				}
				$this->write_err($err_msg);
			} else	{
				if (!empty($this->settings['earliest']) && $ts < $this->settings['earliest']) {
					$found_earliest = true;
					$found_earliest_msg = "Found post earlier than earliest allowed; not searching further: ".$ts_raw." < {$this->settings['start_from_date']}.";
					// Don't quit yet - matches might not be in order due to $combine = true above.
					continue;
				}
			}

			$this->find_author_posts_via_search_page__match_hook($match, $forum, $forumid, $topic, $topicid, $postid, $posttitle, $ts_raw, $ts);

			$this->posts_data[$topicid]['forum'  ] = $forum;
			$this->posts_data[$topicid]['topic'  ] = $topic;
			$this->posts_data[$topicid]['forumid'] = $forumid;
			$this->posts_data[$topicid]['posts'][$postid] = array(
				'posttitle' => $posttitle,
				'ts'        => $ts_raw,
				'timestamp' => $ts,
				'content'   => null,
				// Need the below for XenForo, since topicid is always zero, so these values will
				// be overwritten above for each different topic.
				'forum'     => $forum,
				'topic'     => $topic,
				'forumid'   => $forumid,
			);
			if (isset($this->settings['download_attachments']) && $this->settings['download_attachments']) {
				$this->posts_data[$topicid]['posts'][$postid]['attachments'] = array();
			}
			if ($this->dbg) {
				$this->write_err("Added post: $posttitle ($topic; $ts; $forum; forumid: $forumid; topicid: $topicid; postid: $postid)");
			}
			
			$num_posts_found++;
		}

		$do_inc_progress_level = $found_earliest;
		if ($found_earliest && $this->dbg) $this->write_err($found_earliest_msg);

		$this->find_author_posts_via_search_page__end_hook($do_inc_progress_level, $html, $found_earliest, $matches);

		if ($this->skins_preg_match('last_search_page', $html, $matches)) {
			if ($this->dbg) $this->write_err('Matched "last_search_page" regex; we have finished finding posts.');
			$do_inc_progress_level = true;
		}

		if ($do_inc_progress_level) $this->progress_level++;
		
		return $num_posts_found;
	}

	protected function find_author_posts_via_search_page__end_hook(&$do_inc_progress_level, $html, $found_earliest, $matches) {
		$this->post_search_counter += count($matches);
	}

	protected function find_author_posts_via_search_page__match_hook($match, &$forum, &$forumid, &$topic, &$topicid, &$postid, &$posttitle, &$ts_raw, &$ts) {}

	# Override this function to e.g. remove extraneous text from the matched timestamp string
	# prior to attempting to parse it into a UNIX timestamp.
	protected function ts_raw_hook(&$ts_raw) {}

	protected function find_post($postid) {
		foreach ($this->posts_data as $topicid => $t) {
			foreach ($t['posts'] as $pid => $p) {
				if ($pid == $postid) return array($p, $t, $topicid);
			}
		}

		return false; # Earlier return possible
	}

	static protected function get_classname_msg_s($classname) {
		return 'The active FUPS class is: '.$classname;
	}

	static public function get_canonical_forum_type_s($forum_type) {
		$ret = false;

		$valid_forum_types = FUPSBase::get_valid_forum_types_s();
		foreach ($valid_forum_types as $valid_forum_type) {
			if (strcasecmp($forum_type, $valid_forum_type) == 0) {
				$ret = $valid_forum_type;
				break;
			}
		}

		return $ret;
	}

	protected function get_err_msgs_from_files_for_email($skip_settings_and_classname = false) {
		$err_msg    = '';
		if ($this->web_initiated) {
			$errs       = file_get_contents(make_errs_filename      ($this->token));
			// Disable error messages because if there are no errors then this file
			// won't exist - we want to avoid an error message telling us as much.
			$errs_admin = @file_get_contents(make_errs_admin_filename($this->token));
			if ($errs || $errs_admin) {
				$err_msg = '';
				if ($errs) {
					$len = strlen($errs);
					$trunc_msg = '';
					if ($len > FUPS_MAX_ERROR_FILE_EMAIL_LENGTH) {
						$errs = substr($errs, 0, FUPS_MAX_ERROR_FILE_EMAIL_LENGTH);
						$trunc_msg = ' (truncated from '.number_format($len).' bytes to '.number_format(FUPS_MAX_ERROR_FILE_EMAIL_LENGTH).' bytes)';
					}
					// No need to include the settings and classname if admin error info exists too,
					// because settings and classname are already included each time the admin error
					// file is appended to.
					if (!$errs_admin && !$skip_settings_and_classname) {
						$settings_msg = static::get_settings_msg_s(static::get_settings_str());
						$classname_msg = static::get_classname_msg_s(get_class($this));
						$err_msg .= $settings_msg.PHP_EOL.PHP_EOL.$classname_msg.PHP_EOL.PHP_EOL;
					}
					$err_msg .= 'The following non-fatal errors were recorded in the error file'.$trunc_msg.':'.PHP_EOL.PHP_EOL.$errs.PHP_EOL;
				}
				if ($errs_admin) {
					if ($errs) $err_msg .= PHP_EOL.PHP_EOL;
					$len = strlen($errs_admin);
					$trunc_msg = '';
					if ($len > FUPS_MAX_ADMIN_FILE_EMAIL_LENGTH) {
						$errs_admin = substr($errs_admin, 0, FUPS_MAX_ADMIN_FILE_EMAIL_LENGTH);
						$trunc_msg = ' (truncated from '.number_format($len).' bytes to '.number_format(FUPS_MAX_ADMIN_FILE_EMAIL_LENGTH).' bytes)';
					}
					$err_msg .= 'The following extended non-fatal error messages were recorded in the admin error file'.$trunc_msg.':'.PHP_EOL.PHP_EOL.$errs_admin.PHP_EOL;
				}
			}
		}

		return $err_msg;
	}

	protected function get_extra_head_lines() {
		return '';
	}

	protected function get_final_output_array() {
		static $ret = null;
		if ($ret === null) {
			$posts_data = $this->posts_data;
			if (isset($this->settings['download_attachments']) && $this->settings['download_attachments']) {
				// Map absolute URLS of downloaded attachments to local, relative URLs.
				foreach ($posts_data  as $topicid => &$topic_data) {
					foreach ($topic_data['posts'] as &$post_data) {
						if ($post_data['attachments']) foreach ($post_data['attachments'] as &$attachment) {
							$attachment['original_url'] = $attachment['url'];
							if (isset($this->downld_file_urls_downloaded[$attachment['url']])) {
								$attachment['url'] = $this->downld_file_urls_downloaded[$attachment['url']];
							}
						}
					}
				}
			}
			$ret = array(
				'board_title'       => $this->settings['board_title'],
				'user_name'         => $this->settings['extract_user'],
				'board_base_url'    => $this->settings['base_url'],
				'start_from_date'   => $this->settings['start_from_date'],
				'character_set'     => $this->charset,
				'threads_and_posts' => $posts_data,
			);
		}

		return $ret;
	}

	static protected function get_formatted_err_s($method, $line, $file, $msg) {
		$ret = '';
		if ($method) $ret = "In $method";
		if ($line) {
			$ret .= ($ret ? ' in' : 'In')." line $line";
		}
		if ($file) {
			$ret .= ($ret ? ' in' : 'In')." file $file";
		}
		$ret .= ($ret ? ': ' : '').$msg;

		return $ret;
	}

	abstract protected function get_forum_page_url($id, $pg);

	static public function get_forum_type_s() {
		return static::get_canonical_forum_type_s(static::classname_to_forum_type_s(get_called_class()));
	}

	static function get_forum_software_homepage_s() {
		return '[YOU NEED TO CUSTOMISE THE static get_forum_software_homepage_s() method OF YOUR CLASS DESCENDING FROM FUPSBase!]';
	}

	static protected function get_downld_file_filename_from_url_s($url) {
		return urldecode(explode('?', basename($url))[0]);
	}

	static function get_msg_how_to_detect_forum_s() {
		return '[YOU NEED TO CUSTOMISE THE static get_msg_how_to_detect_forum_s() function OF YOUR CLASS DESCENDING FROM FUPSBase!]';
	}

	protected function get_output_variants() {
		$user_posts_opvs = array(
			array(
				'filename_appendix' => '.threadasc.dateasc.html',
				'method'            => 'write_output_html_threadasc_dateasc',
				'description'       => 'HTML, sorting posts first by ascending thread title (i.e. alphabetical order) then ascending post date (i.e. earliest first).',
			),
			array(
				'filename_appendix' => '.threadasc.datedesc.html',
				'method'            => 'write_output_html_threadasc_datedesc',
				'description'       => 'HTML, sorting posts first by ascending thread title (i.e. alphabetical order) then descending post date (i.e. latest first).',
			),
			array(
				'filename_appendix' => '.threaddesc.dateasc.html',
				'method'            => 'write_output_html_threaddesc_dateasc',
				'description'       => 'HTML, sorting posts first by descending thread title (i.e. reverse alphabetical order) then ascending post date (i.e. earliest first).',
			),
			array(
				'filename_appendix' => '.threaddesc.datedesc.html',
				'method'            => 'write_output_html_threaddesc_datedesc',
				'description'       => 'HTML, sorting posts first by descending thread title (i.e. reverse alphabetical order) then descending post date (i.e. latest first).',
			),
			array(
				'filename_appendix' => '.dateasc.html',
				'method'            => 'write_output_html_dateasc',
				'description'       => 'HTML, sorting posts by ascending date (i.e. earliest first) regardless of which thread they are in.',
			),
			array(
				'filename_appendix' => '.datedesc.html',
				'method'            => 'write_output_html_datedesc',
				'description'       => 'HTML, sorting posts by descending date (i.e. latest first) regardless of which thread they are in.',
			),
			array(
				'filename_appendix' => '.php_serialised',
				'method'            => 'write_output_php_serialised',
				'description'       => 'Serialised PHP.',
			),
			array(
				'filename_appendix' => '.php',
				'method'            => 'write_output_php',
				'description'       => 'PHP (unserialised array).',
			),
			array(
				'filename_appendix' => '.json',
				'method'            => 'write_output_json',
				'description'       => 'JSON.',
			),
		);
		$forum_posts_opvs = array(
			array(
				'filename_appendix' => '.forum-posts.json',
				'method'            => 'write_output_json_forums',
				'description'       => 'JSON.',
			),
		);

		$final_arr = array();
		if (!empty($this->settings['extract_user_id'])) {
			$final_arr = array_merge($final_arr, $user_posts_opvs);
		}
		if (isset($this->settings['forum_ids_arr'])) {
			$final_arr = array_merge($final_arr, $forum_posts_opvs);
		}

		return $final_arr;
	}

	protected function get_post_contents($forumid, $topicid, $postid) {
		$ret = false;
		$found = false;

		if (!curl_setopt($this->ch, CURLOPT_POST, false)) {
			$this->write_err('Failed to set cURL option CURLOPT_POST to false.',__FILE__, __METHOD__, __LINE__);
		}

		$url = $this->get_post_url($forumid, $topicid, $postid);
		$this->set_url($url);
		$html = $this->do_send();

		$this->check_get_charset($html);

		$err = false;
		$count = 0;
		if (!$this->skins_preg_match_all('post_contents', $html, $matches)) {
			$err = true;
			$this->write_and_record_err_admin('Error: Did not find any post IDs or contents on the thread page for post ID '.$postid.'. The URL of the page is "'.$this->last_url.'"', __FILE__, __METHOD__, __LINE__, $html);
			$postids = array();
		} else {
			list($root_rel_url_base, $path_rel_url_base, $current_protocol) = static::get_base_urls_s($this->last_url, $html);
			list($found, $postids) = $this->get_post_contents_from_matches($matches, $postid, $topicid, $root_rel_url_base, $path_rel_url_base, $current_protocol, $this->last_url);
			$count = count($postids);
			if ($found) {
				if ($this->dbg) $this->write_err('Retrieved post contents of post ID "'.$postid.'"');
				$ret = true;
				$count--;
			} else	$this->write_and_record_err_admin('FAILED to retrieve post contents of post ID "'.$postid.'". The URL of the page is "'.$this->last_url.'"', __FILE__, __METHOD__, __LINE__, $html);

			if ($count > 0 && $this->dbg) $this->write_err('Retrieved '.$count.' other posts.');
		}

		$this->get_post_contents__end_hook($forumid, $topicid, $postid, $postids, $html, $found, $err, $count, $ret);

		if (!$found) $this->posts_not_found[$postid] = true;
		$this->num_posts_retrieved += $count + ($found ? 1 : 0);

		return $ret;
	}

	protected function get_post_contents__end_hook($forumid, $topicid, $postid, $postids, $html, &$found, $err, $count, &$ret) {}

	static protected function get_base_urls_s($url, $html) {
		$root_rel_url_base = '';
		$path_rel_url_base = '';
		$current_protocol = '';

		$parsed = parse_url($url);
		if ($parsed) {
			$current_protocol = $parsed['scheme'];
			$server_base = $current_protocol.'://'.(isset($parsed['username']) ? $parsed['username'].(isset($parsed['password']) ? ':'.$parsed['password'] : '').'@' : '').$parsed['host'].(isset($parsed['port']) ? ':'.$parsed['port'] : '').'/';
			$dir = isset($parsed['path']) ? $parsed['path'] : '';
			if ($dir[strlen($dir)-1] != '/') {
				$dir = dirname($dir).'/';
			}
			$current_dir_url = $server_base.substr($dir, 1);
			$root_rel_url_base = $server_base;
			$path_rel_url_base = $current_dir_url;
		}

		if (preg_match_all('(<head(?:>|\\s).*<base\\s+([^>]*)>.*</head>)Us', $html, $matches, PREG_PATTERN_ORDER)) {
			for ($i = count($matches[1]) - 1; $i >= 0; $i--) {
				$attrs = static::split_attrs_s($matches[1][$i]);
				if (isset($attrs['href'])) {
					$path_rel_url_base = htmlspecialchars_decode($attrs['href']);
					break;
				}
			}
		}
		$parsed = parse_url($path_rel_url_base);
		if (!$parsed || !isset($parsed['scheme'])) {
			if (!isset($parsed['scheme']) && substr($path_rel_url_base, 0, 2) == '//') {
				$path_rel_url_base = $current_protocol.':'.$path_rel_url_base;
			} else	$path_rel_url_base = ($path_rel_url_base[0] == '/' ? substr($server_base, 0, -1) : $current_dir_url).$path_rel_url_base;
		}

		return array($root_rel_url_base, $path_rel_url_base, $current_protocol);
	}

	protected function get_post_contents_from_matches($matches, $postid, $topicid, $root_rel_url_base, $path_rel_url_base, $current_protocol, $current_url) {
		$found = false;
		$postids = array();
		$posts =& $this->posts_data[$topicid]['posts'];

		foreach ($matches as $match) {
			if (isset($posts[$match[1]])) {
				if ($match[2] == '') {
					$this->empty_posts[$match[1]] = true;
					$this->write_err("Warning: the post with ID {$match[1]} in the topic with ID $topicid appears to be empty.");
				} else {
					$downld_file_urls = array();
					$post_html = static::replace_contextual_urls_s($match[2], $root_rel_url_base, $path_rel_url_base, $current_protocol, $current_url, $downld_file_urls);
					if ($this->dbg) {
						if ($downld_file_urls) {
							$this->write_err('Merging the following downloadable file URLs into $this->downld_file_urls: <'.implode('>, <', $downld_file_urls).'>.');
						} else	$this->write_err('No downloadable file URLs to merge.');
					}
					$this->downld_file_urls = array_merge($this->downld_file_urls, $downld_file_urls);
					if ($post_html == '') {
						$this->empty_posts[$match[1]] = true;
						$this->write_and_record_err_admin("Warning: after replacing URLs the post with ID {$match[1]} in the topic with ID $topicid appears to be empty. The pre-replacement post HTML is:\n\n{$match[2]}", __FILE__, __METHOD__, __LINE__);
					} else	$posts[$match[1]]['content'] = $post_html;
				}
				if (isset($this->settings['download_attachments']) && $this->settings['download_attachments'] && isset($match[3]) && $match[3] != '') {
					if ($this->dbg) $this->write_err("Attempting to parse attachments in post with ID {$match[1]}.");
					if (!$this->skins_preg_match_all('attachments', $match[3], $matches_att, 'attachments_order')) {
						$this->write_and_record_err_admin("Warning: the post with ID {$match[1]} appears to have attachments but we could not parse them.", __FILE__, __METHOD__, __LINE__);
					} else {
						$attachments = array();
						foreach ($matches_att as $match_att) {
							$is_image = isset($match_att[$match_att['match_indexes']['img_url']]) && $match_att[$match_att['match_indexes']['img_url']] != '';
							$attachment = array(
								'comment'  => isset($match_att[$match_att['match_indexes']['comment']]) ? $match_att[$match_att['match_indexes']['comment']] : '',
								'is_image' => $is_image,
								'url'      => htmlspecialchars_decode($match_att[$match_att['match_indexes'][($is_image ? 'img' : 'file').'_url']]),
								'filename' => $match_att[$match_att['match_indexes'][($is_image ? 'img' : 'file').'_name']],
							);
							$attachment['url'] = static::absolutify_url_s($attachment['url'], $root_rel_url_base, $path_rel_url_base, $current_protocol, $current_url);
							$this->downld_file_urls[$attachment['url']] = $attachment['filename'];
							$attachments[] = $attachment;
						}
						$posts[$match[1]]['attachments'] = $attachments;
						if ($this->dbg) $this->write_err('Parsed '.count($attachments)." attachments in post with ID {$match[1]}.");
					}
				}
				if ($postid == $match[1]) $found = true;
				$postids[] = $match[1];
			}
		}

		unset($posts);

		return array($found, $postids);
	}

	abstract protected function get_post_url($forumid, $topicid, $postid, $with_hash = false);

	static function get_qanda_s() {
		return array(
			'q_lang' => array(
				'q' => 'Does the script work with forums using a language other than English?',
				'a' => 'Yes, or at least, it\'s intended to: if you experience problems, please <a href="'.FUPS_CONTACT_URL.'">contact me</a>.',
			),
			'q_how_long' => array(
				'q' => 'How long will the process take?',
				'a' => 'It depends on how many posts are to be retrieved, and how many pages they are spread across. You can expect to wait roughly one hour to extract and output 1,000 posts.',
			),
			'q_images_supported' => array(
				'q' => 'Are images supported?',
				'a' => 'Yes when scraping based on "Extract User ID"; no when scraping based on "Forum IDs". In the case of the former: if you check "Scrape images" (checked by default), then images are downloaded along with the posts. If not, then all relative image URLs are converted to absolute URLs, so images will display in the HTML output files so long as you are online at the time of viewing those files.',
			),
			'q_attachments_supported' => array(
				'q' => 'Is the downloading of attachments supported?',
				'a' => static::supports_feature_s('attachments') ? 'Yes when scraping based on "Extract User ID"; no when scraping based on "Forum IDs". In the case of the former: if you check "Scrape attachments" (checked by default), then attachments are downloaded along with the posts.' : 'In general, yes, but not yet for '.static::get_forum_type_s().' forums.',
			),
			'q_why_slow' => array(
				'q' => 'Why is this script so slow?',
				'a' => 'So as to avoid hammering other people\'s web servers, the script pauses for five seconds between each page retrieval.',
			),
		);
	}

	abstract protected function get_search_url();

	public function get_settings_array() {
		$default_settings = array(
			'forum_type' => array(
				'label'       => 'Forum type'                            ,
				'default'     => static::classname_to_forum_type_s(get_class($this)),
				'description' => 'Specifies the forum type (e.g. "phpBB" or "XenForo").',
				'required'    => true                                    ,
				'one_of_required' => false                               ,
				'hidden'      => false                                   ,
				'readonly'    => true                                    ,
			),
			'base_url' => array(
				'label'       => 'Base forum URL'                        ,
				'default'     => ''                                      ,
				'description' => 'Set this to the base URL of the forum.',
				'style'       => 'min-width: 300px;'                     ,
				'required'    => true                                    ,
				'one_of_required' => false                               ,
			),
			'extract_user_id' => array(
				'label'       => 'Extract User ID'                       ,
				'default'     => ''                                      ,
				'description' => 'Set this to the user ID of the user whose posts are to be extracted.',
				'required'    => !static::supports_feature_s('forums_dl'),
				'one_of_required' => static::supports_feature_s('forums_dl'),
			)
		);
		if (static::supports_feature_s('forums_dl')) {
			$default_settings = array_merge($default_settings, array(
				'forum_ids'  => array(
					'label' => 'Forum IDs',
					'default' => '',
					'description' => 'Set this to a comma-separated list of IDs of any forums that you wish for FUPS to scrape.',
					'required'    => false,
					'one_of_required' => true,
				),
			));
		}
		if (static::supports_feature_s('login')) {
			$default_settings = array_merge($default_settings, array(
				'login_user'  => array(
					'label' => 'Login User Username',
					'default' => '',
					'description' => 'Set this to the username of the user whom you wish to log in as, or leave it blank if you do not wish FUPS to log in.',
					'required'    => false,
					'one_of_required' => false,
				),
				'login_password' => array(
					'label' => 'Login User Password',
					'default' => '',
					'description' => 'Set this to the password associated with the Login User Username (or leave it blank if you do not require login).',
					'type' => 'password',
					'required'    => false,
					'one_of_required' => false,
				),
			));
		}

		$default_settings = array_merge($default_settings, array(
			'start_from_date'  => array(
				'label' => 'Start From Date+Time',
				'default' => '',
				'description' => 'Set this to the datetime of the earliest post to be extracted i.e. only posts of this datetime and later will be extracted. If you do not set this (i.e. if you leave it blank) then all posts will be extracted. This value is parsed with PHP\'s <a href="http://www.php.net/strtotime">strtotime()</a> function, so check that link for details on what it should look like. An example of something that will work is: 2013-04-30 15:30.',
				'required' => false,
				'one_of_required' => false,
			),
			'php_timezone' => array(
				'label' => 'PHP Timezone',
				'default' => 'Australia/Hobart',
				'description' => 'Set this to the time zone in which the user\'s posts were made. Valid time zone values are listed starting <a href="http://php.net/manual/en/timezones.php">here</a>. This only applies when "Start From Date+Time" is set above, in which case the value that you supply for "Start From Date+Time" will be assumed to be in the time zone you supply here, as will the date+times for posts retrieved from the forum. It is safe to leave this value set to the default if you are not supplying a value for the "Start From Date+Time" setting.',
				'required' => false,
				'one_of_required' => false,
			),
			'download_images' => array(
				'label' => 'Scrape images',
				'default' => true,
				'description' => 'Check this box if you want FUPS to scrape all images in posts too, and to adjust image URLs to refer the local, downloaded images. Note that images which are attached to posts, but which are not included inline in the post itself, will not be scraped'.(static::supports_feature_s('attachments') ? ' unless you also check "Scrape attachments" below.' : ' (because FUPS does not yet support the scraping of attachments for '.static::get_forum_type_s().' forums).'),
				'type' => 'checkbox',
				'required' => false,
				'one_of_required' => false,
			),
		));

		if (static::supports_feature_s('attachments')) {
			$default_settings = array_merge($default_settings, array(
				'download_attachments'  => array(
					'label' => 'Scrape attachments',
					'default' => true,
					'description' => 'Check this box if you want FUPS to scrape all attachments to posts too.',
					'type' => 'checkbox',
					'required'    => false,
					'one_of_required' => false,
				),
			));
		}

		$default_settings = array_merge($default_settings, array(
			'non_us_date_format' => array(
				'label' => 'Non-US date format',
				'default' => false,
				'description' => 'Check this box if the forum from which you\'re scraping outputs dates in the non-US ordering dd/mm rather than the US ordering mm/dd. Applies only if day and month are specified by digits and separated by forward slashes.',
				'type' => 'checkbox',
				'required' => false,
				'one_of_required' => false,
			),
			'debug' => array(
				'label' => 'Output debug messages',
				'default' => false,
				'description' => 'Check this box if you want FUPS to output debug messages (they will appear in the error output).',
				'type' => 'checkbox',
				'hidden' => true,
				'required' => false,
				'one_of_required' => false,
			),
			'delay' => array(
				'label' => 'Consecutive request delay (seconds)',
				'default' => '5',
				'min'     => 5,
				'description' => 'Enter the number of seconds you wish for FUPS to delay between consecutive requests to the same web host (the minimum is five). This is required so as to avoid hammering other people\'s web servers.',
				'required' => false,
				'one_of_required' => false,
			)
		));

		return $default_settings;
	}

	static protected function get_settings_msg_s($settings_str) {
		return 'The session\'s settings are:'.PHP_EOL.$settings_str;
	}

	protected function get_settings_str() {
		$settings_str = '';
		foreach ($this->settings as $k => $v) {
			if ($v && in_array($k, $this->private_settings)) {
				$v = '[redacted]';
			}
			if (is_array($v)) $v = var_export($v, true);
			$settings_str .= "\t$k=$v".PHP_EOL;
		}

		return $settings_str;
	}

	abstract protected function get_topic_page_url($forum_id, $topic_id, $topic_pg_counter);

	abstract protected function get_topic_url($forumid, $topicid);

	abstract protected function get_user_page_url();

	static public function get_valid_forum_types_s() {
		static $ignored_files = array('.', '..', 'CFUPSBase.php');
		$ret = array();
		$class_files = scandir(__DIR__);
		if ($class_files) foreach ($class_files as $class_file) {
			if (!in_array($class_file, $ignored_files)) {
				$class = self::class_file_to_forum_type_s($class_file);
				$ret[] = $class;
			}
		}

		return $ret;
	}

	protected function hook_after__init_user_post_search  () {} // Run after progress level 0
	protected function hook_after__user_post_search       () {} // Run after progress level 1
	protected function hook_after__topic_post_sort        () {} // Run after progress level 2
	protected function hook_after__posts_retrieval        () {} // Run after progress level 3
	protected function hook_after__extract_per_thread_info() {} // Run after progress level 4
	protected function hook_after__handle_missing_posts   () {} // Run after progress level 5
	protected function hook_after__download_files         () {} // Run after progress level 6
	protected function hook_after__init_forums_extract    () {} // Run after progress level 7
	protected function hook_after__forums_extract         () {} // Run after progress level 8
	protected function hook_after__forum_topics_extract   () {} // Run after progress level 9
	protected function hook_after__write_output           () {} // Run after progress level 10
	protected function hook_after__check_send_non_fatal_err_email() {} // Run after progress level 11

	protected function init_forum_page_counter() {
		$this->forum_page_counter = 0;
	}

	protected function init_post_search_counter() {
		$this->post_search_counter = 0;
	}

	protected function init_search_user_posts() {}

	protected function init_topic_pg_counter() {
		$this->topic_pg_counter = 0;
	}

	static public function read_forum_type_from_settings_file_s($settings_filename) {
		$settings_raw = static::read_settings_raw_s($settings_filename);
		return isset($settings_raw['forum_type']) ? $settings_raw['forum_type'] : false;
	}

	protected function read_settings() {
		$raw_settings = $this->read_settings_raw_s($this->settings_filename);
		$settings_arr = $this->get_settings_array();
		$optional_settings = array();
		$required_settings = array();
		foreach ($settings_arr as $setting => $opts) {
			if ($opts['required']) $required_settings[] = $setting;
			else                   $optional_settings[] = $setting;
		}
		foreach ($raw_settings as $setting => $value) {
			if (isset($settings_arr[$setting])) {
				if (isset($settings_arr[$setting]['type']) && $settings_arr[$setting]['type'] == 'checkbox') {
					$value = !in_array($value, array('0', 'off', 'false', 'no'));
				}
				if (isset($settings_arr[$setting]['min']) && $value < $settings_arr[$setting]['min']) {
					$this->write_err('Invalid value for setting "'.$setting.'": specified value "'.$value.'" is less than the minimum, '.$settings_arr[$setting]['min'].'. Resetting this value to that minimum.');
					$value = $settings_arr[$setting]['min'];
				}
				$this->settings[$setting] = $value;
			} else	$this->write_err('Invalid setting: "'.$setting.'".', __FILE__, __METHOD__, __LINE__);
		}
		foreach ($optional_settings as $setting) {
			if (isset($settings_arr[$setting]['type']) && $settings_arr[$setting]['type'] == 'checkbox') {
				if (!isset($this->settings[$setting])) $this->settings[$setting] = false;
			} else if (!isset($this->settings[$setting]) && isset($settings_arr[$setting]['default'])) $this->settings[$setting] = $settings_arr[$setting]['default'];

		}
		$missing = array_diff($required_settings, array_keys($this->settings));
	}

	static public function read_settings_raw_s($settings_filename) {
		$ret = array();
		$contents = file_get_contents($settings_filename);
		$contents_a = explode(PHP_EOL, $contents);
		$settings = array();
		foreach ($contents_a as $line) {
			$a = explode('=', $line, 2);
			if (count($a) < 2) continue;
			$setting = $a[0];
			$value = $a[1];
			$ret[$setting] = $value;
		}

		return $ret;
	}

	static protected function record_err_admin_s($msg, $file, $method, $line, &$have_written_to_admin_err_file, $classname = null, $html = false, $settings_str = false, $token = false, $dbg = false, $resumable = false) {
		$ferr = fopen('php://stderr', 'a');
		$html_msg = $html !== false ? 'The relevant page\'s HTML is:'.PHP_EOL.PHP_EOL.$html.PHP_EOL.PHP_EOL.PHP_EOL.PHP_EOL.PHP_EOL.PHP_EOL : '';
		$settings_msg = (!$have_written_to_admin_err_file && $settings_str) ? static::get_settings_msg_s($settings_str) : '';
		$classname_msg = (!$have_written_to_admin_err_file && $classname) ? static::get_classname_msg_s($classname).PHP_EOL.PHP_EOL : '';
		$full_admin_msg = $classname_msg.$settings_msg.PHP_EOL.static::get_formatted_err_s($method, $line, $file, $msg).($resumable ? ' N.B. This error is RESUMABLE.' : '').PHP_EOL.PHP_EOL.$html_msg;

		if ($token) {
			$filename = make_errs_admin_filename($token);
			if ($dbg) {
				if ($ferr !== false) {
					fwrite($ferr, 'Attempting to open "'.$filename.'" for appending.'.PHP_EOL);
				}
			}
			$ferr_adm = fopen($filename, 'a');
			if ($ferr_adm !== false) {
				if (fwrite($ferr_adm, $full_admin_msg) === false) {
					if ($dbg) fwrite($ferr, 'Error: failed to fwrite() to '.$filename.'.'.PHP_EOL);
				} else	$have_written_to_admin_err_file = true;
				fclose($ferr_adm);
			} else if ($dbg) fwrite($ferr, 'Error: failed to fopen() '.$filename.' for appending.'.PHP_EOL);
		} else	fwrite($ferr, $html_msg);

		fclose($ferr);
		return $full_admin_msg;
	}

	static protected function replace_contextual_urls_s($html, $root_rel_url_base, $path_rel_url_base, $current_protocol, $current_url, &$downld_file_urls_abs) {
		$ret = $html;

		if (!is_array($downld_file_urls_abs)) $downld_file_urls_abs = array();

		if (preg_match_all('(<(img|a) [^>]*>)i', $html, $matches, PREG_SET_ORDER)) {
			$search = array();
			$replace = array();
			foreach ($matches as $arr) {
				$tag = strtolower($arr[1]); // Compare tags case-insensitively
				$tag_match = $arr[0];
				if ($attrs = static::split_attrs_s($tag_match, $full_attr_matches)) {
					$search2 = array();
					$replace2 = array();
					foreach ($attrs as $attr => $value) {
						$attr = strtolower($attr); // Match attributes case-insensitively
						if (($tag == 'img' && $attr == 'src') || ($tag == 'a' && $attr = 'href')) {
							$url = htmlspecialchars_decode($value);

							$new_url = static::absolutify_url_s($url, $root_rel_url_base, $path_rel_url_base, $current_protocol, $current_url);
							if ($tag == 'img') $downld_file_urls_abs[$new_url] = $new_url;
							if ($new_url != $url) {
								$search2[] = $full_attr_matches[$attr];
								$replace2[] = $attr.'="'.htmlspecialchars($new_url).'"';
							}
						}
					}
					if ($search2) {
						$search[] = $tag_match;
						$replace[] = str_replace($search2, $replace2, $tag_match);
					}
				}
			}
			if ($search) {
				$ret = str_replace($search, $replace, $html);
			}
		}

		return $ret;
	}

	static protected function replace_downld_file_urls_s($html, $urls) {
		$ret = $html;

		if (preg_match_all('(<img [^>]*>)i', $html, $matches, PREG_PATTERN_ORDER)) {
			$search = array();
			$replace = array();
			foreach ($matches[0] as $match) {
				if ($attrs = static::split_attrs_s($match, $full_attr_matches)) {
					$search2 = array();
					$replace2 = array();
					foreach ($attrs as $attr => $value) {
						if (strtolower($attr) == 'src') { // Match attributes case-insensitively
							$url = htmlspecialchars_decode($value);
							if (isset($urls[$url])) {
								$search2[] = $full_attr_matches[$attr];
								$replace2[] = $attr.'="'.htmlspecialchars($urls[$url]).'"';
							}
						}
					}
					if ($search2) {
						$search[] = $match;
						$replace[] = str_replace($search2, $replace2, $match);
					}
				}
			}
			if ($search) {
				$ret = str_replace($search, $replace, $html);
			}
		}

		return $ret;
	}

	public function run($login_if_available = true) {
		$valid_protocols = (CURLPROTO_HTTP | CURLPROTO_HTTPS);

		$this->cookie_filename = make_cookie_filename($this->web_initiated ? $this->token : $this->settings_filename);

		if ($this->dbg) $this->write_err('Set cookie_filename to "'.$this->cookie_filename.'".');

		if (!$this->was_chained) {
			@unlink($this->cookie_filename); // Ensure that any existing cookie file on commandline reruns doesn't mess with us.
		}

		$this->ch = curl_init();
		if ($this->ch === false) {
			$this->exit_err('Failed to initialise cURL.', __FILE__, __METHOD__, __LINE__);
		}
		$opts = array(
			CURLOPT_USERAGENT       =>  FUPS_USER_AGENT,
			CURLOPT_FOLLOWLOCATION  =>            false, // We emulate this due to a bug - see do_send().
			CURLOPT_RETURNTRANSFER  =>             true,
			CURLOPT_HEADER          =>             true,
			CURLOPT_TIMEOUT         =>               20,
			CURLOPT_COOKIEJAR       => $this->cookie_filename,
			CURLOPT_COOKIEFILE      => $this->cookie_filename,
			CURLOPT_PROTOCOLS       => $valid_protocols, // Protect against malicious users specifying 'file://...' as base_url setting.
			CURLOPT_REDIR_PROTOCOLS => $valid_protocols, // Protect against malicious users specifying a base_url setting to a server which redirects to 'file://...'.
		);
		if (!curl_setopt_array($this->ch, $opts)) {
			$this->exit_err('Failed to set the following cURL options:'.PHP_EOL.var_export($opts, true), __FILE__, __METHOD__, __LINE__);
		}

		# Login if necessary
		if (static::supports_feature_s('login')) {
			if (!$login_if_available) {
				if ($this->dbg) $this->write_err('Not bothering to check whether to log in again, because $login_if_available is false (probably we\'ve just chained without the -r parameter being passed).');
			} else	$this->check_do_login();
		}

		# Skip to forum extraction if user-extraction is not operative
		if (!$this->settings['extract_user_id']) {
			$this->progress_level = 7;
		}

		# Find all of the user's posts through the search feature
		if ($this->progress_level == 0) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			$this->check_get_username();
			$this->search_page_num = 1;
			$this->init_post_search_counter();
			$this->init_search_user_posts();
			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__init_user_post_search();
			$this->progress_level++;
		}
		if ($this->progress_level == 1) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			do {
				$this->write_status('Scraping search page for posts starting from page #'.$this->search_page_num.'.');
				$num_posts_found = $this->find_author_posts_via_search_page();
				if ($this->dbg) $this->write_err('Found '.$num_posts_found.' posts.');
				$this->total_posts += $num_posts_found;
				$this->search_page_num++;
				$this->check_do_chain();
			} while ($this->progress_level == 1);
			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level-1];
			$this->$hook_method(); // hook_after__user_post_search();
		}

		# Retrieve the contents of all of the user's posts
		if ($this->progress_level == 2) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			$this->write_status('Now scraping contents of '.$this->total_posts.' posts.');
			# If the current topic ID is already set, then we are continuing after having chained.
			$go = is_null($this->current_topic_id);
			foreach ($this->posts_data as $topicid => $dummy) {
				if (!$go && $this->current_topic_id == $topicid) $go = true;
				if ($go) {
					$this->current_topic_id = $topicid;
					$t =& $this->posts_data[$topicid];
					$posts =& $t['posts'];
					$done = false;
					while (!$done) {
						$done = true;
						foreach ($posts as $postid => $dummy2) {
							$p =& $posts[$postid];
							if ($p['content'] == null && !isset($this->posts_not_found[$postid]) && !isset($this->empty_posts[$postid])) {
								$this->get_post_contents($t['forumid'], $topicid, $postid);
								$this->write_status('Retrieved '.$this->num_posts_retrieved.' of '.$this->total_posts.' posts.');
								$done = false;
							}
							unset($p);
							$this->check_do_chain();
						}
					}
					unset($t);
					unset($posts);
				}
			}

			$this->current_topic_id = null; # Reset this for progress level 3

			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__posts_retrieval();
			$this->progress_level++;
		}

		# Extract per-thread information: thread author
		if ($this->progress_level == 3) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			# If the current topic ID is already set, then we are continuing after having chained.
			$go = is_null($this->current_topic_id);
			$total_threads = count($this->posts_data);
			foreach ($this->posts_data as $topicid => $dummy) {
				if (!$go) {
					if ($this->current_topic_id == $topicid) $go = true;
				} else {
					$topic =& $this->posts_data[$topicid];
					if (!isset($topic['startedby'])) {
						$url = $this->get_topic_url($topic['forumid'], $topicid);
						$this->set_url($url);
						$html = $this->do_send();
						if (!$this->skins_preg_match('thread_author', $html, $matches)) {
							$this->write_and_record_err_admin("Error: couldn't find a match for the author of the thread with topic id '$topicid'.  The URL of the page is <".$url.'>.', __FILE__, __METHOD__, __LINE__, $html);
							$topic['startedby'] = '???';
						} else {
							$topic['startedby'] = $matches[1];
							if ($this->dbg) $this->write_err("Added author of '{$topic['startedby']}' for topic id '$topicid'.");
							$this->num_thread_infos_retrieved++;
							$this->write_status('Retrieved author for '.$this->num_thread_infos_retrieved.' of '.$total_threads.' threads.');
						}
						$this->current_topic_id = $topicid;
					}
					unset($topic); // Break the reference otherwise we get corruption during sorting.
					$this->check_do_chain();
				}
			}
			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__extract_per_thread_info();
			$this->progress_level++;
		}

		# Sort topics and posts
		if ($this->progress_level == 4) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			$this->write_status('Sorting posts and topics subsequent to scraping posts\' contents.');

			# Sort topics in ascending alphabetical order
			uasort($this->posts_data, 'cmp_topics_topic');

			# Sort posts within each topic into ascending timestamp order
			foreach ($this->posts_data as $topicid => $dummy) {
				$posts =& $this->posts_data[$topicid]['posts'];
				uasort($posts, 'cmp_posts_date');
			}
			if ($this->dbg) {
				$this->write_err('SORTED POSTS::');
				foreach ($this->posts_data as $topicid => $topic) {
					$this->write_err("\tTopic: {$topic['topic']}\tTopic ID: $topicid");
					foreach ($topic['posts'] as $postid => $p) {
						$newts = strftime('%c', $p['timestamp']);
						$this->write_err("\t\tTime: $newts ({$p['ts']}); Post ID: $postid");
					}
				}
			}
			$this->write_status('Finished sorting posts and topics.');
			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__topic_post_sort();
			$this->progress_level++;
		}

		# Warn about missing posts
		if ($this->progress_level == 5) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			if ($this->posts_not_found) {
				$this->write_err(PHP_EOL.PHP_EOL.PHP_EOL."The contents of the following posts were not found::".PHP_EOL.PHP_EOL.PHP_EOL);
				foreach ($this->posts_not_found as $postid => $dummy) {
					$a = $this->find_post($postid);
					if ($a == false) $this->write_err("\tError: failed to find post with ID '$postid' in internal data.");
					else {
						list($p, $t, $topicid) = $a;
						$this->write_err("\t{$p['posttitle']} ({$t['topic']}; {$p['timestamp']}; {$t['forum']}; forumid: {$t['forumid']}; topicid: $topicid; postid: $postid; ".$this->get_post_url($t['forumid'], $topicid, $postid).')');
					}
				}
			}
			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__handle_missing_posts();
			$this->progress_level++;
		}

		# Download downloable files if necessary
		if ($this->progress_level == 6) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);

			if ((isset($this->settings['download_images']) && $this->settings['download_images']
			     ||
			     isset($this->settings['download_attachments']) && $this->settings['download_attachments']
			    )
			    &&
			    $this->downld_file_urls
			   ) {
				$this->write_status('Downloading images and/or attachments.');
				$output_downld_file_dirname = 'files';
				$output_downld_file_path = $this->output_dirname.$output_downld_file_dirname;
				if (!file_exists($output_downld_file_path) && !mkdir($output_downld_file_path, 0775, true)) {
					$this->exit_err_resumable('Failed to create "files" output directory "'.$output_downld_file_path.'".', __FILE__, __METHOD__, __LINE__);
				}

				ksort($this->downld_file_urls);
				$downld_file_server = '';
				foreach ($this->downld_file_urls as $download_url => &$url) {
					# Don't redownload if we chained during this loop.
					if (isset($this->downld_file_urls_downloaded[$download_url]) || isset($this->downld_file_urls_failed_download[$download_url])) continue;

					$parsed = parse_url($download_url);
					if ($downld_file_server == $parsed['host']) {
						$this->wait_courteously();
					} else	$downld_file_server = $parsed['host'];
					$downld_file_filename_org = sanitise_filename(static::get_downld_file_filename_from_url_s($url));

					# Handle duplicate filenames
					$downld_file_filename = ensure_unique_filename($output_downld_file_path, $downld_file_filename_org);
					$downld_file_path = $output_downld_file_path.'/'.$downld_file_filename;

					$fp = fopen($downld_file_path, 'wb');
					if ($fp === false) {
						$this->exit_err_resumable('Failed to create file "'.$downld_file_filename.'" for writing in "file" output directory.', __FILE__, __METHOD__, __LINE__);
					}

					$this->set_url($download_url);
					$opts = array(
						CURLOPT_FILE   => $fp,
						CURLOPT_HEADER =>   0,
					);
					if (!curl_setopt_array($this->ch, $opts)) {
						$this->exit_err_resumable('Failed to set the following cURL options:'.PHP_EOL.var_export($opts, true), __FILE__, __METHOD__, __LINE__);
					}
					$this->write_status('Downloading file from URL <'.$download_url.'>.');
					$redirect = true;
					$this->do_send($redirect, /*$quit_on_error*/false, $err, /*$check_get_board_title*/false);
					if ($err) {
						$this->downld_file_urls_failed_download[$download_url] = true;
						if ($this->dbg) $this->write_err('Failed to download file from URL <'.$download_url.'>.', __FILE__, __METHOD__, __LINE__);
					} else	$this->downld_file_urls_downloaded[$download_url] = $output_downld_file_dirname.'/'.$downld_file_filename;
					fclose($fp);
					if ($err) unlink($downld_file_path);
					else {
						$url = $output_downld_file_dirname.'/'.rawurlencode($downld_file_filename);
						if ($this->dbg) $this->write_err('Successfully downloaded file from URL <'.$download_url.'> to "'.$downld_file_path.'".');
					}

					$this->check_do_chain();
				}

				if (!$this->downld_file_urls_downloaded) {
					rmdir($output_downld_file_path);
				} else {
					$this->write_status('Replacing URLs in posts with local URLs for downloaded files.');

					foreach ($this->posts_data as $topicid => &$topic_data) {
						foreach ($topic_data['posts'] as $postid => &$post_data) {
							$post_data['content'] = static::replace_downld_file_urls_s($post_data['content'], $this->downld_file_urls);
						}
					}
				}
			}

			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__download_files();
			$this->progress_level++;
		}

		if (!isset($this->settings['forum_ids_arr']) || count($this->settings['forum_ids_arr']) <= 0) {
			$this->progress_level = 10;
		}

		# Initialise scraping from individual forums based on supplied forumids.
		if ($this->progress_level == 7) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);

			# Null indicates that we haven't scraped anything yet. We don't want to init
			# these vars to 0 if we're chaining and have already begun scraping.
			if (is_null($this->forum_idx)) {
				$this->forum_idx = 0;
				$this->forum_pg  = 0;
				$this->init_forum_page_counter();
			}

			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__init_forums_extract();
			$this->progress_level++;
		}

		# Extract all topics within forums given their supplied IDs.
		if ($this->progress_level == 8) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);

			while ($this->forum_idx < count($this->settings['forum_ids_arr'])) {
				$id = $this->settings['forum_ids_arr'][$this->forum_idx];
				if (!isset($this->forum_data[$id])) {
					$this->forum_data[$id] = array('topics' => array());
				}
				$num_topics_found = $this->scrape_forum_pg();
				$this->check_do_chain();
			}

			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__forums_extract();
			$this->progress_level++;
		}

		# Extract all posts in all topics retrieved in the previous progress level.
		if ($this->progress_level == 9) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);

			if (is_null($this->forum_idx2)) {
				$this->forum_idx2 = 0;
				$this->topic_idx  = 0;
				$this->topic_pg   = 0;
				$this->init_topic_pg_counter();
			}

			while ($this->forum_idx2 < count($this->settings['forum_ids_arr'])) {
				$id = $this->settings['forum_ids_arr'][$this->forum_idx2];
				$forum =& $this->forum_data[$id];
				$t_keys = array_keys($forum['topics']);
				if ($this->topic_idx >= count($t_keys)) {
					$this->forum_idx2++;
					$this->topic_idx = 0;
					$this->topic_pg  = 0;
					$this->init_topic_pg_counter();
					continue;
				}
				$topic =& $forum['topics'][$t_keys[$this->topic_idx]];
				$this->scrape_topic_page();
				$this->check_do_chain();
			}

			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__forum_topics_extract();
			$this->progress_level++;
		}

		# Write output
		if ($this->progress_level == 10) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			$this->write_status('Writing output.');

			# Write all output variants
			$this->write_output();

			# Signal that we are done
			$this->write_status('DONE');

			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__write_output();
			$this->progress_level++;
		}

		# Potentially send an admin email re non-fatal errors.
		if ($this->progress_level == 11) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);

			$this->check_do_send_errs();

			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->$hook_method(); // hook_after__check_send_non_fatal_err_email();
			$this->progress_level++;
		}
	}

	protected function scrape_forum_pg() {
		$id = $this->settings['forum_ids_arr'][$this->forum_idx];
		$pg = $this->forum_pg;
		$this->write_status('Attempting to scrape page '.($pg+1).' of forum with ID '.$id.'.');
		$this->set_url($this->get_forum_page_url($id, $this->forum_page_counter));
		$html = $this->do_send();

		if (!$this->skins_preg_match_all('forum_page_topicids', $html, $matches)) {
			$this->write_and_record_err_admin('Error: couldn\'t find any topic matches on one of the forum pages. The URL of the page is '.$this->last_url, __FILE__, __METHOD__, __LINE__, $html);
			$this->forum_idx++;
			$this->forum_pg = 0;
			$this->init_forum_page_counter();
			return 0;
		}

		$num_topics_found = count($matches);
		foreach($matches as $match) {
			$topicid = $match[1];
			$forumid = $this->settings['forum_ids_arr'][$this->forum_idx];
			$this->forum_data[$forumid]['topics'][$topicid] = array();
		}

		if ($this->dbg) $this->write_err('Found '.$num_topics_found.' topics on page '.($this->forum_pg+1).' in forum with ID "'.$id.'".');

		if ($pg == 0) {
			if (!$this->skins_preg_match('forum_title', $html, $matches)) {
				$this->write_and_record_err_admin('Error: couldn\'t find the title of the forum on the forum page with forum ID "'.$id.'". The URL of the page is '.$this->last_url, __FILE__, __METHOD__, __LINE__, $html);
			} else	$this->forum_data[$forumid]['title'] = $matches[1];
		}

		if ($last_forum_page = $this->skins_preg_match('last_forum_page', $html, $matches)) {
			if ($this->dbg) $this->write_err('Matched "last_forum_page" regex; moving to the next forum (if any).');
			$this->forum_idx++;
			$this->forum_pg = 0;
			$this->init_forum_page_counter();
		} else {
			$this->forum_pg++;
		}

		$this->scrape_forum_pg__end_hook($html, $num_topics_found, $last_forum_page);

		return $num_topics_found;
	}

	protected function scrape_forum_pg__end_hook($html, $num_topics_found, $last_forum_page) {
		if ($num_topics_found > 0 && !$last_forum_page) $this->forum_page_counter += $num_topics_found;	
	}

	protected function scrape_topic_page() {
		$id = $this->settings['forum_ids_arr'][$this->forum_idx2];
		$forum =& $this->forum_data[$id];
		$t_keys = array_keys($forum['topics']);
		$topic =& $forum['topics'][$t_keys[$this->topic_idx]];

		$url = $this->get_topic_page_url($id, $t_keys[$this->topic_idx], $this->topic_pg_counter);
		$this->set_url($url);

		$this->write_status('Attempting to scrape page '.($this->topic_pg+1).' of topic with ID '.$t_keys[$this->topic_idx].' in forum with ID '.$id.'.');

		$redirect_url = false;
		$html = $this->do_send($redirect_url);
		if ($redirect_url) {
			$redirect_topic_id = $this->get_topic_id_from_topic_url($redirect_url);
			$original_topic_id = $t_keys[$this->topic_idx];
			if ($redirect_topic_id != $original_topic_id) {
				if ($this->dbg) $this->write_err("Topic page was redirected from topic with ID '$original_topic_id' to topic with ID '$redirect_topic_id'. Skipping to next topic.", __FILE__, __METHOD__, __LINE__);
				goto scrape_topic_page__next_topic;
			}
		}

		if (!isset($topic['title'])) {
			if (!$this->skins_preg_match('topic', $html, $matches)) {
				$this->write_err('Failed to match the "topic" regex - could not determine the title of the topic with ID "'.$t_keys[$this->topic_idx].'". The URL of the topic page is: <'.$this->last_url.'>.', __FILE__, __METHOD__, __LINE__);
			} else	$topic['title'] = $matches[1];
		}

		if (!$this->skins_preg_match_all('post_contents_ext', $html, $matches, 'post_contents_ext_order')) {
			$this->write_and_record_err_admin('Error: couldn\'t find any posts on topic with ID "'.$t_keys[$this->topic_idx].'" and page counter set to "'.$this->topic_pg_counter.'".  The URL of the page is '.$this->last_url, __FILE__, __METHOD__, __LINE__, $html);
			goto scrape_topic_page__next_topic;
		}

		foreach ($matches as $match) {
			$author   = $match[$match['match_indexes']['author'  ]];
			$title    = $match[$match['match_indexes']['title'   ]];
			$ts_raw   = $match[$match['match_indexes']['ts'      ]];
			$postid   = $match[$match['match_indexes']['postid'  ]];
			$contents = $match[$match['match_indexes']['contents']];

			$this->ts_raw_hook($ts_raw);

			$ts = $this->strtotime_intl($ts_raw);
			if ($ts === false) {
				$err_msg = "Error: strtotime_intl failed for '$ts_raw'.";
				if ((!isset($this->settings['non_us_date_format']) || !$this->settings['non_us_date_format']) && strpos($ts_raw, '/') !== false) {
					$err_msg .= ' Hint: Perhaps you need to check the "Non-US date format" box on the previous page.';
				}
				$this->write_err($err_msg);
			}

			if (!isset($this->forum_data[$id]['topics'][$t_keys[$this->topic_idx]]['posts'])) {
				$this->forum_data[$id]['topics'][$t_keys[$this->topic_idx]]['posts'] = array();
			}
			$this->forum_data[$id]['topics'][$t_keys[$this->topic_idx]]['posts'][$postid] = array(
				'author'   => $author  ,
				'title'    => $title   ,
				'ts'       => $ts      ,
				'datetime' => trim($ts_raw),
				'contents' => $contents,
			);
		}

		$this->scrape_topic_page__end_hook($html, $matches);

		if ($this->skins_preg_match('last_topic_page', $html, $matches)) {
			if ($this->dbg) $this->write_err('Matched "last_topic_page" regex; we have finished finding posts for this topic.');
scrape_topic_page__next_topic:
			$this->topic_idx++;
			$this->topic_pg = 0;
			$this->init_topic_pg_counter();
		} else	$this->topic_pg++;
	}

	protected function scrape_topic_page__end_hook($html, $matches) {
		$this->topic_pg_counter += count($matches);
	}

	static protected function send_err_mail_to_admin_s($full_admin_msg, $token = false, $type = FUPS_EMAIL_TYPE_NONFATAL) {
		global $argv;

		switch ($type) {
		case FUPS_EMAIL_TYPE_FATAL:
			$body = 'Fatal error';
			$subject = 'Fatal error in FUPS process';
			break;
		case FUPS_EMAIL_TYPE_RESUMABLE:
			$body = 'Resumable error';
			$subject = 'Resumable error in FUPS process';
			break;
		default: /* should only be FUPS_EMAIL_TYPE_NONFATAL */
			$body = 'Non-fatal error (s)';
			$subject = 'Non-fatal error(s) in FUPS process';
		}
		$body .= ' occurred in the FUPS process with commandline arguments:'.PHP_EOL.var_export($argv, true).PHP_EOL.PHP_EOL;
		$body .= $full_admin_msg;
		if ($token) $subject .= ' '.$token;
		$headers = 'From: '.FUPS_EMAIL_SENDER."\r\n".
				"MIME-Version: 1.0\r\n" .
				"Content-type: text/plain; charset=UTF-8\r\n";
		mail(FUPS_EMAIL_RECIPIENT, $subject, $body, $headers);
	}

	protected function set_url($url) {
		if (!curl_setopt($this->ch, CURLOPT_URL, $url)) {
			$this->exit_err_resumable('Failed to set cURL URL: <'.$url.'>.', __FILE__, __METHOD__, __LINE__);
		} else	$this->last_url = $url;
	}

	protected function skins_preg_match_base($regexp_id, $text, &$matches, $all = false, $match_indexes_id = false, $combine = false) {
		$ret = false;
		$matches = array();
		foreach ($this->regexps as $skin => $skin_regexps) {
			if (!empty($skin_regexps[$regexp_id])) {
				$regexp = $skin_regexps[$regexp_id];
				if (
					($all && preg_match_all($regexp, $text, $matches_tmp, PREG_SET_ORDER))
					||
					(!$all && preg_match($regexp, $text, $matches_tmp))
				) {
					$ret = true;
					if ($match_indexes_id !== false) {
						foreach ($matches_tmp as &$match) {
							$match['match_indexes'] = $skin_regexps[$match_indexes_id];
						}
					}
					if (!$combine) {
						$matches = $matches_tmp;
						break;
					} else {
						$matches = array_merge($matches, $matches_tmp);
					}
				}
			}
		}

		return $ret;
	}

	protected function skins_preg_match($regexp_id, $text, &$matches) {
		return $this->skins_preg_match_base($regexp_id, $text, $matches, false);
	}

	protected function skins_preg_match_all($regexp_id, $text, &$matches, $match_indexes_id = false, $combine = false) {
		return $this->skins_preg_match_base($regexp_id, $text, $matches, true, $match_indexes_id, $combine);
	}

	static protected function split_attrs_s($tag, &$full_matches = array()) {
		$ret = array();

		if (preg_match_all('(\\b(\\w+)\\s*=\\s*"([^"]*)")', $tag, $attrs, PREG_SET_ORDER)) {
			foreach ($attrs as $attr) {
				$full_matches[$attr[1]] = $attr[0];
				$ret[$attr[1]] = $attr[2];
			}
		}

		return $ret;
	}

	protected function strtotime_intl($time_str) {
		$time_str_org = $time_str;
		$non_us_date_format = isset($this->settings['non_us_date_format']) && $this->settings['non_us_date_format'];
		if ($non_us_date_format) {
			// Switch month and day in that part of the date formatted as either m/d/y or m/d/y,
			// where m and d are either one or two digits, and y is either two or four digits.
			// The phrase m/d/y can occur anywhere in the string so long as at either end it is
			// either separated from the rest of the string by a space or occurs at the
			// beginning/end of the string (as such, it may comprise the entire string).
			$time_str = preg_replace('#(^|\s)(\d{1,2})/(\d{1,2})(/\d\d|/\d\d\d\d|)(\s|$)#', '$1$3/$2$4$5', $time_str);
		} else {
			$re = '((\\d{2})\\s(\\d{2})\\s(\\d{2})\\s(\\d{2}):(\\d{2}))';
			if (preg_match($re, $time_str, $matches)) {
				$time_str_new = preg_replace($re, '20$1-$2-$3 $4:$5', $time_str);
				$ret = strtotime($time_str_new);
				if ($ret !== false) return $ret;
			}
		}
		if ($this->dbg) $this->write_err('Running strtotime() on "'.$time_str.'".'.($non_us_date_format ? 'This was derived from "'.$time_str_org.'" due to the "Non-US date format" setting being in effect.' : ''));
		$ret = strtotime($time_str);
		if ($ret === false) {
			if ($this->dbg) $this->write_err('strtotime() failed on "'.$time_str.'". Trying again after replacing international tokens.');

			// This is necessary for translated phpBB forums

			global $intl_data;

			$comps = preg_split('/\\b/', $time_str);
			foreach ($intl_data as $intl_arr) {
				$repls = $comps;
				foreach ($comps as $i => $comp) {
					$repls[$i] = str_replace('May_short', 'May', array_merge((array)$repls[$i], array_unique(array_keys($intl_arr, $comp))));
				}
				foreach (arrays_combos($repls) as $combo) {
					$ret = strtotime(implode('', $combo));
					if ($ret) break;
				}
				if ($ret) break;
			}
		}
		
		return $ret;
	}

	public static function supports_feature_s($feature) {
		static $default_features = array(
			'login'       => false,
			'attachments' => false,
			'forums_dl'   => false
		);

		return isset($default_features[$feature]) ? $default_features[$feature] : false;
	}

	# Returns empty string on successful validation, or, if URL is invalid, then
	# if $exit_on_err is set to true, which it is by default, exits, otherwise
	# (i.e. if $exit_on_err is false) returns error message..
	protected function validate_url($url, $url_label, $exit_on_err = true) {
		static $valid_schemes = array('http', 'https');

		$err = '';

		$parsed = parse_url($url);
		if ($parsed === false) {
			$err = ucfirst($url_label).' ("'.$url.'") was invalid according to PHP\'s parse_url() function, which returned false for it.';
			if ($exit_on_err) $this->exit_err($err, __FILE__, __METHOD__, __LINE__);
		} else {
			if (!in_array($parsed['scheme'], $valid_schemes)) {
				$err = 'The URL scheme ("'.$parsed['scheme'].'") of '.$url_label.' ("'.$url.'") is invalid; it should be one of: '.implode(', ', $valid_schemes).'.';
				if ($exit_on_err) $this->exit_err($err, __FILE__, __METHOD__, __LINE__);
			}
			$ip = gethostbyname($parsed['host']);
			if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				$err = 'The host ("'.$parsed['host'].'") in '.$url_label.' ("'.$url.'") maps to the IP address "'.$ip.'", which is a private or reserved IP address, or is unmapped.';
				if ($exit_on_err) $this->exit_err($err, __FILE__, __METHOD__, __LINE__);
			}
		}

		return $err;
	}

	# Check the settings in the $this->settings array. Exits on invalid setting(s).
	# If overriding this function, make sure to take appropriate action on
	# invalid settings yourself, including exiting if appropriate.
	protected function validate_settings() {
		$this->validate_url($this->settings['base_url'], 'the supplied base_url setting', true);
	}

	protected function wait_courteously() {
		if ($this->web_initiated) {
			$cancellation_filename = make_cancellation_filename($this->token);
			if (file_exists($cancellation_filename)) {
				$this->write_status('Found a cancellation file. CANCELLED');
				if ($this->dbg) $this->write_err('Found a cancellation file; exiting.', __FILE__, __METHOD__, __LINE__);
				exit;
			}
		}
		if ($this->dbg) $this->write_err("Waiting courteously for {$this->settings['delay']} seconds.");
		sleep($this->settings['delay']);
	}

	protected function write_and_record_err_admin($msg, $file, $method, $line, $html = false) {
		$token = $this->web_initiated ? $this->token : false;
		$dbg   = $this->dbg;
		$this->write_err($msg, $file, $method, $line);
		$settings_str = $this->get_settings_str();

		static::record_err_admin_s($msg, $file, $method, $line, $this->have_written_to_admin_err_file, get_class($this), $html, $settings_str, $token, $dbg);
		$this->have_written_to_admin_err_file = true;
	}

	public function write_err($msg, $file = null, $method = null, $line = null) {
		static $ferr = false;
		if ($ferr === false) {
			if ($this->errs_filename === false) {
				$ferr =  fopen('php://stderr'      , 'w');
			} else {
				$ferr = @fopen($this->errs_filename, 'a');
				// The above seems to fail on Windows, perhaps because generally
				// we are already redirecting stderr to that file - the below seems
				// to handle that case.
				if ($ferr === false) $ferr = fopen('php://stderr', 'w');
			}
		}
		static::write_err_s($ferr, $msg, $file, $method, $line);
	}

	static public function write_err_s($ferr, $msg, $file = null, $method = null, $line = null) {
		if (!is_null($file) || !is_null($method) || !is_null($line)) {
			$msg = static::get_formatted_err_s($method, $line, $file, $msg);
		}
		if ($ferr) {
			fwrite($ferr, $msg.PHP_EOL);
		} else	echo $msg;
	}

	protected function write_output() {
		$output_info = array();
		$wanted_downld_files = count($this->downld_file_urls) > 0;
		$have_downld_files = $wanted_downld_files && $this->downld_file_urls_downloaded;

		foreach ($this->get_output_variants() as $opv) {
			$op_filename =  make_output_filename($this->output_dirname, $opv['filename_appendix']);
			if ($this->{$opv['method']}($op_filename)) {
				$opts = array(
					'filename'    => make_output_filename('', $opv['filename_appendix']),
					'description' => ($wanted_downld_files ? 'The post output listing as: ' : '').$opv['description'],
					'size'        => stat($op_filename)['size'],
				);
				if (!$have_downld_files) {
					$opts['url'] = make_output_filename($this->output_dirname_web, $opv['filename_appendix']);
				}
				$output_info[] = $opts;
				if ($this->dbg) $this->write_err('Successfully wrote to the output file: "'.$op_filename.'".');
			} else	$this->write_err('Method '.__CLASS__.'->'.$opv['method'].'('.$op_filename.') returned false.', __FILE__, __METHOD__, __LINE__);
		}

		if ($this->web_initiated) {
			if ($wanted_downld_files) {
				if ($have_downld_files) {
					$downld_file_dir_size = 0;
					$downld_file_dir = $this->output_dirname.'files';
					$handle = opendir($downld_file_dir);
					if ($handle === false) {
						$this->write_err('Unable to open directory "'.$downld_file_dir.'" for reading.', __FILE__, __METHOD__, __LINE__);
					} else {
						while (($f = readdir($handle)) !== false) {
							if (!in_array($f, array('.', '..'))) {
								$downld_file_dir_size += stat($downld_file_dir.'/'.$f)['size'];
							}
						}
						closedir($handle);
					}
					$output_info[] = array(
						'filename'    => 'files/*',
						'description' => 'A directory containing all downloaded files.',
						'size'        => $downld_file_dir_size,
					);
					$this->add_downld_file_map_output_file("\n", 'UNIX/Linux/Mac', 'unix-linux-mac', $output_info);
					$this->add_downld_file_map_output_file("\r\n", 'MS-DOS/Windows', 'dos-win', $output_info);
				}
				if ($this->downld_file_urls_failed_download) {
					$this->add_downld_file_failed_dnlds_output_file("\n", 'UNIX/Linux/Mac', 'unix-linux-mac', $output_info, $have_downld_files);
					$this->add_downld_file_failed_dnlds_output_file("\r\n", 'MS-DOS/Windows', 'dos-win', $output_info, $have_downld_files);
				}
			}
			$zip_ext = '.all.zip';
			$zip_filename = make_output_filename($this->output_dirname, $zip_ext);
			$tmp_zip_path = FUPS_OUTPUTDIR.$this->token.$zip_ext;
			if ($this->archive_output($this->output_dirname, $tmp_zip_path)) {
				if (!rename($tmp_zip_path, $zip_filename)) {
					$this->write_err('Failed to move the temporary zip file to its end location.', __FILE__, __METHOD__, __LINE__);
				} else {
					array_unshift($output_info, array(
						'filename'    => make_output_filename('', $zip_ext),
						'url'         => make_output_filename($this->output_dirname_web, $zip_ext),
						'description' => 'A ZIP archive of all of the below files.',
						'size'        => stat($zip_filename)['size'],
					));
				}
			}

			$output_info_filepath = make_output_info_filename($this->token);
			$json = json_encode($output_info, JSON_PRETTY_PRINT);
			if ($json === false) {
				$this->write_err('Failed to encode output information as JSON.', __FILE__, __METHOD__, __LINE__);
			} else if (file_put_contents($output_info_filepath, $json) === false) {
				$this->write_err('Failed to write output information to "'.$output_info_filepath.'".', __FILE__, __METHOD__, __LINE__);
			}
		}
	}

	protected function write_output_html_dateasc($filename) {
		return $this->write_output_html(/*$thread_sort*/false, /*$post_sort*/'asc', $filename);
	}

	protected function write_output_html_datedesc($filename) {
		return $this->write_output_html(/*$thread_sort*/false, /*$post_sort*/'desc', $filename);
	}

	protected function write_output_html_threadasc_dateasc($filename) {
		return $this->write_output_html(/*$thread_sort*/'asc', /*$post_sort*/'asc', $filename);
	}

	protected function write_output_html_threadasc_datedesc($filename) {
		return $this->write_output_html(/*$thread_sort*/'asc', /*$post_sort*/'desc', $filename);
	}

	protected function write_output_html_threaddesc_dateasc($filename) {
		return $this->write_output_html(/*$thread_sort*/'desc', /*$post_sort*/'asc', $filename);
	}

	protected function write_output_html_threaddesc_datedesc($filename) {
		return $this->write_output_html(/*$thread_sort*/'desc', /*$post_sort*/'desc', $filename);
	}

	protected function write_output_json($filename) {
		$ret = false;
		$op_arr = $this->get_final_output_array();
		$this->array_to_utf8($op_arr);
		$op_arr['character_set'] = 'UTF-8';
		$json = json_encode($op_arr, JSON_PRETTY_PRINT);
		if ($json === false) {
			$this->write_err('Failed to encode final output array for "'.$filename.'" as JSON.', __FILE__, __METHOD__, __LINE__);
		} else if (file_put_contents($filename, $json) === false) {
			$this->write_err('Failed to write final output array as JSON to "'.$filename.'".', __FILE__, __METHOD__, __LINE__);
		} else	$ret = true;

		return $ret;
	}

	protected function write_output_json_forums($filename) {
		$ret = false;
		$op_arr = $this->forum_data;
		$this->array_to_utf8($op_arr);
		$op_arr['character_set'] = 'UTF-8';
		$json = json_encode($op_arr, JSON_PRETTY_PRINT);
		if ($json === false) {
			$this->write_err('Failed to encode final output array for "'.$filename.'" as JSON.', __FILE__, __METHOD__, __LINE__);
		} else if (file_put_contents($filename, $json) === false) {
			$this->write_err('Failed to write final output array as JSON to "'.$filename.'".', __FILE__, __METHOD__, __LINE__);
		} else	$ret = true;

		return $ret;
	}

	protected function write_output_php($filename) {
		$ret = false;
		$op_arr = $this->get_final_output_array();
		$php = var_export($op_arr, true);
		if (file_put_contents($filename, '<?php return '.$php.'; ?>'."\n") === false) {
			$this->write_err('Failed to write final output array as PHP to "'.$filename.'".', __FILE__, __METHOD__, __LINE__);
		} else	$ret = true;

		return $ret;
	}

	protected function write_output_php_serialised($filename) {
		$ret = false;
		$op_arr = $this->get_final_output_array();
		if (file_put_contents($filename, serialize($op_arr)) === false) {
			$this->write_err('Failed to write final output array as serialised PHP to "'.$filename.'".', __FILE__, __METHOD__, __LINE__);
		} else	$ret = true;

		return $ret;
	}

	protected function write_output_html($thread_sort, $post_sort, $filename) {
		// Normalise $thread_sort to one of 'asc', 'desc' and false,
		// the latter meaning "don't sort by threads, only by post dates".
		if (!in_array($thread_sort, array('asc', 'desc'))) $thread_sort = false;

		// Normalise $post_sort to one of 'asc' and 'desc', defaulting to 'desc'
		if ($post_sort !== 'asc') $post_sort = 'desc';

		$heading = 'Postings of '.htmlspecialchars($this->settings['extract_user']).' to <a href="'.htmlspecialchars($this->settings['base_url']).'">'.(isset($this->settings['board_title']) ? htmlspecialchars($this->settings['board_title']) : '[unknown]').'</a>';
		if (!empty($this->settings['start_from_date'])) $heading .= ' starting from '.htmlspecialchars($this->settings['start_from_date']);

		if (!ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE|PHP_OUTPUT_HANDLER_FLUSHABLE|PHP_OUTPUT_HANDLER_REMOVABLE)) {
			$this->write_err('Fatal error: unable to start output buffering.', __FILE__, __METHOD__, __LINE__);
			return false;
		}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en" xml:lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=<?php echo $this->charset !== null ? $this->charset : 'UTF-8'; ?>" />
<title><?php echo $heading; ?></title>
<?php echo $this->get_extra_head_lines(); ?>
</head>

<body style="font-family: Trebuchet MS; font-size: 8pt;">
<div style="padding: 0 50px 0 50px; width: 500px;">
	<div style="font-family: Arial Narrow; font-size: 10pt;">
		<h3><?php echo $heading; ?></h3>
		<table>
		<tr>
			<th style="text-align: left; font-family: Arial Narrow; font-size: 10pt;">Topic</th>
			<th style="text-align: left; font-family: Arial Narrow; font-size: 10pt;">Started By</th>
			<th style="text-align: left; font-family: Arial Narrow; font-size: 10pt;">Forum</th>
			<th style="text-align: left; font-family: Arial Narrow; font-size: 10pt;">#Posts</th>
		</tr>
<?php
		foreach ($this->posts_data as $topicid => $t) {
			echo '		<tr>'."\n";
			echo '			<td style="text-align: left; font-family: Arial Narrow; font-size: 10pt;">'.$t['topic'].'</td>'."\n";
			echo '			<td style="text-align: left; font-family: Arial Narrow; font-size: 10pt;">'.$t['startedby'].'</td>'."\n";
			echo '			<td style="text-align: left; font-family: Arial Narrow; font-size: 10pt;">'.$t['forum'].'</td>'."\n";
			echo '			<td style="text-align: right; font-family: Arial Narrow; font-size: 10pt;">'.count($t['posts']).'</td>'."\n";
			echo '		</tr>'."\n";
		}
?>
		<tr>
			<td colspan="3" style="font-family: Arial Narrow; font-size: 10pt;">Total posts:</td>
			<td style="text-align: right; font-family: Arial Narrow; font-size: 10pt;"><?php echo $this->total_posts; ?></td>
		</tr>
		<tr>
			<td colspan="3" style="font-family: Arial Narrow; font-size: 10pt;">Total topics:</td>
			<td style="text-align: right; font-family: Arial Narrow; font-size: 10pt;"><?php echo count($this->posts_data); ?></td>
		</tr>
		</table>
	</div>

	<br />
	<br />
<?php
		if ($thread_sort !== false) {
			$posts_data = $this->posts_data;
			if ($thread_sort === 'desc') {
				$posts_data = array_reverse($posts_data, true);
			}
			foreach ($posts_data as $topicid => $topic_data) {
				$posts = $topic_data['posts'];
				if ($post_sort === 'desc') {
					$posts = array_reverse($topic_data['posts'], true);
				}
				foreach ($posts as $postid => $post_data) {
					$this->write_post_output_html($topicid, $topic_data, $postid, $post_data);
				}
			}
		} else {
			$flat_posts = array();
			foreach ($this->posts_data as $topicid => $topic_data) {
				foreach ($topic_data['posts'] as $postid => $post_data) {
					$flat_posts[$post_data['timestamp']] = array(
						'topicid'    => $topicid   ,
						'topic_data' => $topic_data,
						'postid'     => $postid     ,
						'post_data'  => $post_data ,
					);
				}
			}
			if ($post_sort === 'asc') {
				ksort($flat_posts, SORT_NUMERIC);
			} else	krsort($flat_posts, SORT_NUMERIC);
			foreach ($flat_posts as $flat_post) {
				$this->write_post_output_html($flat_post['topicid'], $flat_post['topic_data'], $flat_post['postid'], $flat_post['post_data']);
			}
		}
?>
</div>
</body>
</html>
<?php
		if (file_put_contents($filename, ob_get_clean()) === false) {
			$this->write_err('Failed to write to output file "'.$filename.'".', __FILE__, __METHOD__, __LINE__);
			return false;
		} else	return true;
	}

	// Output buffering should be on when this function is called, so we can just write to standard output.
	protected function write_post_output_html($topicid, $topic_data, $postid, $post_data) {
		echo '	<div style="border-bottom: solid gray 2px;">'."\n";
		echo '		<span>'.$post_data['ts'].'</span>'."\n";
		echo '		<a href="'.htmlspecialchars($this->get_post_url($topic_data['forumid'], $topicid, $postid, true)).'">'.$topic_data['topic'].'</a>'."\n";
		echo '	</div>'."\n";
		echo '	<div style="border-bottom: solid gray 2px;">'."\n";
		echo '		<span>'.$post_data['posttitle'].'</span>'."\n";
		echo '	</div>'."\n";
		echo '	<div>'.$post_data['content']."\n";
		echo '	</div>'."\n\n";
		if (isset($this->settings['download_attachments']) && $this->settings['download_attachments'] && $post_data['attachments']) {
			echo '	<br/><div class="attachments">'."\n";
			echo '	<span><em>Attachments:</em></span><br />'."\n";
			foreach ($post_data['attachments'] as $i => $attachment) {
				$size = isset($this->downld_file_urls_downloaded[$attachment['url']]) ? number_format(stat($this->output_dirname.'/'.$this->downld_file_urls_downloaded[$attachment['url']])['size']).' bytes' : 'Unknown size';
				$url  = isset($this->downld_file_urls_downloaded[$attachment['url']]) ? $this->downld_file_urls_downloaded[$attachment['url']] : $attachment['url'];
				echo '		<div class="attachment">'."\n";
				echo '			'.($attachment['is_image'] ? '<img src="'.htmlspecialchars($url).'" alt="'.htmlspecialchars($attachment['filename']).'" />' : '<a href="'.htmlspecialchars($url).'">'.htmlspecialchars($attachment['filename']).'</a> ('.$size.')').'<br />'."\n";
				echo '			<span><i>'.$attachment['comment'].'</i></span><br />'."\n";
				if ($attachment['is_image']) {
					echo '			<span>'.htmlspecialchars($attachment['filename']).' ('.$size.')</span>'."\n";
				}
				echo '		</div>'."\n";
			}
			echo '	</div>'."\n\n";
		}
		echo '	<br />'."\n\n";
	}

	protected function write_status($msg) {
		if ($this->web_initiated || !$this->quiet) {
			static::write_status_s($msg, $this->token, $this->org_start_time);
		}

	}

	static protected function write_status_s($msg, $token = false, $org_start_time = null) {
		if (is_null($org_start_time)) $org_start_time = time();
		$duration = time() - $org_start_time;
		$hrs = floor($duration / 3600);
		$remainder = $duration - $hrs * 3600;
		$mins = floor($remainder / 60);
		$secs = $remainder - $mins * 60;
		$contents = ($hrs ? $hrs.'h' : '').($mins ? $mins.'m' : '').$secs.'s '.$msg;
		if ($token) {
			$filename = make_status_filename($token);
			file_put_contents($filename, $contents);
		} else { // For commandline invocation without -q
			static $ferr = null;
			if (!$ferr) {
				$ferr = fopen('php://stderr', 'w');
			}
			static::write_err_s($ferr, $contents);
		}
	}

	public function skip_current_topic() {
		if (!is_null($this->forum_idx2)) {
			$id = $this->settings['forum_ids_arr'][$this->forum_idx2];
			$forum =& $this->forum_data[$id];
			$t_keys = array_keys($forum['topics']);
			if ($this->topic_idx < count($t_keys)) {
				unset($forum['topics'][$t_keys[$this->topic_idx]]);
			}
		}
	}

	abstract protected function get_topic_id_from_topic_url($url);
}

function cmp_posts_date($p1, $p2) {
	if ($p1['timestamp'] == $p2['timestamp']) return 0;
	return $p1['timestamp'] < $p2['timestamp'] ? -1 : 1;
}

function cmp_topics_topic($t1, $t2) {
	return strcmp($t1['topic'], $t2['topic']);
}

?>
