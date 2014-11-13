<?php

/* FUPS settings.
 *
 * The easiest way to get started is to copy settings.default.php to
 * settings.php and edit as required.
 *
 * The only setting that you *definitely* need to change for FUPS to work is the
 * first one.
 *
 * If you are using FUPS as a web app and you want admin notification emails to
 * work though, then you will also need to change at least the second and 
 * probably the third setting too.
 *
 * If you want web contact links to work, then you will also need to change the
 * fourth setting.
 *
 * All other settings are *probably* optional, but you *might* need to adjust
 * some of them in some circumstances. e.g. you might need to change
 * FUPS_CMDLINE_PHP_PATH to an absolute path.
 */

// Where to store any status, error, cookie, and (web) settings files associated
// with each FUPS process. Must be writable by your web server if running FUPS
// as a web app, or by the user you run FUPS as from the commandline.
// N.B. THIS DIRECTORY SHOULD NOT BE PUBLICLY ACCESSIBLE - it will potentially
// contain settings files which include forum passwords. i.e. It should NOT be
// under your web root.
define('FUPS_DATADIR'          , '/home/yourusername/fups-files/'   );

// The email address to send error notifications to when running FUPS as a
// web app.
define('FUPS_EMAIL_RECIPIENT'  , 'youremail@example.com'            );

// The email address from which error notifications appear to have been sent
// when running FUPS as a web app.
define('FUPS_EMAIL_SENDER'     , 'fups@example.com'                 );

// The URL to link the text "contact me" to whenever it occurs in the web app
// output. Can be anything that's valid as the value of the href attribute of an
// <a> tag. e.g. 'mailto:me@mydomain.com' works fine.
define('FUPS_CONTACT_URL'      , '/contact'                         );

// The path (can be, and might need to be, absolute) to your php executable when
// run from the commandline.
define('FUPS_CMDLINE_PHP_PATH' , 'php'                              );

// The maximum number of PHP processes to detect before letting the user know
// with an error that their FUPS process cannot run. This is useful when your
// web host limits you to a certain number of processes and you want to avoid
// either FUPS-based DOS attacks or simply unintentionally running out of
// process so that your site is inaccessible to non-FUPS visitors.
define('FUPS_MAX_PHP_PROCESSES',                                   7);

// The user-agent that is set by the scraper (based on cURL) when scraping
// forums.
$hostmsg = php_uname('n') ? 'host '.php_uname('n') : 'an unknown host';
define('FUPS_USER_AGENT'       , 'FUPS version '.FUPS_VERSION.' (running from '.$hostmsg.')' );

// The time in seconds after which to chain a new instance of PHP (to avoid
// timeouts due to PHP's maximum execution time setting, typically enforced by
// web hosts).
//
// -1 indicates to attempt to set this automatically based on the value of the
// max_execution_time PHP ini setting.
define('FUPS_CHAIN_DURATION'   ,                                  -1);

// The filesystem path to the directory in which to store output when running as
// a web app.
define('FUPS_OUTPUTDIR'        , __DIR__.'/output/'                 );

// As above but from the perspective of the browser (i.e. URL).
define('FUPS_OUTPUTDIR_WEB'    , dirname($_SERVER['SCRIPT_NAME']).'/output/');

// The filesystem-based root directory of this FUPS installation.
define('FUPS_INC_ROOT'         , __DIR__.'/'                        );

// How frequently in seconds to refresh the entire status page when AJAX is not
// available.
define('FUPS_META_REDIRECT_DELAY',                                30);

// Whether to show the changelog at the bottom of the main FUPS page
// (index.php). Iff this evaluates to true, then the changelog will be shown.
define('FUPS_SHOW_CHANGELOG'   ,                                true);

// The routine deletion policy to be displayed to the user as part of the
// general information displayed when the FUPS process ends successfully with
// output. Ideally, you will set up a cron job so that you can set this to the
// commented-out string.
define('ROUTINE_DELETION_POLICY', '' /*' If not manually deleted, FUPS session files will be deleted by a routine scheduled task, which runs once a day and deletes all files more than two days old.'*/);

// Any additional questions and answers to be shown at the bottom of every
// enter-options.php page.
$fups_extra_qanda = array(
/*
	'q_resources' => array(
		'q' => 'Are there any resource issues of which I should be aware?',
		'a' => 'Yes - because this site is hosted on a shared server, I am limited to a fixed and fairly small number of processes, and each run of FUPS requires two processes, one for the background process doing the scraping, and another for the status web page. For most users, too, the number of posts is significant and the process will run for some time. Please, then, limit yourself to one run of the script at a time, and if you change your mind about wanting to run the script after having clicked "Retrieve posts!", then please click the cancellation link.',
	),
*/
);

// The error message to display to the user when during the running of FUPS as
// a web app, an error occurs, the user elects to send you their email address
// for additional assistance, but the sending of the email fails.
function fups_notify_email_address_fail_msg($token) {
	return 'Apologies, an error occurred whilst trying to send the mail to notify me of your email address. Feel free to <a href="'.FUPS_CONTACT_URL.'">contact me</a> manually, quoting your FUPS run token of "'.$token.'".';
}

// The initial HTML to begin every FUPS web app page with. Feel free to
// customise this to match the rest of your site, but make sure that
// $head_extra, if non-empty, is echoed within <head> somewhere.
// This function should leave the HTML in a state that arbitrary content can be
// added i.e. it should include an opening <body> tag but not a closing </body>
// tag.
function fups_output_page_start($page, $title, $description, $head_extra = '') {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title><?php echo htmlspecialchars($title); ?></title>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta http-equiv="content-language" content="en" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="<?php echo htmlspecialchars($description); ?>" />
<style type="text/css">
.fups_listmin {
	margin-left: 0;
	padding-left: 0;
}

.fups_listmin ul {
	margin-left: 1em;
	padding-left: 0;
}

.fups_listmin li {
	list-style: none;
	font-size: small;
}

.fups_error { 
	border: solid 1px black;
	color: black;
	padding: 5px;
	background-color: red;
}

#fups_div_status {
	border: thin solid black;
	background-color: gray;
	padding: 5px;
}
</style>
<?php
	if ($head_extra) echo $head_extra;
?>
</head>

<body>
<?php
}

// The final HTML to end every FUPS page with. Feel free to customise this to
// match the rest of your site, but make sure that $script, if non-empty, is
// echoed somewhere before the closing </body> tag.
//
// This function should assume that the <body> tag is open, and thus should
// include a closing </body> and a closing </html> tag.
function fups_output_page_end($page, $script = '') {
	if ($script) echo $script;
?>
</body>
</html>
<?php
}

?>
