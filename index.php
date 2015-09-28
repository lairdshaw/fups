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

/* File       : index.php.
 * Description: The web app entry point. Displays a choice of forum software
 *              and a brief description of FUPS.
 */

require_once __DIR__.'/common.php';
require_once __DIR__.'/classes/CFUPSBase.php';

global $fups_url_run;

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
	$enter_options_links_str .= '<a href="'.$fups_url_enter_options.'?forum_type='.$forums_lc_arr[$i].'">'.$forums_arr[$i].'</a>';
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
				<li>2015-09-29
					<ul>
						<li>Added support for rebasing img/anchor URLs: relative image and anchor URLs in posts are now converted into the correct absolute URLs, so images should now always display (assuming an internet connection) and links in posts should now always direct to the correct place.</li>
						<li>Fixed a bug: post counts were sometimes doubled on phpBB forums due to the similarity of the prosilver.1 and prosilver.2 'search_results_page_data' regexes. Combining these into a single regex fixed the problem.</li>
					</ul>
				</li>
				<li>2015-09-28
					<ul>
						<li>Added resumability functionality - now if a page retrieval times out, and the script exits, you can resume it from the point it left off (within two days).</li>
						<li>Fixed a bug: when appending a prefix to create a new output directory when the specified one (via the commandline) already existed, instead a new subdirectory named as the prefix was being created.</li>
						<li>Fixed a bug: prior non-fatal errors weren't being included in the admin emails for fatal errors.</li>
						<li>Fixed a bug: sometimes a preceding "on " interfered with the detection of post dates in phpBB search results.</li>
					</ul>
				</li>
				<li>2015-08-04
					<ul>
						<li>Added support for forum character sets other than UTF-8.</li>
						<li>Fixed a bug in a regular expression for detecting phpBB search results for the subSilver skin 2005 vintage.</li>
					</ul>
				</li>
				<li>2015-07-25
					<ul>
						<li>Added different download options, including various different sorting options for HTML output, as well as JSON, PHP and serialised PHP formats.</li>
						<li>Fixed a bug: sometimes phpBB search results weren't being detected due to a faulty regular expression ('search_results_not_found') for the newly-added "mobile" skin.</li>
					</ul>
				</li>
				<li>2015-07-22
					<ul>
						<li>Added support for an older version of the subsilver skin for phpBB.</li>
						<li>Fixed a bug: the older phpBB variant was not being detected when login credentials weren't supplied.</li>
						<li>Improved error and diagnostic output.</li>
					</ul>
				</li>
				<li>2015-07-06
					<ul>
						<li>Added support for the mobile skin for phpBB.</li>
						<li>Fixed a bug: the wrong URL was being constructed for next and previous pages on phpBB post pages (used when the post for an unknown reason isn't on the page it is supposed to be on).</li>
					</ul>
				</li>
				<li>2015-02-12
					<ul>
						<li>Fixed a bug: the "Extract User Username" setting for phpBB forums was being ignored.</li>
					</ul>
				</li>
				<li>2015-02-11
					<ul>
						<li>Fixed two bugs affecting phpBB forums: user name detection and post contents detection. The user name detection fix applies to certain non-English phpBB forums, in particular to German ones. The post contents detection fix applies to some setups which output HTML with lines ending in CRLF rather than in LF alone.</li>
					</ul>
				</li>
				<li>2015-02-04
					<ul>
						<li>Fixed several deficiencies in XenForo scraping (see Git log), which included adding a "Thread URL prefix" XenForo setting, and a generic "Non-US date format" setting.</li>
						<li>Made other small changes to the code, and updated messages and the documentation to no longer suggest the possibility that FUPS can scrape only a single XenForo forum, now that it has been tested on another (which revealed the deficiencies mentioned above, but no fundamental skin incompatibility).</li>
					</ul>
				</li>
				<li>2015-01-23
					<ul>
						<li>Made small improvements to admin error messaging and the README.</li>
					</ul>
				</li>
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