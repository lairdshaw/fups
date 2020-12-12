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

/* File       : index.php.
 * Description: The web app entry point. Displays a choice of forum software
 *              and a brief description of FUPS.
 */

require_once __DIR__.'/common.php';
require_once __DIR__.'/classes/CFUPSBase.php';

global $fups_url_run;

$page = substr(__FILE__, strlen(FUPS_INC_ROOT));
$valid_forum_types = FUPSBase::get_valid_forum_types_s();
$forums_str = '';
$forums_str_linked = '';
$enter_options_links_str = '';
$how_identify_list_html = '';
for ($i = 0; $i < count($valid_forum_types); $i++) {
	if ($i > 0) {
		$enter_options_links_str .= ' &nbsp;&nbsp;&nbsp;&nbsp; ';
		if ($i < count($valid_forum_types) - 1) {
			$forums_str .= ', ';
			$forums_str_linked .= ', ';
		} else {
			$forums_str .= ' or ';
			$forums_str_linked .= ' or ';
		}
	}
	$forums_str .= $valid_forum_types[$i];
	require_once __DIR__.'/classes/C'.$valid_forum_types[$i].'.php';
	$forum_class = $valid_forum_types[$i].'FUPS';
	$forums_str_linked .= '<a href="'.$forum_class::get_forum_software_homepage_s().'">'.$valid_forum_types[$i].'</a>';
	$enter_options_links_str .= '<a href="'.$fups_url_enter_options.'?forum_type='.$valid_forum_types[$i].'">'.$valid_forum_types[$i].'</a>';
	$how_identify_list_html .= '				<li><b>'.$valid_forum_types[$i].'</b>: '.$forum_class::get_msg_how_to_detect_forum_s().'</li>'."\n";
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

			<p>FUPS is a web app that "scrapes" (downloads) from a specified board running either the <?php echo $forums_str_linked; ?> forum software either:</p>

			<ul>
				<li>All posts of a specified user, or,</li>
				<li>All posts in a (set of) (sub)forums of the board.</li>
			</ul>

			<p>FUPS will download from your specified board all of the relevant posts made to that board satisfying either of the above two conditions - it does this by accessing the forum in the same way your web browser does when you browse the forum manually, only it does so automatically.</p>

			<p>When scraping a user's posts, FUPS then sorts those posts by various means, and for each means, produces a file containing a table of contents for all threads the user was involved in, followed by the sorted posts themselves, with headings, and separated by horizontal lines. It returns an HTML page for each of these sorts. If images or files were downloaded, they are made available in the output too. FUPS also provides a JSON data structure for the scraped posts of the user.</p>

			<p>When scraping an entire (set of) (sub)forum(s), FUPS outputs the scraped data (threads and posts) in a JSON data structure.</p>

			<p>The output files are presented when FUPS finishes scraping, and you can then save these files to disk via your browser, e.g., in Firefox right-click on the file you want to save and choose "Save Link As...", or left-click on the file to open it and then click the "File" main menu option and then click "Save Page As". You can then, if you like, open up HTML files in your word processor and save them in any other format you desire, e.g. ODF, Microsoft Word.</p>

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
				<li>2020-12-12
					<ul>
						<li>Added an updated 'post_contents_ext' regular expression to support the scraping of forum threads for phpBB 3.3.2.</li>
						<li>Added support for new phpBB login form fields, namely 'form_token' and 'creation_time'.</li>
					</ul>
				</li>
				<li>2019-07-02
					<ul>
						<li>Improved XenForo support, by adjusting the regular expressions that detect posts in search listings, and by adding support for stripping a Vietnamese word from international datetimes.</li>
					</ul>
				</li>
				<li>2019-01-25
					<ul>
						<li>Improved code to detect the older phpBB version by not coming up with a false positive merely due to redirects diverting from http to https (or vice versa) and/or to or from the www-prefixed version of the domain, and/or from a path which begins with an extra "/" due to the user ignoring the request to strip the trailing slash off the base forum URL when entering options.</li>
					</ul>
				</li>
				<li>2018-11-11
					<ul>
						<li>Improved a couple of phpBB prosilver_3.1.6 regexes:
							<ul>
								<li>'post_contents_ext' to allow for detection of posts with attachment boxes (though this does not resolve the limitation by which attachments are not downloaded when scraping by forums, i.e., when filling in the "Forum IDs" setting via the web interface or when setting "forum_ids" via the commandline interface).</li>
								<li>'post_contents' to allow for attachments with a class of "file" on top of "thumbnail", and to allow for posts with signatures or that have been edited.</li>
							</ul>
						</li>
						<li>Removed the 'post_contents' regex added on 2018-10-25, which is not only unnecessary given these changes but incomplete and thus sometimes gives incorrect results.</li>
						<li>Reordered the phpBB prosilver regexes so that the most recent are topmost.</li>
					</ul>
				</li>
				<li>2018-11-03
					<ul>
						<li>Fixed GitHub <a href="https://github.com/lairdshaw/fups/issues/5">issue #5</a>: <em>end not detected</em>. Also added a few related regexes to support the prosilver skin on phpBB 3.2.x..</li>
						<li>Fixed GitHub <a href="https://github.com/lairdshaw/fups/issues/4">issue #4</a>: <em>regex issue with forum_page_topicids</em>.</li>
					</ul>
				</li>
				<li>2018-10-25
					<ul>
						<li>Added a phpBB prosilver thread page regex ('post_contents') which matches on some forums where none of the existing regexes did.</li>
					</ul>
				</li>
				<li>2018-09-16
					<ul>
						<li>Amended a phpBB prosilver search page regex to handle empty post subjects.</li>
						<li>Fixed detection of both "Forum IDs" and "Extract User ID" settings being empty for XenForo forums.</li>
					</ul>
				</li>
				<li>2018-06-02
					<ul>
						<li>Fixed error reporting when deleting files.</li>
						<li>Fixed a potential security hole by validating the token supplied by the user before deleting files in the output directory.</li>
						<li>Fixed a couple of small errors: a missing parameter to a call to the delete_files_in_dir_older_than_r() function and a misplaced call to closedir().</li>
					</ul>
				</li>
				<li>2018-03-05
					<ul>
						<li>Amended a regex to better match posts under the prosilver skin on phpBB 3.1.6 forums (it had been failing in some instances).</li>
					</ul>
				</li>
				<li>2018-02-17
					<ul>
						<li>Fixed GitHub <a href="https://github.com/lairdshaw/fups/issues/1">issue #1</a>: <em>Php error</em>.</li>
						<li>Improved support for downloading full forums from phpBB forums.</li>
					</ul>
				</li>
				<li>2017-08-21
					<ul>
						<li>Added support for scraping entire XenForo forums.</li>
						<li>Added support for the "Extract User Username" setting for XenForo forums.</li>
					</ul>
				</li>
				<li>2017-06-06
					<ul>
						<li>Added "skip current topic on resume" functionality.</li>
					</ul>
				</li>
				<li>2017-06-05
					<ul>
						<li>Added support (phpBB-only for now) for scraping entire forums.</li>
						<li>Prepended required fields on the options entry page with asterisks.</li>
					</ul>
				</li>
				<li>2016-07-03
					<ul>
						<li>Fixed a bug in the detection of the old version of phpBB.</li>
						<li>Fixed an image scraping bug.</li>
						<li>Improved detection of Romanian dates on some versions of phpBB.</li>
					</ul>
				</li>
				<li>2015-10-14
					<ul>
						<li>Added support for detecting successful login under the prosilver skin on phpBB boards that redirect to the index page after login.</li>
					</ul>
				</li>
				<li>2015-10-03
					<ul>
						<li>Fixed a bug: commandline chaining wasn't working due to 'output_filename' being used instead of 'output_dirname' in make_php_exec_cmd().</li>
						<li>Fixed a bug: empty posts caused an infinite loop.</li>
					</ul>
				</li>
				<li>2015-09-30
					<ul>
						<li>Added support for scraping images.</li>
						<li>Reworked the settings code and added a "Consecutive request delay (seconds)" setting.</li>
					</ul>
				</li>
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
