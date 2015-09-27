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

/* File       : classes/CphpBB.php.
 * Description: The phpBB forum scraper class, descending from FUPSBase.
 */

require_once __DIR__.'/../phpBB-days-and-months-intl.php';

class phpBBFUPS extends FUPSBase {
	protected $regexps = null;
	protected $old_version = false;

	public function __construct($web_initiated, $params, $do_not_init = false) {
		if (!$do_not_init) {
			$this->optional_settings[] = 'extract_user';
		}

		parent::__construct($web_initiated, $params, $do_not_init);

		if (!$do_not_init) {
			$this->regexps = array(
				/* 'template_skin' => array(
					'sid'                      => a regexp to extract the SID value from the login page <ucp.php?mode=login>
					'board_title'              => a regexp to extract the board's title from the login page
								<ucp.php?mode=login>
					'login_success'            => a regexp to match the html of a successful-login page
					'login_required'           => a regexp to match a phpBB error that login is required to view
								member details
					'user_name'                => a regexp to extract the user's name from the user's profile page
								<memberlist.php?mode=viewprofile&u=[user_id]>
					'thread_author'            => a regexp to extract the thread's author from the thread view page
								<viewtopic.php?f=[forumid]&t=[topicid]>
					'search_results_not_found' => a regexp to detect when a search results page returns no results i.e. on:
								<search.php?st=0&sk=t&sd=d&author_id=[author_id]&start=[start]>
					// N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
					'search_results_page_data' => a regexp to be matched on the user's posts search page
								<search.php?st=0&sk=t&sd=d&author_id=[author_id]&start=[start]> using
								preg_match_all with flags set to PREG_SET_ORDER so that each entry of
								$matches ends up with the following matches in the order specified in
								search_results_page_data_order.
					'search_results_page_data_order' => an array specifying the order in which the following matches occur
									in the matches returned by the previous array.
						= array(
							'title'   => the match index of the title of post,
							'ts'      => the match index of the timestamp of post,
							'forum'   => the match index of the title of forum,
							'topic'   => the match index of the thread topic,
							'forumid' => the match index of the forum id,
							'topicid' => the match index of the topic id,
							'postid'  => the match index of the post id,
						)
					'search_id'                => a regexp to match the search id (only available in older versions of phpBB)
					'post_contents'            => a regexp to match post id (first match) and post contents (second match)
								on a thread page; it is called with match_all so it will return all
								post ids and contents on the page
					'prev_page'                => a regexp to extract the forumid (first match), topicid (second match) and
								start (third match) parameters from the "previous page" url on a thread
								view page
					'next_page'                => a regexp to extract the forumid (first match), topicid (second match) and
								start (third match) parameters from the "next page" url on a thread
								view page
				),
				*/
				'mobile' => array(
					'board_title'              => '#<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile.*&bull;[ ]((?:(?!&bull;).)*)</title>#Us',
					'login_success'            => '#<table cellspacing="0">\\s*<tr class="row1">\\s*<td align="center"><p class="gen">#Us',
					'login_required'           => '#<table cellspacing="0">\\s*<tr class="row2">\\s*<td>#',
					'user_name'                => '#<b class="genmed">([^<]*)</b>#',
					'thread_author'            => '#<strong class="postauthor"[^>]*>[ ]([^<]*)</strong>#',
					'search_results_not_found' => '#<h2>[^0-9<]*0[^0-9<]*</h2>#',
					# N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
					'search_results_page_data' => '#<span class="topictitle"><a name="p(\d+?)".*viewforum\.php\?f=(\d+?)[^>]*>([^<]*)</a>.*viewtopic\.php\?f=\d+?&amp;t=(\d+?)[^>]*>([^<]*)</a>.*viewtopic\.php\?[^>]*>([^<]*)</a>.*</b>[ ]([^<]*)</p>#Us',
					'search_results_page_data_order' => array('title' => 6, 'ts' => 7, 'forum' => 3, 'topic' => 5, 'forumid' => 2, 'topicid' => 4, 'postid' => 1),
					'post_contents'            => '#<tr class="row1">\\s*<td class="gensmall"><a href="\\./viewtopic\\.php\\?p=(\\d+?).*<tr class="row1">\\s*<td>\\s*<div class="postbody">(.*)</div>\\s*</td>\\s*</tr>\\s*</table>#Us',
				),
				'prosilver.1' => array(
					'sid'                      => '/name="sid" value="([^"]*)"/',
					'board_title'              => '#<h1>(.*)</h1>#',
					'login_success'            => '/<div class="panel" id="message">/',
					'login_required'           => '/class="panel"/',
					'user_name'                => '#<dl class="left-box details"[^>]*>\\s*<dt>[^<]*</dt>\\s*<dd>\\s*<span>([^<]+)</span>#Us',
					'thread_author'            => '#<p class="author">.*memberlist\.php.*>(.+)<#Us',
					'search_results_not_found' => '#<div class="panel" id="message">\\s*<div class="inner"><span class="corners-top"><span></span></span>\\s*<h2>#Us',
					# N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
					'search_results_page_data' => '#<h3>[^>]*>([^<]*)</a>.*<dl class="postprofile">.*<dd>'.get_posted_on_date_regex().' (.+)</dd>.*<dd>[^:]*: .*>(.+)</a>.*<dd>[^:]*: .*>(.+)</a>.*viewtopic\.php\?f=(\d+?)&amp;t=(\d+?)&amp;p=(\d+?)#Us',
					'search_results_page_data_order' => array('title' => 1, 'ts' => 3, 'forum' => 4, 'topic' => 5, 'forumid' => 6, 'topicid' => 7, 'postid' => 8),
					'post_contents'            => '#<div id="p(\d+)".*<div class="content">(.*)</div>[\r\n]+#Us',
					'prev_page'                => '#<strong>\\d+</strong>[^<]+<strong>\\d+</strong>.*<a href="\\./viewtopic\\.php\?f=(\\d+)&amp;t=(\\d+)&amp;start=(\\d+?)[^"]*">\\d+</a><span class="page-sep">, </span><strong>\\d+</strong>#Us',
					'next_page'                => '#<strong>\\d+</strong><span class="page-sep">, </span><a href="\\./viewtopic\\.php\\?f=(\\d+)&amp;t=(\\d+)&amp;start=(\\d+?)[^"]*">[^<]*</a>#Us',
				),
				'prosilver.2' => array(
					'search_results_page_data' => '#<h3>[^>]*>([^<]*)</a>.*<dl class="postprofile">(?:(?!</dl>).)*<dd>([^<]+)</dd>.*<dd>[^:]*: .*>(.+)</a>.*<dd>[^:]*: .*>(.+)</a>.*viewtopic\.php\?f=(\d+?)&amp;t=(\d+?)&amp;p=(\d+?)#Us',
					'search_results_page_data_order' => array('title' => 1, 'ts' => 2, 'forum' => 3, 'topic' => 4, 'forumid' => 5, 'topicid' => 6, 'postid' => 7),
				),
				'prosilver.3' => array(
					'search_results_page_data' => '#<dl class="postprofile">.*<dd[^>]*>([^<]+)</dd>.*<dd>[^:]*: .*>(.+)</a>.*<dd>[^:]*: .*>(.+)</a>.*<h3>.*viewtopic\.php\?f=(\d+?)&amp;t=(\d+?)&amp;p=(\d+?)[^>]*>([^<]+)</a>#Us',
					'search_results_page_data_order' => array('title' => 7, 'ts' => 1, 'forum' => 2, 'topic' => 3, 'forumid' => 4, 'topicid' => 5, 'postid' => 6),
				),
				'subsilver.2' => array(
					/* 'sid'                      => ? (not constructed yet), */
					/* 'board_title'              => ? (not constructed yet), */
					'login_success'            => '#<table class="tablebg" width="100%" cellspacing="1">\\s*<tr>\\s*<th>[^<]*</th>\\s*</tr>\\s*<tr>\\s*<td class="row1" align="center"><br /><p class="gen">#Us',
					/* 'login_required'           => ? (not constructed yet), */
					'user_name'                => '#<td align="center"><b class="gen">([^<]*)</b></td>#',
					/* 'thread_author'            => ? (not constructed yet), */
					'search_results_not_found'  => '#<td class="row1" align="center"><br /><p class="gen">[^<]*</p><br /></td>#',
					# N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
					'search_results_page_data'  => '#<tr class="row2">\\s*<td colspan="2" height="25"><p class="topictitle"><a name="p(\\d+)" id="p\\d+"></a>&nbsp;[^:]*: <a href="\\./viewforum\\.php\\?f=(\\d+?)[^"]*">([^<]*)</a> &nbsp; [^:]*: <a href="\\./viewtopic\\.php\\?f=\\d+&amp;t=(\\d+?)[^"]*">([^<]+)</a> </p></td>\\s*</tr>\\s*<tr class="row1">\\s*<td width="150" align="center" valign="middle"><b class="postauthor"><a href="[^"]*">[^<]*</a></b></td>\\s*<td height="25">\\s*<table width="100%" cellspacing="0" cellpadding="0" border="0">\\s*<tr>\\s*<td class="gensmall">\\s*<div style="float: left;">\\s*&nbsp;<b>[^:]*:</b> <a href="[^"]*">([^<]*)</a>\\s*</div>\\s*<div style="float: right;"><b>[^:]*:</b>\\s(.*)&nbsp;</div>#Us',
					'search_results_page_data_order' => array('title' => 6, 'ts' => 7, 'forum' => 3, 'topic' => 5, 'forumid' => 2, 'topicid' => 4, 'postid' => 1),
					/* 'post_contents'            => ? (not constructed yet), */
					/* 'prev_page'                => same as for prosilver.1 */
					'next_page'                => '#<strong>\d+</strong>[^<]+<strong>\d+</strong>.*<a href="\\./viewtopic\\.php\?f=(\\d+)&amp;t=(\d+)&amp;start=(\\d+?)[^"]*">[^<]*</a></b></td>#Us',
				),
				// Try the above first
				'subsilver.2x' => array(
					// N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
					'search_results_page_data'  => '#<tr class="row2">\\s*<td colspan="2" height="25"><p class="topictitle"><a name="p(\\d+?)" id="p\\d+"></a>&nbsp;[^:]*: <a href="\\./viewforum\\.php\\?f=(\\d+?)[^"]*">([^<]*)</a> &nbsp; [^:]*: <a href="\\./viewtopic\\.php\\?f=\\d+&amp;t=(\\d+?)">([^<]+)</a> </p></td>\\s*</tr>\\s*<tr class="row1">\\s*<td width="150" align="center" valign="middle"><b class="postauthor"><a href="[^"]*">[^<]*</a></b></td>\\s*<td height="25">\\s*<table width="100%" cellspacing="0" cellpadding="0" border="0">\\s*<tr>\\s*<td class="gensmall">\\s*<div style="float: left;">\\s*\\[[^\\]]*\\]\\s*</div>\\s*<div style="float: right;"><b>[^:]*:</b>\\s(.*)&nbsp;</div>#Us',
					'search_results_page_data_order' => array('title' => 7 /* this match is deliberately designed to be an empty one because posts matching this regex don't actually have a title, which is the whole reason this subsilver.2x entry is necessary */, 'ts' => 6, 'forum' => 3, 'topic' => 5, 'forumid' => 2, 'topicid' => 4, 'postid' => 1),
				),
				'subsilver.1' => array(
					/* 'sid'                      => ? (not constructed yet), */
					/* 'board_title'              => ? (not constructed yet), */
					/* 'login_success'            => ? (not constructed yet), */
					/* 'login_required'           => ? (not constructed yet), */
					/* 'user_name'                => ? (not constructed yet), */
					'thread_author'             => '#<b class="postauthor">(.+)</b>#Us',
					/* 'search_results_not_found' => ? (not constructed yet), */
					// N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
					/* 'search_results_page_data' => ? (not constructed yet), */
					'post_contents'             => '#<a name="p(\\d+)">.*<td valign="top">\\s*<table width="100%" cellspacing="5">\\s*<tr>\\s*<td>\\s*?(.*)(\\s*?<br /><br />\\s*<span class="gensmall">.*</span>|)\\n\\s*<br clear="all" /><br />#Us',
					'prev_page'                 => '#<a href="\\./viewtopic\\.php\\?f=(\\d+)&amp;t=(\\d+)&amp;start=(\\d+)">[^<]+</a>&nbsp;&nbsp;<a href="\\./viewtopic\\.php\\?f=\\d+&amp;t=\\d+[^"]*">\\d+</a><span class="page-sep">,#',
					/* 'next_page'                => ? (not constructed yet), */
				),
				'subsilver.2005' => array(
					'login_success'            => '#<a href="privmsg\\.php\\?folder=inbox"#',
					'user_name'                => '#alt="[^\\[]*\\[ (.*) \\]"#',
					'search_results_page_data' => '#<span class="topictitle">.*&nbsp;<a href="viewtopic\\.php\\?t=(\\d+?).*class="topictitle">([^<]*)</a></span></td>.*<span class="postdetails">[^:]*:&nbsp;<b><a href="viewforum\\.php\\?f=(\\d+?)[^>]*>([^<]*)</a></b>&nbsp; &nbsp;[^:]*: ([^&]*?)&nbsp;.*viewtopic\\.php\\?p=(\\d+?)[^>]*>([^<]*)</a></b></span>#Us',
					'search_results_page_data_order' => array('topicid' => 1, 'topic' => 2, 'forumid' => 3, 'forum' => 4, 'ts' => 5, 'postid' => 6, 'title' => 7),
					'post_contents'            => '#<td class="row1".*<a href="viewtopic\\.php\\?p=(\\d+?).*<tr>\\s*<td colspan="2"><hr /></td>\\s*</tr>\\s*<tr>\\s*<td colspan="2">(.*)</td>\\s*</tr>\\s*</table></td>\\s*</tr>#Us',
				),
				'subsilver.0' => array(
					'sid'                      => '#href="\\./index\\.php\\?sid=([^"]*)"#',
					/** @todo Remove English-specific components of this regex ("Log in" and potentially the double-colon). */
					'board_title'              => '#<title>(.*) :: Log in</title>#',
					/* 'login_success'            => ? (not constructed yet), */
					/* 'login_required'           => ? (not constructed yet), */
					/* 'user_name'                => ? (not constructed yet), */
					'search_results_not_found' => '#<table border="0" cellpadding="3" cellspacing="1" width="100%" class="forumline" align="center">\\s*<tr>\\s*<th width="150" height="25" class="thCornerL" nowrap="nowrap">Author</th>\\s*<th width="100%" class="thCornerR" nowrap="nowrap">Message</th>\\s*</tr>\\s*<tr>\\s*<td class="catBottom" colspan="2" height="28" align="center">&nbsp; </td>\\s*</tr>\\s*</table>#Us',
					// N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
					'search_results_page_data' => '#<tr>\\s*<td[^>]*><span class="topictitle"><img src="[^"]+" align="absmiddle" />&nbsp; .*:&nbsp;<a href="viewtopic\\.php\\?t=(\\d+)&amp;highlight=" class="topictitle">([^<]*)</a></span></td>\\s*</tr>\\s*<tr>\\s*<td width="\\d+" align="left" valign="top" class="row1" rowspan="2"><span class="name"><b><a href="profile\\.php\\?mode=viewprofile&amp;u=3">[^<]*</a></b></span><br />\\s*<br />\\s*<span class="postdetails">[^<]*<b>[^<]*</b><br />\\s*[^<]*<b>[^<]*</b></span><br />\\s*</td>\\s*<td width="100%" valign="top" class="row1"><img[^>]*><span class="postdetails">[^<]*<b><a href="viewforum\\.php\\?f=(\\d+)" class="postdetails">([^<]*)</a></b>&nbsp; &nbsp;[^:]*: (.*)&nbsp; &nbsp;[^:]*: <b><a href="viewtopic\\.php\\?p=(\\d+)&amp;highlight=\\#\\d+">([^<]+)</a></b></span></td>\\s*</tr>#Us',
					'search_results_page_data_order' => array('title' => 7, 'ts' => 5, 'forum' => 4, 'topic' => 2, 'forumid' => 3, 'topicid' => 1, 'postid' => 6),
					'search_id'                => '#\\?search_id=(\\d+)&#',
					'post_contents'            => '#<tr>\\s*<td width="100%"><a href="viewtopic\\.php\\?p=(\\d+?)[^\\#]*\\#\\d+"><img[^>]*></a><span class="postdetails">[^<]*<span class="gen">&nbsp;</span>[^<]*</span></td>\\s*<td valign="top" nowrap="nowrap"><a href="posting\\.php\\?[^"]*"><img[^>]*></a>\\s*</td>\\s*</tr>\\s*<tr>\\s*<td colspan="2"><hr /></td>\\s*</tr>\\s*<tr>\\s*<td colspan="2"><span class="postbody">(.*)</span><span class="gensmall">(<br /><br />|)[^<]*</span></td>\\s*</tr>#Us',
					'thread_author'            => '#<b>(.*?)</b></span><br /><span class="postdetails">#',
					'prev_page'                => '#()<span class="gensmall"><b>.*?<a href="viewtopic\\.php\\?t=(\\d+?).*start=(\\d+?)[^>]*>[^<]*</a>, <b>#U',
					'next_page'                => '#()<span class="gensmall"><b>[^<]+<a href="viewtopic\\.php\\?t=(\\d+).*start=(\\d+)[^"]*">#',
				),
				'forexfactory' => array(
					'sid'                      => '#SSIONURL = \'?(s\=)(.*&)|(.*)\';#',
					'board_title'              => '#<title>(.*)</title>#',
				),
			);
		}
	}

	protected function check_do_login() {
		# Do this first bit so that we set old_version if necessary regardless of whether or not the user supplied credentials.

		# Discover the SID
		$this->set_url($this->settings['base_url'].'/ucp.php?mode=login');
		$redirect = false;
		$html = $this->do_send($redirect, /*$quit_on_error*/false, $err);
		
		if ($err) {
			# Earlier versions of phpBB need a different URL
			$this->old_version = true;
		}

		# Do the rest conditionally on the user having supplied credentials.
		if (!empty($this->settings['login_user']) || !empty($this->settings['login_password'])) {
			if ($this->old_version) {
				$this->set_url($this->settings['base_url'].'/login.php');
				$html = $this->do_send();
			}
			if ($this->skins_preg_match('sid', $html, $matches)) {
				$sid = $matches[1];
				if ($this->dbg) $this->write_err('SID: '.$sid);
			} else {
				$this->exit_err('Could not find the hidden sid input on the login page. The URL of the searched page is <'.$this->last_url.'>', __FILE__, __METHOD__, __LINE__, $html);
			}
			$this->write_status('Attempting to log in.');
			# Attempt to log in
			if ($this->dbg) $this->write_err('Attempting to log in.');
			$postfields = array(
				'username' => $this->settings['login_user'],
				'password' => $this->settings['login_password'],
				'autologin' => '',
				'viewonline' => '',
				'redirect' => 'index.php',
				'sid' => $sid,
				'login' => 'true',
			);
			$opts = array(
				CURLOPT_POST           => true       ,
				CURLOPT_POSTFIELDS     => $postfields
			);
			if (!curl_setopt_array($this->ch, $opts)) {
				$this->exit_err('Failed to set the following cURL options:'.PHP_EOL.var_export($opts, true), __FILE__, __METHOD__, __LINE__);
			}

			# A successful login either redirects via HTTP or returns a page with a message matching the 'login_success' regex.
			$html = $this->do_send($redirect);
			if ((!$html && $redirect) || $this->skins_preg_match('login_success', $html, $dummy)) {
				if ($this->dbg) $this->write_err('Logged in successfully.');
			} else {
				$this->exit_err('Login was unsuccessful (did not find success message). This could be due to a wrong username/password combination. The URL is <'.$this->last_url.'>', __FILE__, __METHOD__, __LINE__,  $html);
			}

			# Set cURL method back to GET because this class and especially its ancestor rely on the default method being GET
			if (!curl_setopt($this->ch, CURLOPT_POST, false)) {
				$this->exit_err_resumable('Failed to set cURL option CURLOPT_POST back to false.',__FILE__, __METHOD__, __LINE__);
			}
		}
	}

	protected function find_author_posts_via_search_page__end_hook(&$do_inc_progress_level, $html, $found_earliest, $matches) {
		if ($this->post_search_counter === 0 && !$found_earliest) {
			if ($this->skins_preg_match('search_id', $html, $matches__search_id)) {
				$this->search_id = $matches__search_id[1];
			}
		}
		parent::find_author_posts_via_search_page__end_hook($do_inc_progress_level, $html, $found_earliest, $matches);
	}

	# Strip any preceding text in the timestamp such as "on" e.g. [posted] "on Mon 28 September 2015 6:05am".
	protected function find_author_posts_via_search_page__ts_raw_hook(&$ts_raw) {
		global $intl_data;
		static $posted_on_date_arr = null;

		if (is_null($posted_on_date_arr)) {
			$posted_on_date_arr = array('on');
			foreach ($intl_data as $arr) {
				if (isset($arr['POSTED_ON_DATE']) && $arr['POSTED_ON_DATE'] != '') {
					$posted_on_date_arr[] = $arr['POSTED_ON_DATE'];
				}
			}
		}

		foreach ($posted_on_date_arr as $on) {
			if (substr($ts_raw, 0, strlen($on)) == $on) {
				$ts_raw = substr($ts_raw, strlen($on));
			}
		}
	}

	# For quotes rendered by the subsilver skin
	protected function get_extra_head_lines() {
		return '<style type="text/css">
.quotetitle, .attachtitle {
	margin: 10px 5px 0 5px;
	padding: 4px;
	font-weight: bold;
}

.quotecontent, .attachcontent {
	margin: 0 5px 10px 5px;
	padding: 5px;
	font-weight: normal;
}
</style>';
	}

	static function get_forum_software_homepage() {
		return 'https://www.phpbb.com/';
	}

	static function get_msg_how_to_detect_forum() {
		return 'Typically, phpBB forums can be identified by the presence of the text "Powered by phpBB" in the footer of their forum pages. It is possible, however, that these footer texts have been removed by the administrator of the forum. In this case, the only way to know for sure is to contact your forum administrator.';
	}

	protected function get_post_contents__end_hook($forumid, $topicid, $postid, $html, &$found, $err, $count, &$ret) {
		# Sometimes (this seems to be a phpBB bug), posts don't appear on the thread page they're supposed to,
		# and instead appear on the previous or next page in the thread. Here, we deal with those scenarios.
		$org_url = $this->last_url;
		if (!$found) {
			$this->write_err('Trying to find post ID '.$postid.' on previous page of thread, if that page exists.');
			if (!$this->skins_preg_match('prev_page', $html, $matches__prev_page)) {
				$this->write_and_record_err_admin('Warning: could not extract the details of the previous thread page from the current page. The URL of the current page is <'.$org_url.'>.', __FILE__, __METHOD__, __LINE__, $html);
			} else {
				$this->set_url($this->get_topic_url($matches__prev_page[1], $matches__prev_page[2], $matches__prev_page[3]));
				$html__prev_page = $this->do_send();
				if (!$this->skins_preg_match_all('post_contents', $html__prev_page, $matches__prev_posts)) {
					$this->write_and_record_err_admin('Warning: could not find any post contents on the previous page in the thread. The URL of that previous page in the thread is: '.$this->last_url, __FILE__, __METHOD__, __LINE__, $html__prev_page);
				} else {
					list($found, $count) = $this->get_post_contents_from_matches($matches__prev_posts, $postid, $topicid);
					if ($found) {
						$this->write_err('Success! Retrieved post contents of post ID "'.$postid.'".');
						$ret = true;
					} else {
						$this->write_and_record_err_admin("Warning: post ID '$postid' not found on previous page. The URL of that previous page is <".$this->last_url.'>.', __FILE__, __METHOD__, __LINE__, $html__prev_page);
					}
					if ($found) $count--;
					if ($count > 0 && $this->dbg) $this->write_err('Retrieved '.$count.' other posts from the page.');
				}
			}
		}
		if (!$found) {
			$this->write_err('Trying to find post ID '.$postid.' on next page of thread, if that page exists.');
			if (!$this->skins_preg_match('next_page', $html, $matches__next_page)) {
				$this->write_and_record_err_admin('Warning: could not extract the details of the next thread page from the current page. The URL of that page is <'.$org_url.'>.', __FILE__, __METHOD__, __LINE__, $html);
			} else {
				$this->set_url($this->get_topic_url($matches__next_page[1], $matches__next_page[2], $matches__next_page[3]));
				$html__next_page = $this->do_send();
				if (!$this->skins_preg_match_all('post_contents', $html__next_page, $matches__next_posts)) {
					$this->write_and_record_err_admin('Warning: could not find any post contents on the next page in the thread. The URL of that next page in the thread is: '.$this->last_url, __FILE__, __METHOD__, __LINE__, $html__next_page);
				} else {
					list($found, $count) = $this->get_post_contents_from_matches($matches__next_posts, $postid, $topicid);
					if ($found) {
						$this->write_err('Success! Retrieved post contents of post ID "'.$postid.'".');
						$ret = true;
					} else if ($err || $this->dbg) {
						$this->write_and_record_err_admin("Warning: post ID '$postid' not found on next page. The URL of that next page is <".$this->last_url.'>.', __FILE__, __METHOD__, __LINE__, $html__next_page);
					}
					if ($found) $count--;
					if ($count > 0 && $this->dbg) $this->write_err('Retrieved '.$count.' other posts from the page.');
				}
			}
		}
	}

	protected function get_post_url($forumid, $topicid, $postid, $with_hash = false) {
		return $this->settings['base_url']."/viewtopic.php?f=$forumid&t=$topicid&p=$postid".($with_hash ? '#p'.$postid : '');
	}

	static function get_qanda() {
		$qanda = parent::get_qanda();
		$qanda = array_merge($qanda, array(
			'q_relationship' => array(
				'q' => 'Does this script have any relationship with <a href="https://github.com/ProgVal/PHPBB-Extract">the PHPBB-Extract script on GitHub</a>?',
				'a' => 'No, they are separate projects.',
			),
		));
		$qanda_new = array(
			'q_how_know_phpbb' => array(
				'q' => 'How can I know if a forum is a phpBB forum?',
				'a' => self::get_msg_how_to_detect_forum(),
			)
		);

		foreach ($qanda as $id => $qa) {
			$qanda_new[$id] = $qa;

			if ($id == 'q_lang') {
				$qanda_new['q_login_req'] = array(
					'q' => 'Do I need to supply a login username and password?',
					'a' => '<p>Probably not. These are the conditions under which you do:</p>

			<ul>
				<li>You do not supply a value for the Extract User Username setting, and the phpBB board you\'re retrieving from requires login before it will display member information.</li>
				<li>Your local timezone (configured in your board preferences) is different to the board\'s default timezone, and you wish for all dates and times displayed against your posts to be in your local timezone.</li>
				<li>You are retrieving posts from a private forum.</li>
			</ul>',
				);


				$qanda_new['q_login_details_safe'] = array(
					'q' => 'Is it safe to supply my login username and password?',
					'a' => '<p>You will need to use your judgement here. I have attempted to make it as safe as possible without compromising simplicity. Your username and password, along with all other settings, will be stored in one or two files in a private directory (i.e. not accessible via the web) on my web hosting account for no longer than three days (a scheduled task deletes these files periodically; it runs once a day and deletes files more than two days old). In addition, you will be presented with an option after the script runs, or, if you cancel the script, to delete immediately all files associated with your request. I will never look inside the temporary files containing your username/password.</p>

			<p>If this doesn\'t satisfy you, you might consider temporarily changing your password for the script, and then changing it back again once the script has finished.</p>');

				$qanda_new['q_post_contents_safe'] = array(
					'q' => 'Is it safe to retrieve posts from a private forum through this script?',
					'a' => 'Your username and password are as safe as the previous answer describes. The content of your posts (the output file) is slightly less safe in that this output file is publicly accessible - but only to those who know the 32-character random token associated with it, and only until it is deleted either by you after you have saved it, or by the daily scheduled deletion task. As with usernames and passwords, I will never look inside the temporary file containing your posts\' content.',
				);

				$qanda_new['q_images_supported'] = array(
					'q' => 'Are images supported?',
					'a' => 'External images are supported so long as you are online at the time of viewing the output - they are not downloaded, the link is merely retained. Internal images - those uploaded to the forum as attachments - aren\'t supported at all; they occur as relative URLs, which the script does not convert into absolute URLs.',
				);

				$qanda_new['q_which_skins_supported'] = array(
					'q' => 'Which skins are supported?',
					'a' => 'Both the prosilver and subsilver skins are supported. The script probably won\'t work with customised skins, but if you desire support for such a skin (you are getting error messages about regular expressions failing), feel free to <a href="'.FUPS_CONTACT_URL.'">contact me</a>. A workaround is to simply set your skin to either prosilver or subsilver in the user control panel of your phpBB forum whilst you are logged in, and then to supply your login credentials in the settings above, optionally reverting your skin back to whatever it was before in the user control panel after running FUPS.',
				);
			}
		}

		return $qanda_new;
	}

	protected function get_search_url() {
		if ($this->old_version) {
			$url = $this->settings['base_url'].'/search.php?'.($this->search_id !== null ? 'search_id='.urlencode($this->search_id) : 'search_author='.urlencode($this->settings['extract_user'])).'&start='.urlencode($this->post_search_counter);
		} else	$url = $this->settings['base_url'].'/search.php?st=0&sk=t&sd=d&author_id='.urlencode($this->settings['extract_user_id']).'&start='.urlencode($this->post_search_counter);

		return $url;
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
					'description' => 'Set this to the username corresponding to the above ID. Note that it does not and cannot replace the need for the above ID; that ID is required. In contrast, this setting is not required (i.e. it can be left blank) if the script has permission to view member information on the specified phpBB board, in which case the script will extract it automatically from the member information page associated with the above ID: this will fail if the forum requires users to be logged in to view member information and if you do not provide valid login credentials (which can be specified below), in which case you should specify this setting.',
				);
			}
		}

		$new_settings_arr['base_url']['default'] = 'http://www.theabsolute.net/phpBB';
		$new_settings_arr['base_url']['description'] .= ' This is the URL that appears in your browser\'s address bar when you access the forum, only with everything onwards from (and including) the filename of whichever script is being accessed (e.g. /index.php or /viewtopic.php) stripped off. The default URL provided is for the particular phpBB board known as "Genius Forums".';
		$new_settings_arr['extract_user_id']['description'] .= ' You can find a user\'s ID by hovering your cursor over a hyperlink to their name and taking note of the number that appears after "&amp;u=" in the URL in the browser\'s status bar.';
		$new_settings_arr['login_user']['description'] = 'Set this to the username of the user whom you wish to log in as (it\'s fine to set it to the same value as Extract User Username above), or leave it blank if you do not wish FUPS to log in. Logging in is optional but if you log in then the timestamps associated with each post will be according to the timezone specified in that user\'s preferences, rather than the board default. Also, some boards require you to be logged in so that you can view posts. If you don\'t want to log in, then simply leave blank this setting and the next setting.';

		return $new_settings_arr;
	}

	protected function get_topic_url($forumid, $topicid, $start = null) {
		return $this->settings['base_url'].'/viewtopic.php?f='.urlencode($forumid).'&t='.urlencode($topicid).($start === null ? '' : '&start='.urlencode($start));
	}

	protected function get_user_page_url() {
		return $this->settings['base_url'].'/memberlist.php?mode=viewprofile&u='.urlencode($this->settings['extract_user_id']);
	}

	public function supports_feature($feature) {
		static $features = array(
			'login' => true
		);

		return isset($features[$feature]) ? $features[$feature] : parent::supports_feature($feature);
	}

	protected function validate_settings() {
		parent::validate_settings();

		if (filter_var($this->settings['extract_user_id'], FILTER_VALIDATE_INT) === false) {
			$this->exit_err('The value supplied for the extract_user_id setting, "'.$this->settings['extract_user_id'].'", is not an integer, which it is required to be for phpBB boards.', __FILE__, __METHOD__, __LINE__);
		}
	}
}

?>
