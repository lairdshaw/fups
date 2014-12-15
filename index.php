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

/* File       : index.php.
 * Description: The web app entry point. Displays a choice of forum software
 *              and a brief description of FUPS.
 */

require_once __DIR__.'/common.php';
require_once __DIR__.'/classes/CFUPSBase.php';
$page = substr(__FILE__, strlen(FUPS_INC_ROOT));
$valid_forum_types = FUPSBase::get_valid_forum_types();
$forums_str = '';
$forums_str_linked = '';
$enter_options_links_str = '';
$how_identify_list_html = '';
$forums_arr = array_values($valid_forum_types);
$forums_lc_arr = array_keys($valid_forum_types);
for ($i = 0; $i < count($forums_arr); $i++) {
	if ($i > 0) {
		$enter_options_links_str .= ' &nbsp;&nbsp;&nbsp;&nbsp; ';
		if ($i < count($forums_arr) - 1) {
			$forums_str .= ', ';
			$forums_str_linked .= ', ';
		} else {
			$forums_str .= ' or ';
			$forums_str_linked .= ' or ';
		}
	}
	$forums_str .= $forums_arr[$i];
	require_once __DIR__.'/classes/C'.$forums_arr[$i].'.php';
	$forum_class = $forums_arr[$i].'FUPS';
	$forums_str_linked .= '<a href="'.$forum_class::get_forum_software_homepage().'">'.$forums_arr[$i].'</a>';
	$enter_options_links_str .= '<a href="enter-options.php?forum_type='.$forums_lc_arr[$i].'">'.$forums_arr[$i].'</a>';
	$how_identify_list_html .= '				<li><b>'.$forums_arr[$i].'</b>: '.$forum_class::get_msg_how_to_detect_forum().'</li>'."\n";
}
fups_output_page_start($page, 'FUPS: Forum user-post scraper', 'Scrape posts made under a particular username from a '.$forums_str.' forum.');
?>
			<h2>FUPS: Forum user-post scraper</h2>

			<br />
			<br />
			<br />

			<h3 style="text-align: center;">Select forum software <sup><a class="footnote" href="#footnote1">[1]</a></sup> to continue</h3>

			<p style="width: 100%; text-align: center;"><?php echo $enter_options_links_str; ?></p>

			<br />
			<br />
			<br />

			<h3>What is FUPS?</h3>

			<p>FUPS is a web app that "scrapes" (downloads) the posts of a specified user from a specified forum/board running either the <?php echo $forums_str_linked; ?> forum software. FUPS will download from your specified forum all of the posts made to that forum under a particular username - it does this by accessing the forum in the same way your web browser does when you browse the forum manually, only it does so automatically. It then sorts posts alphabetically by thread, and within each thread in ascending date+time order, and produces a table of contents for all threads the user was involved in, followed by the sorted posts themselves, with headings, and separated by horizontal lines. It returns this output as an HTML page, which you can then save to disk via your browser, e.g. in Firefox click the "File" menu option and under that click "Save Page As". You can then, if you like, open up this saved HTML file in your word processor and save it in any other format you desire, e.g. ODF, Microsoft Word.</p>

			<p>If you're asking, "How can I download all of my posts from a remote <?php echo $forums_str; ?> forum to my local hard drive?", then this might be the script for you.</p>

			<h3 id="footnote1">[1] How do I know whether my forum software is <?php echo $forums_str; ?>, or neither?</h3>

			<ul>
<?php echo $how_identify_list_html; ?>
			</ul>
<?php
if (defined('FUPS_SHOW_CHANGELOG') && FUPS_SHOW_CHANGELOG) {
?>
			<h3 id="changelog">Changelog</h3>

			<ul>
				<li>2014-11-14 - 2014-12-16
					<ul>
						<li>Fixed bugs and made small improvements (see Git log).</li>
					</ul>
				</li>
				<li>2014-11-13
					<ul>
						<li>Finalised the refactoring of the code into the object-oriented paradigm.</li>
						<li>Made some small changes for better viewing on mobile devices.</li>
					</ul>
				</li>
				<li>2014-06-24
					<ul>
						<li>Added support for the XenForo forum software.</li>
						<li>Renamed the project from phpBB-extract to FUPS, and redirected links from /phpBB-extract to /fups.</li>
					</ul>
				</li>
				<li>2014-05-30
					<ul>
						<li>Bugfix: posts without titles weren't being identified for the subsilver skin.</li>
						<li>Bugfix: post contents were sometimes being truncated for the subsilver skin.</li>
						<li>Enhancement: increased the odds that posts inexplicably missing from their page will be found by checking the next page as well as the previous page.</li>
						<li>Enhancement: added recognition of login for the subsilver skin.</li>
					</ul>
				</li>
			</ul>
<?php
}

fups_output_page_end($page);
?>