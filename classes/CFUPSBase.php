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

/* File       : classes/CFUPSBase.php.
 * Description: The base class for forum scraping. Cannot be instantiated
 *              due to abstract methods - only descendant classes for specific
 *              forums can be instantiated.
 */

require_once __DIR__.'/../common.php';
require_once __DIR__.'/../phpBB-days-and-months-intl.php';

abstract class FUPSBase {
	# The maximum time in seconds before the script chains a new instance of itself and then exits,
	# to avoid timeouts due to exceeding the PHP commandline max_execution_time ini setting.
	public    $FUPS_CHAIN_DURATION =  null;
	protected $have_written_to_admin_err_file = false;
	protected $required_settings = array('base_url', 'extract_user_id', 'php_timezone');
	protected $optional_settings = array('start_from_date', 'non_us_date_format', 'debug');
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
	protected $output_filename   =   false;
	protected $errs_filename     =   false;
	protected $cookie_filename   =   false;
	protected $ch                =    null;
	protected $last_url          =    null;
	protected $search_id         =    null;
	protected $post_search_counter =     0;
	protected $posts_not_found   = array();
	protected $posts_data        = array();
	protected $total_posts       =       0;
	protected $current_topic_id  =    null;
	protected $num_posts_retrieved =     0;
	protected $num_thread_infos_retrieved = 0;
	protected $search_page_num   =       0;
	protected $dbg               =   false;
	protected $quiet             =   false;
	protected $progress_levels   = array(
		0 => 'init_user_post_search',
		1 => 'user_post_search',
		2 => 'topic_post_sort',
		3 => 'posts_retrieval',
		4 => 'extract_per_thread_info',
		5 => 'handle_missing_posts',
		6 => 'write_output',
		7 => 'check_send_non_fatal_err_email',
	);
	protected $was_chained       =   false;

	public function __construct($web_initiated, $params, $do_not_init = false) {
		if (!$do_not_init) {
			$this->org_start_time = time();
			$this->start_time = $this->org_start_time;
			if ($this->supports_feature('login')) {
				$this->optional_settings = array_merge($this->optional_settings, array('login_user', 'login_password'));
			}
			$this->web_initiated = $web_initiated;
			if ($this->web_initiated) {
				if (!isset($params['token'])) {
					$this->exit_err('Fatal error: $web_initiated was true but $params did not contain a "token" key.', __FILE__, __METHOD__, __LINE__);
				}
				$this->token = $params['token'];
				$this->settings_filename = make_settings_filename($this->token);
				$this->output_filename   = make_output_filename  ($this->token);
				$this->errs_filename     = make_errs_filename    ($this->token);
			} else {
				if (!isset($params['settings_filename'])) {
					$this->exit_err('Fatal error: $web_initiated was false but $params did not contain a "settings_filename" key.', __FILE__, __METHOD__, __LINE__);
				}
				$this->settings_filename = $params['settings_filename'];
				if (!isset($params['output_filename'])) {
					$this->exit_err('Fatal error: $web_initiated was false but $params did not contain a "output_filename" key.', __FILE__, __METHOD__, __LINE__);
				}
				$this->output_filename = $params['output_filename'];
				$this->quiet           = $params['quiet'          ];
			}

			if (FUPS_CHAIN_DURATION == -1) {
				$max_execution_time = ini_get('max_execution_time');
				if (is_numeric($max_execution_time) && $max_execution_time > 0) {
					$this->FUPS_CHAIN_DURATION = $max_execution_time * 3/4;
				} else	$this->FUPS_CHAIN_DURATION = FUPS_FALLBACK_FUPS_CHAIN_DURATION;
			} else $this->FUPS_CHAIN_DURATION = FUPS_CHAIN_DURATION;

			$this->write_status('Reading settings.');
			$default_settings = $this->get_default_settings();
			$raw_settings = $this->read_settings_raw_s($this->settings_filename);
			foreach ($raw_settings as $setting => $value) {
				if (in_array($setting, $this->required_settings) || in_array($setting, $this->optional_settings)) {
					$this->settings[$setting] = $value;
				}
			}
			$missing = array_diff($this->required_settings, array_keys($this->settings));
			if ($missing) {
				$this->exit_err("The following settings were missing: ".implode(', ', $missing).'.', __FILE__, __METHOD__, __LINE__);
			}
			foreach ($default_settings as $setting => $default) {
				if (empty($this->settings[$setting])) $this->settings[$setting] = $default;
			}
			date_default_timezone_set($this->settings['php_timezone']); // This timezone only matters when converting the earliest time setting.
			if (!empty($this->settings['start_from_date'])) {
				$this->settings['earliest'] = $this->strtotime_intl($this->settings['start_from_date']);
				if ($this->settings['earliest'] === false) $this->write_err("Error: failed to convert 'start_from_date' ({$this->settings['start_from_date']}) into a UNIX timestamp.");
			}

			$this->dbg = in_array($this->settings['debug'], array('true', '1')) ? true : false;

			if ($this->dbg) {
				$this->write_err('SETTINGS:');
				$this->write_err(var_export($this->settings, true));
			}
			$this->write_status('Finished reading settings.');

			$this->validate_settings();
		}
	}

	public function __wakeup() {
		$this->start_time = time();
		date_default_timezone_set($this->settings['php_timezone']);
		$this->was_chained = true;
		$this->write_status('Woke up in chained process.');
	}

	protected function check_do_chain() {
		if (time() - $this->start_time > $this->FUPS_CHAIN_DURATION) {
			$serialize_filename = make_serialize_filename($this->web_initiated ? $this->token : $this->settings_filename);

			if ($this->dbg) $this->write_err('Set $serialize_filename to "'.$serialize_filename.'".');

			if (!file_put_contents($serialize_filename, serialize($this))) {
				$this->exit_err('file_put_contents returned false.', __FILE__, __METHOD__, __LINE__);
			}

			$args = array(
				'chained' => true,
			);
			if ($this->web_initiated) {
				$args['token'] = $this->token;
			} else {
				$args['settings_filename'] = $this->settings_filename;
				$args['output_filename'] = $this->output_filename;
				$args['quiet'] = $this->quiet;
			}

			curl_close($this->ch); // So we save the cookie file to disk for the chained process.

			$cmd = make_php_exec_cmd($args);
			$this->write_status('Chaining next process.');
			if ($this->dbg) $this->write_err('Chaining process: about to run command: '.$cmd);
			if (!try_run_bg_proc($cmd)) {
				$this->exit_err('Apologies, the server encountered a technical error: it was unable to initiate a chained background process to continue the task of scraping, sorting and finally presenting your posts. The command used was:'.PHP_EOL.PHP_EOL.$cmd.PHP_EOL.PHP_EOL.'Any output was:'.PHP_EOL.implode(PHP_EOL, $output).PHP_EOL.PHP_EOL.'You might like to try again.', __FILE__, __METHOD__, __LINE__);
			}
			if ($this->dbg) $this->write_err('Exiting parent chaining process.');
			exit;
		}
	}

	protected function check_do_login() {}

	protected function check_get_board_title($html) {
		if (empty($this->settings['board_title'])) {
			# Try to discover the board's title
			if (!$this->skins_preg_match('board_title', $html, $matches)) {
				if ($this->dbg) $this->write_err("Warning: couldn't find the site title. The URL of the searched page is ".$this->last_url, __FILE__, __METHOD__, __LINE__, $html);
			}
			$this->settings['board_title'] = $matches[1];
			if ($this->dbg) $this->write_err("Site title: {$this->settings['board_title']}");
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

	function do_send() {
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
			if ($err) break;
		}
		if ($err) {
			$this->write_err('Too many errors with request; abandoning page and quitting. Request URL is <'.$this->last_url.'>. Last error was: '.$err, __FILE__, __METHOD__, __LINE__);
		} else {
			$this->check_get_board_title($html);
		}

		return $html;
	}

	# Non-static variant of the static variant below
	protected function exit_err($msg, $file, $method, $line, $html = false, $send_mail = true) {
		$token = $this->web_initiated ? $this->token : false;
		$dbg   = $this->dbg;
		$this->write_err($msg, $file, $method, $line);
		$settings_str = $this->get_settings_str();

		static::exit_err_common_s($msg, $file, $method, $line, $this->have_written_to_admin_err_file, get_class($this), $html, $settings_str, $send_mail, $token, $dbg);
	}

	static public function exit_err_s($msg, $file, $method, $line, $html = false, $send_mail = true, $token = false, $dbg = false) {
		$ferr = fopen('php://stderr', 'a');
		static::write_err_s($ferr, $msg, $file, $method, $line);
		static::exit_err_common_s($msg, $file, $method, $line, false, null, $html, false, $send_mail, $token, $dbg);
	}

	static public function exit_err_common_s($msg, $file, $method, $line, $have_written_to_admin_err_file, $classname = null, $html = false, $settings_str = false, $send_mail = true, $token = false, $dbg = false) {
		$full_admin_msg = static::record_err_admin_s($msg, $file, $method, $line, $have_written_to_admin_err_file, $classname, $html, $settings_str, $token, $dbg);

		if ($send_mail) {
			static::send_err_mail_to_admin_s($full_admin_msg, $token, true);
		}

		if ($token) {
			static::write_status_s('A fatal error occurred. EXITING', $token);
		}

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
			$this->write_err('Error: couldn\'t find any search result matches on one of the search results pages.  The URL of the page is '.$this->last_url, __FILE__, __METHOD__, __LINE__, $html);
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

			$this->find_author_posts_via_search_page__ts_raw_hook($ts_raw);

			$ts = $this->strtotime_intl($ts_raw);
			if ($ts === false) {
				$err_msg = "Error: strtotime_intl failed for '$ts_raw'.";
				if (!isset($this->settings['non_us_date_format']) && strpos($ts_raw, '/') !== false) {
					$err_msg .= ' Hint: Perhaps you need to check the "Non-US date format" box on the previous page.';
				}
				$this->write_err($err_msg);
			} else	{
				if (!empty($this->settings['earliest']) && $ts < $this->settings['earliest']) {
					$found_earliest = true;
					if ($this->dbg) $this->write_err("Found post earlier than earliest allowed; not searching further: ".$ts_raw." < {$this->settings['start_from_date']}.");
					break;
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
			);
			if ($this->dbg) {
				$this->write_err("Added post: $posttitle ($topic; $ts; $forum; forumid: $forumid; topicid: $topicid; postid: $postid)");
			}
			
			$num_posts_found++;
		}

		$do_inc_progress_level = $found_earliest;

		$this->find_author_posts_via_search_page__end_hook($do_inc_progress_level, $html, $found_earliest, $matches);

		if ($do_inc_progress_level) $this->progress_level++;
		
		return $num_posts_found;
	}

	protected function find_author_posts_via_search_page__end_hook(&$do_inc_progress_level, $html, $found_earliest, $matches) {
		$this->post_search_counter += count($matches);
	}

	protected function find_author_posts_via_search_page__match_hook($match, &$forum, &$forumid, &$topic, &$topicid, &$postid, &$posttitle, &$ts_raw, &$ts) {}

	# Override this function to e.g. remove extraneous text from the matched timestamp string
	# prior to attempting to parse it into a UNIX timestamp.
	protected function find_author_posts_via_search_page__ts_raw_hook(&$ts_raw) {}

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

	protected function get_default_settings() {
		return array(
			'delay' => 5,
			'debug' => false
		);
	}

	protected function get_extra_head_lines() {
		return '';
	}

	static protected function get_formatted_err($method, $line, $file, $msg) {
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

	static function get_forum_software_homepage() {
		return '[YOU NEED TO CUSTOMISE THE static get_forum_software_homepage() function OF YOUR CLASS DESCENDING FROM FUPSBase!]';
	}

	static function get_msg_how_to_detect_forum() {
		return '[YOU NEED TO CUSTOMISE THE static get_msg_how_to_detect_forum() function OF YOUR CLASS DESCENDING FROM FUPSBase!]';
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

		$err = false;
		$count = 0;
		if (!$this->skins_preg_match_all('post_contents', $html, $matches)) {
			$err = true;
			$this->write_err('Error: Did not find any post IDs or contents on the thread page for post ID '.$postid.'. The URL of the page is "'.$this->last_url.'"', __FILE__, __METHOD__, __LINE__, $html);
		} else {
			list($found, $count) = $this->get_post_contents_from_matches($matches, $postid, $topicid);
			if ($found) {
				if ($this->dbg) $this->write_err('Retrieved post contents of post ID "'.$postid.'"');
				$ret = true;
				$count--;
			} else if ($this->dbg) $this->write_err('FAILED to retrieve post contents of post ID "'.$postid.'". The URL of the page is "'.$this->last_url.'"', __FILE__, __METHOD__, __LINE__, $html);

			if ($count > 0 && $this->dbg) $this->write_err('Retrieved '.$count.' other posts.');
		}

		$this->get_post_contents__end_hook($forumid, $topicid, $postid, $html, $found, $err, $count, $ret);

		if (!$found) $this->posts_not_found[$postid] = true;
		$this->num_posts_retrieved += $count + ($found ? 1 : 0);

		return $ret;
	}

	protected function get_post_contents__end_hook($forumid, $topicid, $postid, $html, &$found, $err, $count, &$ret) {}

	protected function get_post_contents_from_matches($matches, $postid, $topicid) {
		$found = false;
		$count = 0;
		$posts =& $this->posts_data[$topicid]['posts'];
		foreach ($matches as $match) {
			if (isset($posts[$match[1]])) {
				$posts[$match[1]]['content'] = $match[2];
				if ($postid == $match[1]) $found = true;
				$count++;
			}
		}

		return array($found, $count);
	}

	abstract protected function get_post_url($forumid, $topicid, $postid, $with_hash = false);

	static function get_qanda() {
		return array(
			'q_lang' => array(
				'q' => 'Does the script work with forums using a language other than English?',
				'a' => 'Yes, or at least, it\'s intended to: if you experience problems, please <a href="'.FUPS_CONTACT_URL.'">contact me</a>.',
			),
			'q_how_long' => array(
				'q' => 'How long will the process take?',
				'a' => 'It depends on how many posts are to be retrieved, and how many pages they are spread across. You can expect to wait roughly one hour to extract and output 1,000 posts.',
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
			'base_url' => array(
				'label'       => 'Base forum URL'                        ,
				'default'     => ''                                      ,
				'description' => 'Set this to the base URL of the forum.',
				'style'       => 'min-width: 300px;'                     ,
			),
			'extract_user_id' => array(
				'label'       => 'Extract User ID'                       ,
				'default'     => ''                                      ,
				'description' => 'Set this to the user ID of the user whose posts are to be extracted.',
			)
		);

		if ($this->supports_feature('login')) {
			$default_settings = array_merge($default_settings, array(
				'login_user'  => array(
					'label' => 'Login User Username',
					'default' => '',
					'description' => 'Set this to the username of the user whom you wish to log in as, or leave it blank if you do not wish FUPS to log in.',
				),
				'login_password' => array(
					'label' => 'Login User Password',
					'default' => '',
					'description' => 'Set this to the password associated with the Login User Username (or leave it blank if you do not require login).',
					'type' => 'password',
				),
			));
		}

		$default_settings = array_merge($default_settings, array(
			'start_from_date'  => array(
				'label' => 'Start From Date+Time',
				'default' => '',
				'description' => 'Set this to the datetime of the earliest post to be extracted i.e. only posts of this datetime and later will be extracted. If you do not set this (i.e. if you leave it blank) then all posts will be extracted. This value is parsed with PHP\'s <a href="http://www.php.net/strtotime">strtotime()</a> function, so check that link for details on what it should look like. An example of something that will work is: 2013-04-30 15:30.',
			),
			'php_timezone' => array(
				'label' => 'PHP Timezone',
				'default' => 'Australia/Hobart',
				'description' => 'Set this to the time zone in which the user\'s posts were made. Valid time zone values are listed starting <a href="http://php.net/manual/en/timezones.php">here</a>. This is a required setting, because PHP requires the time zone to be set when using date/time functions, however it only applies when "Start From Date+Time" is set above, in which case the value that you supply for "Start From Date+Time" will be assumed to be in the time zone you supply here, as will the date+times for posts retrieved from the forum. It is safe to leave this value set to the default if you are not supplying a value for the "Start From Date+Time" setting.',
			),
			'non_us_date_format' => array(
				'label' => 'Non-US date format',
				'default' => '',
				'description' => 'Check this box if the forum from which you\'re scraping outputs dates in the non-US ordering dd/mm rather than the US ordering mm/dd. Applies only if day and month are specified by digits and separated by forward slashes.',
				'type' => 'checkbox',
			),
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
			$settings_str .= "\t$k=$v".PHP_EOL;
		}

		return $settings_str;
	}

	abstract protected function get_topic_url($forumid, $topicid);

	abstract protected function get_user_page_url();

	static public function get_valid_forum_types() {
		static $ignored_files = array('.', '..', 'CFUPSBase.php');
		$ret = array();
		$class_files = scandir(__DIR__);
		if ($class_files) foreach ($class_files as $class_file) {
			if (!in_array($class_file, $ignored_files)) {
				$class = substr($class_file, 1, -4); # Omit initial "C" and trailing ".php"
				$ret[strtolower($class)] = $class;
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
	protected function hook_after__write_output           () {} // Run after progress level 6
	protected function hook_after__check_send_non_fatal_err_email() {} // Run after progress level 7

	protected function init_post_search_counter() {
		$this->post_search_counter = 0;
	}

	protected function init_search_user_posts() {}

	static public function read_forum_type_from_settings_file_s($settings_filename) {
		$settings_raw = static::read_settings_raw_s($settings_filename);
		return isset($settings_raw['forum_type']) ? $settings_raw['forum_type'] : false;
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

	static protected function record_err_admin_s($msg, $file, $method, $line, &$have_written_to_admin_err_file, $classname = null, $html = false, $settings_str = false, $token = false, $dbg = false) {
		$ferr = fopen('php://stderr', 'a');
		$html_msg = $html !== false ? 'The relevant page\'s HTML is:'.PHP_EOL.PHP_EOL.$html.PHP_EOL.PHP_EOL.PHP_EOL.PHP_EOL.PHP_EOL.PHP_EOL : '';
		$settings_msg = (!$have_written_to_admin_err_file && $settings_str) ? static::get_settings_msg_s($settings_str) : '';
		$classname_msg = (!$have_written_to_admin_err_file && $classname) ? static::get_classname_msg_s($classname).PHP_EOL.PHP_EOL : '';
		$full_admin_msg = $classname_msg.$settings_msg.PHP_EOL.static::get_formatted_err($method, $line, $file, $msg).PHP_EOL.PHP_EOL.$html_msg;

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

	public function run() {
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
		if ($this->supports_feature('login')) {
			if ($this->was_chained) {
				if ($this->dbg) $this->write_err('Not bothering to check whether to log in again, because we\'ve just chained.');
			} else	$this->check_do_login();
		}

		# Find all of the user's posts through the search feature
		if ($this->progress_level == 0) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			$this->check_get_username();
			$this->search_page_num = 1;
			$this->init_post_search_counter();
			$this->init_search_user_posts();
			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->progress_level++;
			$this->$hook_method(); // hook_after__init_user_post_search();
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

		# Sort topics and posts
		if ($this->progress_level == 2) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			$this->write_status('Sorting posts and topics prior to scraping posts\' content.');
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
			$this->write_status('Finished sorting posts and topics. Now scraping contents of '.$this->total_posts.' posts.');
			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->progress_level++;
			$this->$hook_method(); // hook_after__topic_post_sort();
		}

		# Retrieve the contents of all of the user's posts
		if ($this->progress_level == 3) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
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
							if ($p['content'] == null && !isset($this->posts_not_found[$postid])) {
								$this->get_post_contents($t['forumid'], $topicid, $postid);
								$this->write_status('Retrieved '.$this->num_posts_retrieved.' of '.$this->total_posts.' posts.');
								$done = false;
							}
							$this->check_do_chain();
						}
					}
				}
			}

			$this->current_topic_id = null; # Reset this for progress level 4

			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->progress_level++;
			$this->$hook_method(); // hook_after__posts_retrieval();
		}

		# Extract per-thread information: thread author and forum
		if ($this->progress_level == 4) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			# If the current topic ID is already set, then we are continuing after having chained.
			$go = is_null($this->current_topic_id);
			$total_threads = count($this->posts_data);
			foreach ($this->posts_data as $topicid => $dummy) {
				if (!$go) {
					if ($this->current_topic_id == $topicid) $go = true;
				} else {
					$topic =& $this->posts_data[$topicid];
					$url = $this->get_topic_url($topic['forumid'], $topicid);
					$this->set_url($url);
					$html = $this->do_send();
					if (!$this->skins_preg_match('thread_author', $html, $matches)) {
						$this->write_err("Error: couldn't find a match for the author of the thread with topic id '$topicid'.  The URL of the page is <".$url.'>.', __FILE__, __METHOD__, __LINE__, $html);
						$topic['startedby'] = '???';
					} else {
						$topic['startedby'] = $matches[1];
						if ($this->dbg) $this->write_err("Added author of '{$topic['startedby']}' for topic id '$topicid'.");
						$this->num_thread_infos_retrieved++;
						$this->write_status('Retrieved author and topic name for '.$this->num_thread_infos_retrieved.' of '.$total_threads.' threads.');
					}
					$this->current_topic_id = $topicid;
					$this->check_do_chain();
				}
			}
			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->progress_level++;
			$this->$hook_method(); // hook_after__extract_per_thread_info();
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
			$this->progress_level++;
			$this->$hook_method(); // hook_after__handle_missing_posts();
		}

		# Write output
		if ($this->progress_level == 6) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);
			$this->write_status('Writing output.');

			# Write the HTML output
			$this->write_output();

			# Signal that we are done
			$this->write_status('DONE');

			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->progress_level++;
			$this->$hook_method(); // hook_after__write_output();
		}

		# Potentially send an admin email re non-fatal errors.
		if ($this->progress_level == 7) {
			if ($this->dbg) $this->write_err('Entered progress level '.$this->progress_level);

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
						if (!$errs_admin) {
							$settings_msg = static::get_settings_msg_s(static::get_settings_str());
							$classname_msg = static::get_classname_msg_s(get_class($this));
							$err_msg .= $settings_msg.PHP_EOL.PHP_EOL.$classname_msg.PHP_EOL;
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
					static::send_err_mail_to_admin_s($err_msg, $this->token, false);
				}
			}

			$hook_method = 'hook_after__'.$this->progress_levels[$this->progress_level];
			$this->progress_level++;
			$this->$hook_method(); // hook_after__check_send_non_fatal_err_email();
		}
	}

	static protected function send_err_mail_to_admin_s($full_admin_msg, $token = false, $is_fatal = true) {
		global $argv;

		$body  = ($is_fatal ? 'F' : 'Non-f').'atal error'.($is_fatal ? '' : '(s)').' occurred in the FUPS process with commandline arguments:'.PHP_EOL.var_export($argv, true).PHP_EOL.PHP_EOL;
		$body .= $full_admin_msg;
		$subject = ($is_fatal ? 'F' : 'Non-f').'atal error'.($is_fatal ? '' : '(s)').' in FUPS process';
		if ($token) $subject .= ' '.$token;
		$headers = 'From: '.FUPS_EMAIL_SENDER."\r\n".
				"MIME-Version: 1.0\r\n" .
				"Content-type: text/plain; charset=UTF-8\r\n";
		mail(FUPS_EMAIL_RECIPIENT, $subject, $body, $headers);
	}

	protected function set_url($url) {
		if (!curl_setopt($this->ch, CURLOPT_URL, $url)) {
			$this->exit_err('Failed to set cURL URL: <'.$url.'>.', __FILE__, __METHOD__, __LINE__);
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

	protected function strtotime_intl($time_str) {
		$time_str_org = $time_str;
		$non_us_date_format = isset($this->settings['non_us_date_format']);
		if ($non_us_date_format) {
			// Switch month and day in that part of the date formatted as either m/d/y or m/d/y,
			// where m and d are either one or two digits, and y is either two or four digits.
			// The phrase m/d/y can occur anywhere in the string so long as at either end it is
			// either separated from the rest of the string by a space or occurs at the
			// beginning/end of the string (as such, it may comprise the entire string).
			$time_str = preg_replace('#(^|\s)(\d{1,2})/(\d{1,2})(/\d\d|/\d\d\d\d|)(\s|$)#', '$1$3/$2$4$5', $time_str);
		}
		if ($this->dbg) $this->write_err('Running strtotime() on "'.$time_str.'".'.($non_us_date_format ? 'This was derived from "'.$time_str_org.'" due to the "Non-US date format" setting being in effect.' : ''));
		$ret = strtotime($time_str);
		if ($ret === false) {
			// This is necessary for translated phpBB forums
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

	public function supports_feature($feature) {
		static $default_features = array(
			'login' => false
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
			if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE)) {
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
			$msg = static::get_formatted_err($method, $line, $file, $msg);
		}
		if ($ferr) {
			fwrite($ferr, $msg.PHP_EOL);
		} else	echo $msg;
	}

	protected function write_output() {
		$heading = 'Postings of '.htmlspecialchars($this->settings['extract_user']).' to <a href="'.htmlspecialchars($this->settings['base_url']).'">'.(isset($this->settings['board_title']) ? htmlspecialchars($this->settings['board_title']) : '[unknown]').'</a>';
		if (!empty($this->settings['start_from_date'])) $heading .= ' starting from '.htmlspecialchars($this->settings['start_from_date']);

		if (!ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE|PHP_OUTPUT_HANDLER_FLUSHABLE|PHP_OUTPUT_HANDLER_REMOVABLE)) {
			$this->exit_err('Fatal error: unable to start output buffering.', __FILE__, __METHOD__, __LINE__);
		}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en" xml:lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
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
		foreach ($this->posts_data as $topicid => $t) {
			foreach ($t['posts'] as $postid => $p) {
				echo '	<div style="border-bottom: solid gray 2px;">'."\n";
				echo '		<span>'.$p['ts'].'</span>'."\n";
				echo '		<a href="'.htmlspecialchars($this->get_post_url($t['forumid'], $topicid, $postid, true)).'">'.$t['topic'].'</a>'."\n";
				echo '	</div>'."\n";
				echo '	<div style="border-bottom: solid gray 2px;">'."\n";
				echo '		<span>'.$p['posttitle'].'<span>'."\n";
				echo '	</div>'."\n";
				echo '	<div>'.$p['content']."\n";
				echo '	</div>'."\n\n";

				echo '	<br />'."\n\n";
			}
		}
?>
</div>
</body>
</html>
<?php
		file_put_contents($this->output_filename, ob_get_clean());
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
}

function cmp_posts_date($p1, $p2) {
	if ($p1['timestamp'] == $p2['timestamp']) return 0;
	return $p1['timestamp'] < $p2['timestamp'] ? -1 : 1;
}

function cmp_topics_topic($t1, $t2) {
	return strcmp($t1['topic'], $t2['topic']);
}

?>
