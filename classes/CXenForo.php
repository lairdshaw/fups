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

/* File       : classes/CXenForo.php.
 * Description: The XenForo forum scraper class, descending from FUPSBase.
 */

class XenForoFUPS extends FUPSBase {
	protected $required_settings = array('base_url', 'extract_user_id', 'php_timezone');
	protected $optional_settings = array('start_from_date', 'delay', 'debug');
	protected $user_id_num       =      '';
	protected $topic_ids         = array();

	protected $regexps = array(
		'cwt_default' => array(
			// a regexp to extract the board's title from any forum page
			'board_title'              => '#<div class="boardTitle"><strong>([^<]*)</strong></div>#',
			// a regexp to extract the user's name from the user's profile page:
			//   </members/[user_id]/>
			'user_name'                => '#<h1 itemprop="name" class="username"><span class="[^"]*">([^<]*)</span></h1>#',
			// a regexp to extract the thread's author from the thread view page:
			//   </threads/[topicid]/>
			'thread_author'            => '#<p id="pageDescription" class="muted ">[^<]*<a href="forums/[^/]*/">[^<]*</a>[^<]*<a href="members/[^/]*/" class="username"[^>]*>([^<]*)</a>#Us',
			// a regexp to detect when a search results page returns no results i.e. on:
			//   </search/[searchid]/?page=[pagenum]>
			'search_results_not_found' => '#<div class="messageBody">[^<]*</div>#',
			// a regexp to be matched on the user's posts search page
			//   </search/[searchid]/?page=[pagenum]> using
			// preg_match_all with flags set to PREG_SET_ORDER so that each entry of
			// $matches ends up with the following matches in the order specified in
			// search_results_page_data_order.
			// N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
			'search_results_page_data' => '#<div class="listBlock main">\\s*<div class="titleText">\\s*<span class="contentType">[^<]*</span>\\s*<h3 class="title"><a href="([^/]*)/([^/]+)/">([^<]*)</a></h3>\\s*</div>\\s*<blockquote class="snippet">\\s*<a href="[^/]*/[^/]+/">[^<]*</a>\\s*</blockquote>\\s*<div class="meta">\\s*[^<]*<a href="members/[^/]*/"\\s*class="username"[^>]*>[^<]*</a>,\\s*<span class="DateTime" title="([^"]+)">[^<]*</span>[^<]*<a href="forums/([^/]*)/">([^<]*)</a>#Us',
			// an array specifying the order in which the following matches occur
			// in the matches returned by the previous array.
			// = array(
			//	'topic'   => the match index of the topic of post,
			//	'ts'      => the match index of the timestamp of post,
			//	'forum'   => the match index of the title of forum,
			//	'forumid' => the match index of the forum id,
			//	'postid'  => the match index of the post id,
			//	'postsorthreads' => the match index of the text which is either "posts" or "threads"
			// )
			'search_results_page_data_order' => array('topic' => 3, 'ts' => 4, 'forum' => 6, 'forumid' => 5, 'postid' => 2, 'postsorthreads' => 1),
			// a regexp to match post id (first match) and post contents (second match)
			// on a thread page; it is called with match_all so it will return all
			// post ids and contents on the page
			'post_contents'            => '#<li id="post-(\\d+)".*<article>\\s*<blockquote class="messageText [^"]*">(.*)\\s*?</blockquote>\\s*</article>#Us',
			// a regexp to match the thread id in a thread page
			'thread_id'                => '#<a href="threads/([^/]*)/">#',
			'older_content'            => '#<div class="secondaryContent olderMessages">\\s*<a href="search/member\\?user_id=\\d*&amp;before=(\\d+)">#'
		),
		'cwt_default2' => array(
			'user_name'                => '#<h1 itemprop="name" class="username">([^<]*)</h1>#', // Sometimes the inner span is missing.
			'search_results_page_data' => '#<div class="listBlock main">\\s*<div class="titleText">\\s*<span class="contentType">[^<]*</span>\\s*<h3 class="title"><a href="([^/]*)/([^/]+)/">([^<]*)</a></h3>\\s*</div>\\s*<blockquote class="snippet">\\s*<a href="[^/]*/[^/]+/">[^<]*</a>\\s*</blockquote>\\s*<div class="meta">\\s*[^<]*<a href="members/[^/]*/"\\s*class="username"[^>]*>[^<]*</a>,\\s*<(span|abbr) class="DateTime"[^>]*>([^<]*)</\\4>[^<]*<a href="forums/([^/]*)/">([^<]*)</a>#Us', // Sometimes the DateTime <span> is actually an <abbr>.
			'search_results_page_data_order' => array('topic' => 3, 'ts' => 5, 'forum' => 7, 'forumid' => 6, 'postid' => 2, 'postsorthreads' => 1),
		),
	);

	protected function find_author_posts_via_search_page__match_hook($match, &$forum, &$forumid, &$topic, &$topicid, &$postid, &$posttitle, &$ts_raw, &$ts) {
		# Messy workaround: posts which start a thread are displayed as a "thread" result in XenForo search results,
		# so we need to convert the threadid into a postid.
		if (isset($match['match_indexes']['postsorthreads']) && $match[$match['match_indexes']['postsorthreads']] == 'threads') {
			$url = $this->settings['base_url'].'/threads/'.$postid; # really a threadid
			$this->set_url($url);
			$html = $this->do_send();
			if (!$this->skins_preg_match('post_contents', $html, $matches)) {
				$this->write_err("Error: the regex to detect the first post ID on the thread page at <$url> failed.", __FILE__, __METHOD__, __LINE__, $html);
				$postid = null;
			} else {
				$postid = $matches[1];
			}
		}

		# Another messy workaround: the topic (thread) ID is not present anywhere in the XenForo search page HTML,
		# so we generate fake incrementing thread IDs, associated with the thread text, and then resolve
		# the actual IDs later (in get_post_contents__end_hook() and hook_after__posts_retrieval() below).
		if (!isset($this->topic_ids[$topic])) {
			$ids = array_values($this->topic_ids);
			$lastid = array_pop($ids);
			$lastid++;
			$this->topic_ids[$topic] = $lastid;
		}
		$topicid = $this->topic_ids[$topic];
	}

	protected function find_author_posts_via_search_page__ts_raw_hook(&$ts_raw) {
		if ($this->dbg) $this->write_err('Deleting any "at " in time string "'.$ts_raw.'".');
		$ts_new = preg_replace('/\\bat /', '', $ts_raw);
		if (!is_null($ts_new)) $ts_raw = $ts_new;
	}

	protected function find_author_posts_via_search_page__end_hook(&$do_inc_progress_level, $html, $found_earliest, $matches) {
		if ($this->skins_preg_match('older_content', $html, $matches)) {
			$this->write_status('Attempting to determine next search ID.');
			$this->search_id = $this->get_search_id($matches[1]);
			if (is_null($this->search_id)) {
				$do_inc_progress_level = true;
			} else	$this->post_search_counter = 1;
		} else	$this->post_search_counter++;
	}

	static function get_forum_software_homepage() {
		return 'http://xenforo.com/';
	}

	static function get_msg_how_to_detect_forum() {
		return 'Typically, XenForo forums can be identified by the presence of the text "Forum software by XenForo" in the footer of their forum pages. It is possible, however, that these footer texts have been removed by the administrator of the forum. In this case, the only way to know for sure is to contact your forum administrator.';
	}

	# First part of the postponed resolution of thread (topic) IDs -
	# see also find_author_posts_via_search_page__match_hook() above and hook_after__posts_retrieval() below.
	protected function get_post_contents__end_hook($forumid, $topicid, $postid, $html, &$found, $err, $count, &$ret) {
		if (!$err && $found) {
			if (!$this->skins_preg_match('thread_id', $html, $matches)) {
				$this->write_err('Error: could not match the thread_id on the page with url <'.$this->last_url.'>', __FILE__, __METHOD__, __LINE__, $html);
			} else {
				$this->topic_ids[$this->posts_data[$topicid]['topic']] = $matches[1];
			}
		}
	}

	protected function get_post_url($forumid, $topicid, $postid, $with_hash = false) {
		return $this->settings['base_url']."/posts/$postid/";
	}

	static function get_qanda() {
		$qanda = parent::get_qanda();
		$qanda_new = array(
			'q_how_know_xenforo' => array(
				'q' => 'How can I know if a forum is a XenForo forum?',
				'a' => self::get_msg_how_to_detect_forum(),
			)
		);

		foreach ($qanda as $id => $qa) {
			$qanda_new[$id] = $qa;

			if ($id == 'q_lang') {
				$qanda_new['q_images_supported'] = array(
					'q' => 'Are images supported?',
					'a' => 'Yes, images are supported so long as you are online at the time of viewing the output - they are not downloaded, the link is merely retained.',
				);
				$qanda_new['q_which_skins_supported'] = array(
					'q' => 'Which skins are supported?',
					'a' => 'Whichever skin is default for the <a href="http://civilwartalk.com">CivilWarTalk</a> forum - I don\'t even know which skin that is, having developed the XenForo scraping functionality as a paid job for a friend who wanted to extract posts from that forum, and having not even registered for an account there. Potentially it\'s even a customised skin, and FUPS won\'t work for any other XenForo forum. If you need support for another XenForo skin, feel free to <a href="'.FUPS_CONTACT_URL.'">contact me</a>.',
				);
			}
		}

		return $qanda_new;
	}

	protected function get_search_id($before = false) {
		$search_id = null;
		$url = $this->settings['base_url'].'/search/member?user_id='.$this->user_id_num;
		if ($before !== false) {
			$url .= '&before='.$before;
		}
		$this->set_url($url);
		$opts = array(
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HEADER         => true ,
		);
		if (!curl_setopt_array($this->ch, $opts)) {
			$this->exit_err('Failed to set the following cURL options:'."\n".var_export($opts, true), __FILE__, __METHOD__, __LINE__);
		}

		$response = curl_exec($this->ch);
		if ($response === false) {
			$this->write_err('curl_exec returned false.', __FILE__, __METHOD__, __LINE__);
		} else {
			$header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
			$headers = substr($response, 0, $header_size);
			$location = false;
			if (preg_match('/^Location: (.*)$/im', $headers, $matches)) {
				$location = trim($matches[1]);
				if ($location) {
					$i = strlen($location) - 1 /* account for trailing / */;
					while (--$i >= 0 && $location[$i] >= '0' && $location[$i] <= '9') {
						$search_id = $location[$i].$search_id;
					}
				}
			} else if ($this->dbg) {
				$this->write_err('Failed to detect a "Location:" header.', __FILE__, __METHOD__, __LINE__);
			}
		}
		$opts = array(
			CURLOPT_FOLLOWLOCATION => true ,
			CURLOPT_HEADER         => false,
		);
		if (!curl_setopt_array($this->ch, $opts)) {
			$this->exit_err('Failed to set the following cURL options:'."\n".var_export($opts, true), __FILE__, __METHOD__, __LINE__);
		}

		return $search_id;
	}

	protected function get_search_url() {
		return $this->settings['base_url'].'/search/'.$this->search_id.'/?page='.$this->post_search_counter;
	}

	public function get_settings_array() {
		$settings_arr = parent::get_settings_array();

		$settings_arr['base_url']['default'] = 'http://civilwartalk.com';
		$settings_arr['base_url']['description'] .= ' This is the URL that appears in your browser\'s address bar when you access the forum, only with everything onwards from (and including) the path of whichever script is being accessed (e.g. /threads or /forums) stripped off. The default URL provided is for the particular XenForo board known as "CivilWarTalk".';
		$settings_arr['extract_user_id']['description'] .= ' You can find a user\'s ID by hovering your cursor over a hyperlink to their name and taking note of everything that appears between "/members/" and the next "/" (i.e. this will be something like "my-member-name.12345") in the browser\'s status bar.';

		return $settings_arr;
	}

	protected function get_topic_url($forumid, $topicid) {
		return $this->settings['base_url'].'/threads/'.$topicid.'/';
	}

	protected function get_user_page_url() {
		return $this->settings['base_url'].'/members/'.$this->settings['extract_user_id'].'/';
	}

	# Second and final part of the postponed resolution of thread (topic) IDs -
	# see also find_author_posts_via_search_page__match_hook() and get_post_contents__end_hook() above.
	protected function hook_after__posts_retrieval() {
		$posts_data2 = array();
		foreach ($this->posts_data as $topicid => $t) {
			$posts_data2[$this->topic_ids[$t['topic']]] = $t;
		}
		$this->posts_data = $posts_data2;
	}

	protected function init_post_search_counter() {
		$this->post_search_counter = 1;
	}

	protected function init_search_user_posts() {
		$this->write_status('Attempting to determine search ID.');
		$i = strlen($this->settings['extract_user_id']);
		while (--$i >= 0 && $this->settings['extract_user_id'][$i] >= '0' && $this->settings['extract_user_id'][$i] <= '9') {
			$this->user_id_num = $this->settings['extract_user_id'][$i].$this->user_id_num;
		}
		$this->search_id = $this->get_search_id();
	}

	public function supports_feature($feature) {
		static $features = array(
			'login' => false
		);

		return isset($features[$feature]) ? $features[$feature] : parent::supports_feature($feature);
	}
}

?>
