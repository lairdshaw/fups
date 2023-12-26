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

/* File       : classes/CXenForo.php.
 * Description: The XenForo forum scraper class, descending from FUPSBase.
 */

class XenForoFUPS extends FUPSBase {
	protected $user_id_num =      '';
	protected $topic_ids   = array();

	protected $regexps = array(
		'cwt_default' => array(
			// a regexp to extract the board's title from any forum page
			'board_title'              => '#<div class="boardTitle"><strong>([^<]*)</strong></div>#',
			// a regexp to extract the user's name from the user's profile page:
			//   </members/[user_id]/>
			'user_name'                => '#<h1 itemprop="name" class="username"><span class="[^"]*">([^<]*)</span>#',
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
			'search_results_page_data' => '#<div class="listBlock main">\\s*<div class="titleText">\\s*<span class="contentType">[^<]*</span>\\s*(?:<span[^>]*>[^<]*</span>)?\\s*<h3 class="title"><a href="([^/]*)/([^/]+)/">(<span[^>]*>[^<]*</span> )?([^<]*)</a></h3>\\s*</div>\\s*<blockquote class="snippet">\\s*<a href="[^/]*/[^/]+/">[^<]*</a>\\s*</blockquote>\\s*<div class="meta">\\s*[^<]*<a href="members/[^/]*/"\\s*class="username"[^>]*>[^<]*</a>,\\s*<span class="DateTime" title="([^"]+)">[^<]*</span>[^<]*<a href="forums/([^/]*)/">([^<]*)</a>#Us',
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
			'search_results_page_data_order' => array('topic' => 4, 'ts' => 5, 'forum' => 7, 'forumid' => 6, 'postid' => 2, 'postsorthreads' => 1),
			// a regexp to match post id (first match) and post contents (second match)
			// on a thread page; it is called with match_all so it will return all
			// post ids and contents on the page
			'post_contents'            => '#<li id="post-(\\d+)".*<article>\\s*<blockquote class="messageText [^"]*">(.*)\\s*?</blockquote>\\s*</article>#Us',
			// a regexp to match the thread id in a thread page
			'thread_id'                => '[THIS REGEX IS SET WITHIN __construct()]',
			'older_content'            => '#<div class="secondaryContent olderMessages">\\s*<a href="search/member\\?user_id=\\d*&amp;before=(\\d+)">#',
			'last_forum_page'           => '((^(?!.*<a\\s*href="[^"]*"\\s*class="currentPage\\s*")|<div\\s*class="PageNav".*data-last="(\\d+)".*<a\\s*href="[^"]+"\\s*class="currentPage\\s*">\\2</a>))Us',
			'forum_page_topicids'       => '(<h3\\s+class="title">.*<a href="threads/([^/]+)/")Us',
			'post_contents_ext'         => '(<li\\s+id="post-(\\d+)"[^>]*>.*<h3\\s+class="userText">\\s*<a[^>]*>([^<]+)</a>.*<div\\s+class="messageContent"[^>]*>\\s*<article>\\s*<blockquote[^>]*>(.*)<div\\s*class="messageTextEndMarker">&nbsp;</div>\\s*</blockquote>\\s*</article>.*class="DateTime"[^>]*title="([^"]+)">())Us',
			'post_contents_ext_order'   =>
				array(
					'author'  => 2,
					'title'   => 5,
					'ts'      => 4,
					'postid'  => 1,
					'contents'=> 3,
				),
			'forum_title'               => '(<meta\\s+property="og:title"\\s+content="([^"]+)"\\s+/>)',
			'last_topic_page'           => '((^(?!.*<a\\s*href="[^"]*"\\s*class="currentPage\\s*")|<div\\s*class="PageNav".*data-last="(\\d+)".*<a\\s*href="[^"]+"\\s*class="currentPage\\s*">\\2</a>))Us',
			'topic'                     => '(<meta\\s+property="og:title"\\s+content="([^"]+)")',
		),
		'cwt_default2' => array(
			'user_name'                => '#<h1 itemprop="name" class="username">([^<]*)</h1>#', // Sometimes the inner span is missing.
			// Sometimes the DateTime <span> is actually an <abbr>.
			// N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
			'search_results_page_data' => '#<div class="listBlock main">\\s*<div class="titleText">\\s*<span class="contentType">[^<]*</span>\\s*(?:<span[^>]*>[^<]*</span>)?\\s*<h3 class="title"><a href="([^/]*)/([^/]+)/">(<span[^>]*>[^<]*</span> )?([^<]*)</a></h3>\\s*</div>\\s*<blockquote class="snippet">\\s*<a href="[^/]*/[^/]+/">[^<]*</a>\\s*</blockquote>\\s*<div class="meta">\\s*[^<]*<a href="members/[^/]*/"\\s*class="username"[^>]*>[^<]*</a>,\\s*<abbr class="DateTime"[^>]*>([^<]*)</abbr>[^<]*<a href="forums/([^/]*)/">([^<]*)</a>#Us',
			'search_results_page_data_order' => array('topic' => 4, 'ts' => 5, 'forum' => 7, 'forumid' => 6, 'postid' => 2, 'postsorthreads' => 1),
			'thread_author'            => '#data-author="([^"]*)"#',
			'post_contents_ext'         => '(<li\\s+id="post-(\\d+)"[^>]*>.*<h3\\s+class="userText">\\s*<a[^>]*>([^<]+)</a>.*<div\\s+class="messageContent"[^>]*>\\s*<article>\\s*<blockquote[^>]*>(.*)<div\\s*class="messageTextEndMarker">&nbsp;</div>\\s*</blockquote>\\s*</article>.*class="DateTime"[^>]*>([^"]+)<())Us',
			'post_contents_ext_order'   =>
				array(
					'author'  => 2,
					'title'   => 5,
					'ts'      => 4,
					'postid'  => 1,
					'contents'=> 3,
				),
		),
	);

	public function __construct($web_initiated, $params, $do_not_init = false) {
		parent::__construct($web_initiated, $params, $do_not_init);
		if (!$do_not_init) {
			$this->regexps['cwt_default']['thread_id'] = '#<a href="'.$this->settings['thread_url_prefix'].'([^/]*)/[^"]*" title="[^"]*" class="datePermalink"#';
		}
	}

	protected function find_author_posts_via_search_page__match_hook($match, &$forum, &$forumid, &$topic, &$topicid, &$postid, &$posttitle, &$ts_raw, &$ts) {
		# Posts which start a thread are displayed as a "thread" result in XenForo search results,
		# but our regex detects the topic (thread) ID as a post ID, so we need to convert it.
		if (isset($match['match_indexes']['postsorthreads']) && $match[$match['match_indexes']['postsorthreads']] == 'threads') {
			$topicid = $postid;
			$postid = null;
		} else	$topicid = 0;
	}

	protected function ts_raw_hook(&$ts_raw) {
		if ($this->dbg) $this->write_err('Deleting any "at " in time string "'.$ts_raw.'".');
		$ts_new = preg_replace('/\\bat /', '', $ts_raw);
		if ($this->dbg) $this->write_err('Deleting any "lúc " in time string "'.$ts_raw.'".');
		$ts_new = preg_replace('/\\blúc /', '', $ts_raw);
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

	protected function get_forum_page_url($id, $pg) {
		return $this->settings['base_url'].'/forums/'.$id.'/page-'.$pg;
	}

	static function get_forum_software_homepage_s() {
		return 'http://xenforo.com/';
	}

	protected function init_topic_pg_counter() {
		$this->topic_pg_counter = 1;
	}

	protected function scrape_topic_page__end_hook($html, $matches) {
		$this->topic_pg_counter++;
	}

	protected function get_topic_page_url($forum_id, $topic_id, $topic_pg_counter) {
		return $this->settings['base_url'].'/threads/'.$topic_id.'/page-'.$topic_pg_counter;
	}

	static function get_msg_how_to_detect_forum_s() {
		return 'Typically, XenForo forums can be identified by the presence of the text "Forum software by XenForo" in the footer of their forum pages. It is possible, however, that these footer texts have been removed by the administrator of the forum. In this case, the only way to know for sure is to contact your forum administrator.';
	}

	protected function get_post_contents__end_hook($forumid, $topicid, $postid, $postids, $html, &$found, $err, $count, &$ret) {
		if ($topicid == 0) {
			if (!preg_match('(/([^/]+)/(page-\\d+)?$)', $this->last_url, $matches)) {
				$this->write_err('Error: could not extract the topic ID from the post URL: <'.$this->last_url.'>', __FILE__, __METHOD__, __LINE__);
			} else foreach ($postids as $pid) {
				$this->posts_data[$topicid]['posts'][$pid]['topicid'] = $matches[1];
			}
		}
	}

	protected function get_post_url($forumid, $topicid, $postid, $with_hash = false) {
		return $this->settings['base_url']."/posts/$postid/";
	}

	static function get_qanda_s() {
		$qanda = parent::get_qanda_s();
		$qanda_new = array(
			'q_how_know_xenforo' => array(
				'q' => 'How can I know if a forum is a XenForo forum?',
				'a' => self::get_msg_how_to_detect_forum_s(),
			)
		);

		foreach ($qanda as $id => $qa) {
			$qanda_new[$id] = $qa;

			if ($id == 'q_lang') {
				$qanda_new['q_which_skins_supported'] = array(
					'q' => 'Which skins are supported?',
					'a' => 'Whichever skin(s) is/are default for the <a href="http://civilwartalk.com">CivilWarTalk</a>, <a href="http://ecigssa.co.za/">ECIGS SA</a> and <a href="http://www.skeptiko-forum.com/">Skeptiko</a> forums. FUPS\' XenForo scraping functionality was originally developed as a paid job to extract posts from the CivilWarTalk forum; since then it has been tested on the other two forums and seems to function fine. If you need support for another XenForo skin, feel free to <a href="'.FUPS_CONTACT_URL.'">contact me</a>.',
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
		// $opts = array(
		// 	CURLOPT_FOLLOWLOCATION => false,
		// 	CURLOPT_HEADER         => true ,
		// );
		// if (!curl_setopt_array($this->ch, $opts)) {
		// 	$this->exit_err('Failed to set the following cURL options:'.PHP_EOL.var_export($opts, true), __FILE__, __METHOD__, __LINE__);
		// }

		$response = curl_exec($this->ch);
		if ($response === false) {
			$this->write_err('curl_exec returned false. curl_error returns: "'.curl_error($this->ch).'".', __FILE__, __METHOD__, __LINE__);
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
		// $opts = array(
		// 	CURLOPT_FOLLOWLOCATION => true ,
		// 	CURLOPT_HEADER         => false,
		// );
		// if (!curl_setopt_array($this->ch, $opts)) {
		// 	$this->exit_err('Failed to set the following cURL options:'.PHP_EOL.var_export($opts, true), __FILE__, __METHOD__, __LINE__);
		// }

		if ($this->dbg) $this->write_err("Got search ID of '$search_id'.");
		return $search_id;
	}

	protected function get_search_url() {
		return $this->settings['base_url'].'/search/'.$this->search_id.'/?page='.$this->post_search_counter;
	}

	public function get_settings_array() {
		$settings_arr = parent::get_settings_array();
		$new_settings_arr = array();

		foreach ($settings_arr as $key => $setting) {
			$new_settings_arr[$key] = $setting;
			if ($key == 'extract_user_id') {
				$new_settings_arr['extract_user'] = array(
					'label'       => 'Extract User Username',
					'default'     => ''                     ,
					'description' => 'Set this to the username corresponding to the above ID. Note that it does not and cannot replace the need for the above ID; that ID is required. In contrast, this setting is not required (i.e. it can be left blank) if the script has permission to view member information on the specified phpBB board, in which case the script will extract it automatically from the member information page associated with the above ID: this will fail if the forum requires users to be logged in to view member information, in which case you need to specify this setting.',
					'required'    => false,
					'one_of_required' => false,
				);
			}
		}
		$settings_arr = $new_settings_arr;

		$settings_arr['base_url']['default'] = 'http://civilwartalk.com';
		$settings_arr['base_url']['description'] .= ' This is the URL that appears in your browser\'s address bar when you access the forum, only with everything onwards from (and including) the path of whichever script is being accessed (e.g. /threads or /forums) stripped off. The default URL provided is for the particular XenForo board known as "CivilWarTalk".';
		$settings_arr['extract_user_id']['description'] .= ' You can find a user\'s ID by hovering your cursor over a hyperlink to their name and taking note of everything that appears between "/members/" and the next "/" (i.e. this will be something like "my-member-name.12345") in the browser\'s status bar.';

		$settings_arr['thread_url_prefix'] = array(
			'label' => 'Thread URL prefix',
			'default' => 'threads/',
			'description' => 'Set this to that part of the URL for forum thread (topic) pages between the beginning part of the URL, that which was entered above beside "Base forum URL" but followed by a forward slash, and the end part of the URL, the thread id optionally followed by forward slash and page number. By default, this setting should be "threads/", but the XenForo forum software supports changing this default through <a href="https://xenforo.com/help/route-filters/">route filters</a>, and some XenForo forums have been configured in this way such that this setting ("Thread URL prefix") needs to be empty. An example of how to discern this value (it is emboldened) in a typical thread URL with "Base forum URL" set to "http://civilwartalk.com" is: "http://civilwartalk.com/<b>threads/</b>traveller.84936/page-2". Here, the initial base URL plus forward slash is obvious, the thread id part is "traveller.84936" and the optional-forward-slash-followed-by-page-number part is "/page-2". If route filtering were set up on the CivilWarTalk forum such that this setting should be empty, then that same thread URL would have looked like this: "http://civilwartalk.com/traveller.84936/page-2". If, hypothetically, this "Thread URL prefix" setting were to correctly be "topic/here/", then that same thread URL would have looked like this: "http://civilwartalk.com/topic/here/traveller.84936/page-2".',
			'required' => true,
		);
		$settings_arr['forum_ids']['description'] .= ' You can find a forum\'s ID by hovering your cursor over a forum hyperlink and taking note of the text between "forums/" and the final "/" in the URL in the browser\'s status bar.';

		return $settings_arr;
	}

	protected function get_topic_url($forumid, $topicid) {
		return $this->settings['base_url'].'/'.$this->settings['thread_url_prefix'].$topicid.'/';
	}

	protected function get_user_page_url() {
		return $this->settings['base_url'].'/members/'.urlencode($this->settings['extract_user_id']).'/';
	}

	protected function hook_after__posts_retrieval() {
		if (isset($this->posts_data[0]['posts'])) {
			foreach ($this->posts_data[0]['posts'] as $pid => $post) {
				if (isset($post['topicid'])) {
					$topicid = $post['topicid'];
					if (!isset($this->posts_data[$topicid])) {
						$this->posts_data[$topicid] = array(
							'forum'   => $post['forum'],
							'topic'   => $post['topic'],
							'forumid' => $post['forumid'],
							'posts'   => array(),
						);
					}
					unset($post['forum']);
					unset($post['forumid']);
					unset($post['topic']);
					unset($post['topicid']);
					$this->posts_data[$topicid]['posts'][$pid] = $post;
				} else {
					$this->write_err('Error: $post[\'topicid\'] is unset.', __FILE__, __METHOD__, __LINE__);
				}
			}
			unset($this->posts_data[0]);
		}
	}

	protected function hook_after__user_post_search() {
		$go = is_null($this->current_topic_id);
		foreach ($this->posts_data as $topicid => $dummy) {
			if (!$go) {
				if ($this->current_topic_id == $topicid) $go = true;
			} else {
				$this->current_topic_id = $topicid;
				if (isset($this->posts_data[$topicid]['posts'][null])) {
					$url = $this->settings['base_url'].'/'.$this->settings['thread_url_prefix'].$topicid;
					$this->set_url($url);
					$html = $this->do_send();
					if (!$this->skins_preg_match_all('post_contents', $html, $matches)) {
						$this->write_and_record_err_admin("Error: the regex to detect the first thread post in the thread at <$url> failed.", __FILE__, __METHOD__, __LINE__, $html);
					} else {
						$postid = $matches[0][1];
						$this->posts_data[$topicid] = $this->posts_data[$topicid];
						$this->posts_data[$topicid]['posts'] = array($postid => $this->posts_data[$topicid]['posts'][null]);
						list($root_rel_url_base, $path_rel_url_base, $current_protocol) = static::get_base_urls_s($this->last_url, $html);
						list($found, $postids) = $this->get_post_contents_from_matches($matches, $postid, $topicid, $root_rel_url_base, $path_rel_url_base, $current_protocol, $this->last_url);
						$count = count($postids);
						if ($found) {
							if ($this->dbg) $this->write_err('Retrieved post contents of post ID "'.$postid.'"');
							$count--;
						} else	$this->write_and_record_err_admin('FAILED to retrieve post contents of post ID "'.$postid.'". The URL of the page is "'.$this->last_url.'"', __FILE__, __METHOD__, __LINE__, $html);

						if ($count > 0 && $this->dbg) $this->write_err('Retrieved '.$count.' other posts.');

						if (!$found) $this->posts_not_found[$postid] = true;
						$this->num_posts_retrieved += $count + ($found ? 1 : 0);

						$this->write_status('Retrieved '.$this->num_posts_retrieved.' of '.$this->total_posts.' posts.');
					}
					if (!$this->skins_preg_match('thread_author', $html, $matches)) {
						$this->write_and_record_err_admin("Error: couldn't find a match for the author of the thread with topic id '$topicid'.  The URL of the page is <".$url.'>.', __FILE__, __METHOD__, __LINE__, $html);
						$this->posts_data[$topicid]['startedby'] = '???';
					} else {
						$this->posts_data[$topicid]['startedby'] = $matches[1];
						if ($this->dbg) $this->write_err("Added author of '{$this->posts_data[$topicid]['startedby']}' for topic id '$topicid'.");
						$this->num_thread_infos_retrieved++;
						$this->write_status('Retrieved author for '.$this->num_thread_infos_retrieved.' of '.count($this->posts_data).' threads.');
					}
					$this->check_do_chain();
				}
			}
		}

		$this->current_topic_id = null; # Reset this for progress level 2
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

	public static function supports_feature_s($feature) {
		static $features = array(
			'login' => false,
			'forums_dl' => true,
		);

		return isset($features[$feature]) ? $features[$feature] : parent::supports_feature_s($feature);
	}

	protected function validate_settings() {
		$forum_ids1 = explode(',', $this->settings['forum_ids']);
		if ($forum_ids1) {
			foreach ($forum_ids1 as &$id) {
				$id = trim($id);
			}
			if ($forum_ids1 && $forum_ids1 != array('')) {
				$this->settings['forum_ids_arr'] = $forum_ids1;
				if ($this->dbg) $this->write_err('$this->settings[\'forum_ids_arr\'] == '.var_export($this->settings['forum_ids_arr'], true));
			}
		}
		if (!$this->settings['extract_user_id'] && empty($this->settings['forum_ids_arr'])) {
			$this->exit_err('Neither the "Extract User ID" setting nor the "Forum IDs" setting were specified: at least one of these must be set.', __FILE__, __METHOD__, __LINE__);
		}
	}

	protected function init_forum_page_counter() {
		$this->forum_page_counter = 1;
	}

	protected function scrape_forum_pg__end_hook($html, $num_topics_found, $last_forum_page) {
		if ($num_topics_found > 0 && !$last_forum_page) $this->forum_page_counter++;
	}

	protected function get_topic_id_from_topic_url($url) {
		$prefix = $this->settings['base_url'].'/'.$this->settings['thread_url_prefix'];
		$postfix = substr($url, strlen($prefix));
		if (($pos = strpos($postfix, '/')) !== false) {
			$topicid = substr($postfix, 0, $pos);
		} else	$topicid = $postfix;

		return $topicid;
	}
}

?>
