<?php

/* 
 * FUPS: Forum user-post scraper. An extensible PHP framework for scraping and
 * outputting the posts of a specified user from a specified forum/board
 * running supported forum software. Can be run as either a web app or a
 * commandline script.
 *
 * Copyright (C) 2013-2018 Laird Shaw.
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
	protected static $partial_attach_support_warning = 'Note however that attachments are not supported on all skins: if the version of the phpBB software that your forum is running is old then FUPS might not scrape attachments even if you do check "Scrape attachments".';
	protected $regexps = null;
	protected $old_version = false;

	public function __construct($web_initiated, $params, $do_not_init = false) {
		parent::__construct($web_initiated, $params, $do_not_init);

		if (!$do_not_init) {
			$this->regexps = array(
				/* 'template_skin' => array(
					'sid'                      => a regexp to extract the SID value from the login page <ucp.php?mode=login>
					'form_token'               => a regexp to extract the form_token value from the login page <ucp.php?mode=login>
					'creation_time'            => a regexp to extract the creation_time value from the login page <ucp.php?mode=login>
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
					'post_contents'            => a regexp to match post id (first match), post contents (second match) and, if any,
					                              attachments HTML (third match) on a thread page; it is called with match_all
					                              so it will return all post ids and contents on the page
					'prev_page'                => a regexp to extract the forumid (first match), topicid (second match) and
								start (third match) parameters from the "previous page" url on a thread
								view page
					'next_page'                => a regexp to extract the forumid (first match), topicid (second match) and
								start (third match) parameters from the "next page" url on a thread
								view page
					'attachments'              => a regexp to extract the attachments from the third match of 'post_contents'.
					'attachments_order'        => an array specifying the order in which the following matches occur in the matches
					                              returned by the previous array.
					                              = array(
					                                      'comment' => the match index of any comment/label associated with the attachment,
					                                      'file_url' => the match index of the source URL of the attachment if it is not an image,
					                                      'file_name' => the match index of the filename of the attachment if it is not an image,
					                                      'img_url' => the match index of the source URL of the attachment if it is an image,
					                                      'img_name' => the match index of the filename of the attachment if it is an image,
					                              )
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
					'topic'                     => a regex to match the topic title on a topic (thread) page.
					'forum_title'               => a regex to match the forum title on a (sub)forum page.
					'last_topic_page'           => a regex to match when this is the last page of a topic.
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
					// N.B. Does not (yet) contain a match for attachments.
					'post_contents'            => '#<tr class="row1">\\s*<td class="gensmall"><a href="\\./viewtopic\\.php\\?p=(\\d+?).*<tr class="row1">\\s*<td>\\s*<div class="postbody">(.*)</div>\\s*</td>\\s*</tr>\\s*</table>#Us',
				),
				'prosilver.?' => array(
					'last_search_page'         => '(<li class="active"><span>(\\d+)</span></li>\\s*</ul>\\s*</div>\\s*</div>)',
					'last_forum_page'          => '(<div class="pagination">.*&bull;.*<strong>(\\d+)</strong>[^<]*<strong>\\1</strong>)Us',
				),
				'prosilver.html' => array(
					'forum_page_topicids'      => '(<a\\s+href="[^"]+-t(\\d+).html"\\s+class="topictitle">)',
					'topic'                    => '(<div\\sid="page-body">.*<h2><a\\s+href="[^"]+-t\\d+\\.html">([^<]*)</a></h2>)s',
					'forum_title'              => '(<div\\sid="page-body"[^>]*>.*<h2[^>]*><a\\s*[^>]+>([^<]+)</a></h2>)s',
				),
				'prosilver_3.3.2' => array(
					'post_contents_ext'        => '(<div\\s+class="postbody">.*<h3[^>]*>\\s*(?:<img\\s+[^>]+>\\s+)?<a\\s*href="[^#]*#p(\\d+)">([^<]*)</a>\\s*</h3>.*<p\\s+class="author">\\s*<a\\s+[^>]*href="[^"]*"[^>]*>.*<strong>(<a[^>]*>)?(?:<span[^>]*>)?([^><]*)(?:</span>)?(</a>)?</strong>\\s*(?:&raquo;|»)\\s*</span><time\\sdatetime="[^"]+">([^<]*)</time>\\s*</p>\\s*<div\\s+class="content">(.*)</div>\\s*(?:<dl\\sclass="attachbox">.*</dl>\\s*)?(?:<div\\s+class="notice">.*</div>\\s*)?(?:<div\\s+[^>]*class="signature">(.*)</div>\\s*)?(<div\\s[^>]*>\\s*</div>\\s*){0,2}?</div>\\s*</div>\\s*<div\\s+class="back2top")Us',
					'post_contents_ext_order'  => array(
						'author'  => 4,
						'title'   => 2,
						'ts'      => 6,
						'postid'  => 1,
						'contents'=> 7,
					),
				),
				'prosilver_3.2.x' => array(
					'last_forum_page'          => '(<li\\s+class="active"><span>\\d+</span></li>\\s*</ul>)',
					'last_topic_page'          => '(<li\\s+class="active"><span>\\d+</span></li>\\s*</ul>)',
					'post_contents_ext'        => '(<div\\s+class="postbody">.*<h3[^>]*>(?:<img\\s+[^>]+>\\s+)?<a\\s*href="#p(\\d+)">([^<]*)</a></h3>.*<p\\s+class="author">\\s*<a\\s*[^>]*href="[^"]*"[^>]*>.*<strong>(<a[^>]*>)?([^><]*)(</a>)?</strong>\\s*(?:&raquo;|»)\\s*</span>([^<]*)</p>\\s*<div\\s+class="content">(.*)</div>\\s*(?:<dl\\sclass="attachbox">.*</dl>\\s*)?(?:<div\\s+class="notice">.*</div>\\s*)?(?:<div\\s+[^>]*class="signature">(.*)</div>\\s*)?</div>\\s*</div>\\s*<div\\s+class="back2top")Us',
					'post_contents_ext_order'  => array(
						'author'  => 4,
						'title'   => 2,
						'ts'      => 6,
						'postid'  => 1,
						'contents'=> 7,
					),
				),
				'prosilver_3.1.6' => array(
					'last_search_page'         => '(&bull;\\s[^<\\s]+\\s<strong>(\\d+)</strong>[^<]*<strong>\\1</strong>)',
					'post_contents'            => '(<div id="p(\d+)"(?:(?!<div id="p(?:\d+)").)*<div\\sclass="content">(.*)</div>\\s*(<dl\\sclass="attachbox">(?:.*<dl\\sclass="(?:file|thumbnail)">.*</dl>)+\\s*</dd>\\s*</dl>\\s*)?(?:<div\\s+class="notice">.*</div>\\s*)?(?:<div\\s+[^>]*class="signature">(.*)</div>\\s*)?</div>\\s*</div>\\s*<div\\sclass="back2top">)Us',
					'attachments'              => '(<dl\\sclass="file">\\s*(?:<dt><span[^<]*</span>\\s*<a\\s[^>]*href="([^"]*)"[^>]*>([^<]*)</a>|<dt[^>]*><img\\s[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>)</dt>\\s*<dd>(?:<em>((?:(?!</em>).)*)</em>|(?:(?!<em>).)*)</dd>)Us',
					'attachments_order'        => array('comment' => 5, 'file_url' => 1, 'file_name' => 2, 'img_url' => 3, 'img_name' => 4),
					'post_contents_ext'        => '(<div\\s+class="postbody">.*<h3[^>]*><a\\s*href="#p(\\d+)">([^<]*)</a></h3>.*<p\\s+class="author"><a\\s*href="[^"]*">.*<strong>(<a[^>]*>)?([^><]*)(</a>)?</strong>\\s*&raquo;\\s*</span>([^<]*)</p>\\s*<div\\s+class="content">(.*)</div>)Us',
					'post_contents_ext_order'  => array(
						'author'  => 4,
						'title'   => 2,
						'ts'      => 6,
						'postid'  => 1,
						'contents'=> 7,
					),
					'topic'                    => '(<div\\sid="page-body"[^>]*>.*<h2[^>]*><a\\s+href="[^"]*">([^<]*)</a></h2>)s',
				),
				'prosilver.1' => array(
					'sid'                      => '/name="sid" value="([^"]*)"/',
					'board_title'              => '#<h1>(.*)</h1>#',
					'login_success'            => '/<div class="panel" id="message">/',
					'login_required'           => '/class="panel"/',
					'user_name'                => '#<dl class="left-box details"[^>]*>\\s*<dt>[^<]*</dt>\\s*<dd>\\s*<span>([^<]+)</span>#Us',
					'thread_author'            => '#<p class="author">.*memberlist\\.php.*>(.+)<#Us',
					'search_results_not_found' => '#<div class="panel" id="message">\\s*<div class="inner"><span class="corners-top"><span></span></span>\\s*<h2>#Us',
					# N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
					'search_results_page_data' => '#<h3>[^>]*>([^<]*)</a>.*<dl class="postprofile">(?:(?!</dl>).)*<dd>('.get_posted_on_date_regex().' )?([^<]+)</dd>.*<dd>[^:]*: .*>(.+)</a>.*<dd>[^:]*: .*>(.+)</a>.*viewtopic\.php\?f=(\d+?)&amp;t=(\d+?)&amp;p=(\d+?)#Us',
					'search_results_page_data_order' => array('title' => 1, 'ts' => 4, 'forum' => 5, 'topic' => 6, 'forumid' => 7, 'topicid' => 8, 'postid' => 9),
					'post_contents'            => '#<div id="p(\d+)"(?:(?!<div id="p(?:\d+)").)*<div\\sclass="content">((?:(?!<dl\\sclass="attachbox">)(?!<div\\sclass="back2top">).)*)</div>\\s*(<dl\\sclass="attachbox">(?:.*<dl\\sclass="file">.*</dl>)+\\s*</dd>\\s*</dl>)?\\s*</div>\\s*<dl\\sclass="postprofile"#Us',
					'prev_page'                => '#<strong>\\d+</strong>[^<]+<strong>\\d+</strong>.*<a href="\\./viewtopic\\.php\?f=(\\d+)&amp;t=(\\d+)&amp;start=(\\d+?)[^"]*">\\d+</a><span class="page-sep">, </span><strong>\\d+</strong>#Us',
					'next_page'                => '#<strong>\\d+</strong><span class="page-sep">, </span><a href="\\./viewtopic\\.php\\?f=(\\d+)&amp;t=(\\d+)&amp;start=(\\d+?)[^"]*">[^<]*</a>#Us',
					'attachments'              => '(<dl\\sclass="file">\\s*(?:<dt><img\\s[^>]*>\\s*<a\\s[^>]*href="([^"]*)"[^>]*>([^<]*)</a>|<dt[^>]*><img\\s[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>)</dt>\\s*<dd>(?:<em>((?:(?!</em>).)*)</em>|(?:(?!<em>).)*)</dd>)Us',
					'attachments_order'        => array('comment' => 5, 'file_url' => 1, 'file_name' => 2, 'img_url' => 3, 'img_name' => 4),
					'post_contents_ext'        => '(<div\\s+class="postbody">.*<h3[^>]*><a\\s*href="#p(\\d+)">([^<]*)</a></h3>\\s*<p\\s+class="author"><a\\s*href="[^"]*"><img\\s*[^>]*></a>[^<]*<strong>(<a[^>]*>)?([^><]*)(</a>)?</strong>\\s*&raquo;\\s*([^<]*)</p>\\s*<div\\s+class="content">(.*)</div>\\s*(<div class="notice">(.*)</div>)?\\s*(<div\\s+id="[^"]+"\\s+class="signature">.*</div>)?\\s*</div>\\s*<dl\\s*class="postprofile")Us',
					'post_contents_ext_order'  => array(
						'author'  => 4,
						'title'   => 2,
						'ts'      => 6,
						'postid'  => 1,
						'contents'=> 7,
					),
					'forum_page_topicids'      => '(\\s+href="\\./viewtopic\\.php\\?f=\\d+&amp;t=(\\d+)"\\s+class="topictitle">)',
					'forum_title'              => '(<h2><a\\s*[^>]+>([^<]+)</a></h2>)',
					'last_topic_page'          => '(<div class="pagination">[^&]*&bull;\\s*(<a[^>]*>)?[^<]+<strong>(\\d+)</strong>[^<]*<strong>\\2</strong>)Us',
					'topic'                    => '(<h2><a\\s+href="\\./viewtopic\\.php\\?f=\\d+&amp;t=\\d+">([^<]*)</a></h2>)',
				),
				'prosilver.2' => array(
					'login_success'            => '(<li class="icon-logout"><a href="\\./ucp\\.php\\?mode=logout)', # Sometimes boards are set up to redirect to the index page, in which case the above won't work and this will
					'search_results_page_data' => '#<dl class="postprofile">.*<dd[^>]*>([^<]+)</dd>.*<dd>[^:]*: .*>(.+)</a>.*<dd>[^:]*: .*>(.+)</a>.*<h3>.*viewtopic\.php\?f=(\d+?)&amp;t=(\d+?)&amp;p=(\d+?)[^>]*>([^<]*)</a>#Us',
					'search_results_page_data_order' => array('title' => 7, 'ts' => 1, 'forum' => 2, 'topic' => 3, 'forumid' => 4, 'topicid' => 5, 'postid' => 6),
				),
				'subsilver2_3.1.6' => array(
					'login_success'            => '(<a href="\\./ucp\\.php\\?mode=logout)',
					'user_name'                => '(<td align="center"><b class="gen" style="color: [^"]*">([^<]*)</b>)',
					'last_search_page'         => '(<span class="nav">[^<]*<strong>(\\d+)</strong>[^<]*<strong>\\1</strong></span>)',
					'post_contents'            => '(<a name="p(\\d+?)[^"]*"(?:(?!<div class="postbody">).)*<div class="postbody">(.*)</div>\\s*<br clear="all"(?:(?!<a name="p(?:\\d+?)[^"]*")(?!<table [^>]*class="tablebg"[^>]*>\\s*<tr>\\s*<td[^>]*><b class="genmed">).)*?(<table [^>]*class="tablebg"[^>]*>\\s*<tr>\\s*<td[^>]*><b class="genmed">.*\\s*</table>)?+)Us',
					'attachments'              => '((?:<span class="gensmall"><b>[^<]*</b>\\s*?((?:(?!</span>).)*)</span>|)(?:(?!<span class="gensmall">).)*(?:<a [^>]*href="([^"]*)"[^>]*>([^<]*)<|<img [^>]*src="([^"]*)"[^>]*alt="([^"]*)"))Us',
					'attachments_order'        => array('comment' => 1, 'file_url' => 2, 'file_name' => 3, 'img_url' => 4, 'img_name' => 5),
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
					'search_results_page_data'  => '#<tr class="row2">\\s*<td colspan="2" height="25"><p class="topictitle"><a name="p(\\d+)" id="p\\d+"[^>]*></a>&nbsp;[^:]*: <a href="\\./viewforum\\.php\\?f=(\\d+?)[^"]*">([^<]*)</a> &nbsp; [^:]*: <a href="\\./viewtopic\\.php\\?f=\\d+&amp;t=(\\d+?)[^"]*">([^<]+)</a>\\s*</p></td>\\s*</tr>\\s*<tr class="row1">\\s*<td width="150" align="center" valign="middle"><b class="postauthor"><a [^>]*>[^<]*</a></b></td>\\s*<td height="25">\\s*<table width="100%" cellspacing="0" cellpadding="0" border="0">\\s*<tr>\\s*<td class="gensmall">\\s*<div style="float: left;">\\s*&nbsp;<b>[^:]*:</b> <a href="[^"]*">([^<]*)</a>\\s*</div>\\s*<div style="float: right;"><b>[^:]*:</b>\\s(.*)&nbsp;</div>#Us',
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
					'thread_author'             => '#<b class="postauthor"[^>]*>(.+)</b>#Us',
					/* 'search_results_not_found' => ? (not constructed yet), */
					// N.B. Must not match any results matched by any other search_results_page_data regex - the results of all are combined!
					/* 'search_results_page_data' => ? (not constructed yet), */
					// N.B. Does not (yet) contain a match for attachments.
					'post_contents'             => '#<a name="p(\\d+)">.*<td valign="top">\\s*<table width="100%" cellspacing="5">\\s*<tr>\\s*<td>\\s*?(.*)(\\s*?<br /><br />\\s*<span class="gensmall">.*</span>|)\\n\\s*<br clear="all" /><br />#Us',
					'prev_page'                 => '#<a href="\\./viewtopic\\.php\\?f=(\\d+)&amp;t=(\\d+)&amp;start=(\\d+)">[^<]+</a>&nbsp;&nbsp;<a href="\\./viewtopic\\.php\\?f=\\d+&amp;t=\\d+[^"]*">\\d+</a><span class="page-sep">,#',
					/* 'next_page'                => ? (not constructed yet), */
				),
				'subsilver.2005' => array(
					'login_success'            => '#<a href="privmsg\\.php\\?folder=inbox"#',
					'user_name'                => '#alt="[^\\[]*\\[ (.*) \\]"#',
					'search_results_page_data' => '#<span class="topictitle">.*&nbsp;<a href="viewtopic\\.php\\?t=(\\d+?).*class="topictitle">([^<]*)</a></span></td>.*<span class="postdetails">[^:]*:&nbsp;<b><a href="viewforum\\.php\\?f=(\\d+?)[^>]*>([^<]*)</a></b>&nbsp; &nbsp;[^:]*: ([^&]*?)&nbsp;.*viewtopic\\.php\\?p=(\\d+?)[^>]*>([^<]*)</a></b></span>#Us',
					'search_results_page_data_order' => array('topicid' => 1, 'topic' => 2, 'forumid' => 3, 'forum' => 4, 'ts' => 5, 'postid' => 6, 'title' => 7),
					// N.B. Does not (yet) contain a match for attachments.
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
					// N.B. Does not (yet) contain a match for attachments.
					'post_contents'            => '#<tr>\\s*<td width="100%"><a href="viewtopic\\.php\\?p=(\\d+?)[^\\#]*\\#\\d+"><img[^>]*></a><span class="postdetails">[^<]*<span class="gen">&nbsp;</span>[^<]*</span></td>\\s*<td valign="top" nowrap="nowrap"><a href="posting\\.php\\?[^"]*"><img[^>]*></a>\\s*</td>\\s*</tr>\\s*<tr>\\s*<td colspan="2"><hr /></td>\\s*</tr>\\s*<tr>\\s*<td colspan="2"><span class="postbody">(.*)</span><span class="gensmall">(<br /><br />|)[^<]*</span></td>\\s*</tr>#Us',
					'thread_author'            => '#<b>(.*?)</b></span><br /><span class="postdetails">#',
					'prev_page'                => '#()<span class="gensmall"><b>.*?<a href="viewtopic\\.php\\?t=(\\d+?).*start=(\\d+?)[^>]*>[^<]*</a>, <b>#U',
					'next_page'                => '#()<span class="gensmall"><b>[^<]+<a href="viewtopic\\.php\\?t=(\\d+).*start=(\\d+)[^"]*">#',
				),
				'generic_new' => array(
					'form_token'               => '#<input type="hidden" name="form_token" value="([^"]*)"#',
					'creation_time'            => '#<input type="hidden" name="creation_time" value="([^"]*)"#',
				),
				'forexfactory' => array(
					'sid'                      => '#SSIONURL = \'?(s\=)(.*&)|(.*)\';#',
					'board_title'              => '#<title>(.*)</title>#',
				),
			);
		}
	}

	protected function check_do_login() {
		# We don't want any existing authentication token to mess with the process below. This does matter (tested).
		if ($this->was_chained) @unlink($this->cookie_filename);

		$creation_time = $form_token = $sid = '';

		# Do this first bit so that we set old_version if necessary regardless of whether or not the user supplied credentials.

		$url = $this->settings['base_url'].'/ucp.php?mode=login';
		# Discover the SID
		$this->set_url($url);
		$redirect = false;
		$html = $this->do_send($redirect, /*$quit_on_error*/false, $err);
		
		if ($err || $redirect) {
			$is_old = false;
			if (!$err && $redirect) {
				$empty_url = array(
					'scheme' => '',
					'host'   => '',
					'port'   => '',
					'user'   => '',
					'pass'   => '',
					'path'   => '',
					'query'  => '',
				);
				$u1 = array_merge($empty_url, parse_url($url));
				$u2 = array_merge($empty_url, parse_url($redirect));
				# Only count redirects which aren't simply diverting from http to https (or vice versa)
				# and/or to or from the www-prefixed version of the domain,
				# and/or from a path which begins with an extra "/" due to the user ignoring
				# the request to strip the trailing slash off the base forum URL when entering options.
				# Lazily not splitting this out into a separate function as it is used nowhere else.
				$is_old = !(
				  ($u1['scheme'] == $u2['scheme'] || $u1['scheme'].'s'  == $u2['scheme'] || $u1['scheme'] == $u2['scheme'].'s' )
				  &&
				  ($u1['host'  ] == $u2['host'  ] || 'www.'.$u1['host'] == $u2['host'  ] || $u1['host'  ] == 'www.'.$u2['host'])
				  &&
				   $u1['port'  ] == $u2['port'  ]
				  &&
				   $u1['user'  ] == $u2['user'  ]
				  &&
				   $u1['pass'  ] == $u2['pass'  ]
				  &&
				  ($u1['path'  ] == $u2['path'  ] || '/'.$u1['path'   ] == $u2['path'  ] || $u1['path'  ] == '/'.$u2['path'   ])
				  &&
				   $u1['query' ] == $u2['query' ]
				);
			} else	$is_old = true;
			if ($is_old) {
				# Earlier versions of phpBB need a different URL
				$this->old_version = true;
			}
		}
		if (!$is_old) {
			if ($this->skins_preg_match('form_token', $html, $matches)) {
				$form_token = $matches[1];
			}
			if ($this->skins_preg_match('creation_time', $html, $matches)) {
				$creation_time = $matches[1];
			}
			if ($form_token && $creation_time) {
				if ($this->dbg) $this->write_err('form_token: "'.$form_token.'"; creation_time: "'.$creation_time.'".');
			}
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
				'form_token' => $form_token,
				'creation_time' => $creation_time,
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
				$method = $this->was_chained ? 'exit_err_resumable' : 'exit_err';
				$this->$method('Login was unsuccessful (did not find success message). This could be due to a wrong username/password combination. The URL is <'.$this->last_url.'>', __FILE__, __METHOD__, __LINE__,  $html);
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
	protected function ts_raw_hook(&$ts_raw) {
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
.attachment {
	border-top: dotted gray 2px;
	margin-top: 7px;
	padding-top: 7px;
}
</style>';
	}

	protected function get_forum_page_url($id, $pg) {
		return $this->settings['base_url'].'/viewforum.php?f='.$id.'&start='.$pg;
	}

	static function get_forum_software_homepage_s() {
		return 'https://www.phpbb.com/';
	}

	protected function get_topic_page_url($forum_id, $topic_id, $topic_pg_counter) {
		return $this->settings['base_url'].'/viewtopic.php?f='.$forum_id.'&t='.$topic_id.'&start='.$topic_pg_counter;
	}

	static function get_msg_how_to_detect_forum_s() {
		return 'Typically, phpBB forums can be identified by the presence of the text "Powered by phpBB" in the footer of their forum pages. It is possible, however, that these footer texts have been removed by the administrator of the forum. In this case, the only way to know for sure is to contact your forum administrator.';
	}

	protected function get_post_contents__end_hook($forumid, $topicid, $postid, $postids, $html, &$found, $err, $count, &$ret) {
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
					list($root_rel_url_base, $path_rel_url_base, $current_protocol) = static::get_base_urls_s($this->last_url, $html);
					list($found, $count) = $this->get_post_contents_from_matches($matches__prev_posts, $postid, $topicid, $root_rel_url_base, $path_rel_url_base, $current_protocol, $this->last_url);
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
					list($root_rel_url_base, $path_rel_url_base, $current_protocol) = static::get_base_urls_s($this->last_url, $html);
					list($found, $count) = $this->get_post_contents_from_matches($matches__next_posts, $postid, $topicid, $root_rel_url_base, $path_rel_url_base, $current_protocol, $this->last_url);
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

	static function get_qanda_s() {
		$qanda = parent::get_qanda_s();
		$qanda['q_images_supported']['a'] .= ' Note that if you wish to scrape images which are attached to posts then you will need to also check "Scrape attachments" too. '.self::$partial_attach_support_warning;
		$qanda['q_attachments_supported']['a'] .= ' '.self::$partial_attach_support_warning;
		$qanda = array_merge($qanda, array(
			'q_relationship' => array(
				'q' => 'Does this script have any relationship with <a href="https://github.com/ProgVal/PHPBB-Extract">the PHPBB-Extract script on GitHub</a>?',
				'a' => 'No, they are separate projects.',
			),
		));
		$qanda_new = array(
			'q_how_know_phpbb' => array(
				'q' => 'How can I know if a forum is a phpBB forum?',
				'a' => self::get_msg_how_to_detect_forum_s(),
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
					'required'    => false,
					'one_of_required' => false,
				);
			}
		}

		$new_settings_arr['base_url']['default'] = 'http://www.theabsolute.net/phpBB';
		$new_settings_arr['base_url']['description'] .= ' This is the URL that appears in your browser\'s address bar when you access the forum, only with everything onwards from (and including) the filename of whichever script is being accessed (e.g. /index.php or /viewtopic.php) stripped off. The default URL provided is for the particular phpBB board known as "Genius Forums".';
		$new_settings_arr['extract_user_id']['description'] .= ' You can find a user\'s ID by hovering your cursor over a hyperlink to their name and taking note of the number that appears after "&amp;u=" in the URL in the browser\'s status bar.';
		$new_settings_arr['forum_ids']['description'] .= ' You can find a forum\'s ID by hovering your cursor over a forum hyperlink and taking note of the integer that appears after "&amp;f=" in the URL in the browser\'s status bar.';
		$new_settings_arr['login_user']['description'] = 'Set this to the username of the user whom you wish to log in as (it\'s fine to set it to the same value as Extract User Username above), or leave it blank if you do not wish FUPS to log in. Logging in is optional but if you log in then the timestamps associated with each post will be according to the timezone specified in that user\'s preferences, rather than the board default. Also, some boards require you to be logged in so that you can view posts. If you don\'t want to log in, then simply leave blank this setting and the next setting.';
		$new_settings_arr['download_attachments']['description'] .= ' '.self::$partial_attach_support_warning;

		return $new_settings_arr;
	}

	protected function get_topic_url($forumid, $topicid, $start = null) {
		return $this->settings['base_url'].'/viewtopic.php?f='.urlencode($forumid).'&t='.urlencode($topicid).($start === null ? '' : '&start='.urlencode($start));
	}

	protected function get_user_page_url() {
		return $this->settings['base_url'].'/memberlist.php?mode=viewprofile&u='.urlencode($this->settings['extract_user_id']);
	}

	public static function supports_feature_s($feature) {
		static $features = array(
			'login'       => true,
			'attachments' => true,
			'forums_dl'   => true
		);

		return isset($features[$feature]) ? $features[$feature] : parent::supports_feature_s($feature);
	}

	protected function validate_settings() {
		parent::validate_settings();

		$forum_ids1 = explode(',', $this->settings['forum_ids']);
		$forum_ids2 = array();
		foreach ($forum_ids1 as $id) {
			$tmp = trim($id);
			if ($tmp) {
				if (filter_var($tmp, FILTER_VALIDATE_INT) === false) {
					$this->exit_err('One of the values in the comma-separated list supplied for the Forum IDs setting, "'.$tmp.'", is not an integer, which it is required to be for phpBB boards.', __FILE__, __METHOD__, __LINE__);
				} else	$forum_ids2[] = $tmp;
			}
		}
		if ($forum_ids2) {
			$this->settings['forum_ids_arr'] = $forum_ids2;
			if ($this->dbg) $this->write_err('$this->settings[\'forum_ids_arr\'] == '.var_export($this->settings['forum_ids_arr'], true));
		}

		if ($this->settings['extract_user_id']) {
			if (filter_var($this->settings['extract_user_id'], FILTER_VALIDATE_INT) === false) {
				$this->exit_err('The value supplied for the "Extract User ID" setting, "'.$this->settings['extract_user_id'].'", is not an integer, which it is required to be for phpBB boards.', __FILE__, __METHOD__, __LINE__);
			}
		} else if (!$this->settings['forum_ids_arr']) {
				$this->exit_err('Neither the "Extract User ID" setting nor the "Forum IDs" setting were specified: at least one of these must be set.', __FILE__, __METHOD__, __LINE__);
		}
	}

	protected function get_topic_id_from_topic_url($url) {
		$needle = '&t=';
		$pos = strpos($url, $needle);
		$pos2 = strpos($url, '&', $pos+strlen($needle));
		if ($pos2 !== false) {
			$topicid = substr($url, $pos+strlen($needle), $pos2 - ($pos+strlen($needle)));
		} else	$topicid = substr($url, $pos+strlen($needle));

		return $topicid;
	}
}

?>
