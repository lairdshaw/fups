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

/* File       : run.php.
 * Description: Starts a background fups.php process based on the options
 *              entered by the user on the previous page (enter-options.php)
 *              and displays a status page to the user, auto-updating via
 *              AJAX if supported, otherwise refreshing the entire page
 *              at an interval configured by FUPS_META_REDIRECT_DELAY in
 *              settings.php.
 *              Part of the web app functionality of FUPS.
 */

require_once __DIR__.'/common.php';
$err = false;
$file_errs = '';
$ajax = isset($_GET['ajax']) && $_GET['ajax'] == 'yes';
if (!isset($_GET['token'])) {
	if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
		exec('ps -e | grep php | wc -l', $output, $res);
		$num_php_processes = $output[0];
		if ($num_php_processes > FUPS_MAX_PHP_PROCESSES) {
			$err = 'Apologies, my web hosting account is currently running short of allowable tasks; please wait a little and then try again. Feel free to <a href="'.FUPS_CONTACT_URL.'">contact me</a> if this situation persists over an extended period. Thank you!';
		}
	}
	if (!$err) {
		$num_settings_set = 0;
		foreach (array_keys($_POST) as $setting) {
			if ($_POST[$setting]) $num_settings_set++;
		}
		if ($num_settings_set == 0) {
			$err = 'You do not seem to have entered any values on the settings form. Please click "Back" and try again.';
		} else {
			$fp = false;
			for ($i = 0; $i < FUPS_MAX_TOKEN_ATTEMPTS; $i++) {
				$token = md5(rand(0, 1000000000));
				$settings_filename = make_settings_filename($token);
				$fp = @fopen($settings_filename, "x");
				if ($fp !== false) break;
			}

			if ($fp === false) {
				$err = 'Apologies, the server encountered a technical error: it was unable to generate a unique token to be associated with your request after '.FUPS_MAX_TOKEN_ATTEMPTS.' attempts. You might like to try again or <a href="'.FUPS_CONTACT_URL.'">contact me</a> about this error.';
			} else {
				foreach (array_keys($_POST) as $setting) {
					$value = $_POST[$setting];
					fwrite($fp, $setting.'='.$value.PHP_EOL);
				}
				fclose($fp);
				$status_filename = make_status_filename($token);
				$errs_filename = make_errs_filename($token);
				$serialize_filename = make_serialize_filename($token);
				if (file_put_contents($status_filename   , 'Starting up.') === false) {
					if ($file_errs) $file_errs .= ' ';
					$file_errs .= 'Error: unable to write to the status file.';
				}
				if (file_put_contents($errs_filename     , '') === false) {
					if ($file_errs) $file_errs .= ' ';
					$file_errs .= 'Error: unable to write to the error file.';
				}
				if (file_put_contents($serialize_filename, '') === false) {
					if ($file_errs) $file_errs .= ' ';
					$file_errs .= 'Error: unable to write to the serialization file.';
				}
				$cmd = make_php_exec_cmd(array('token' => $token));
				if (!try_run_bg_proc($cmd)) {
					$err = 'Apologies, the server encountered a technical error: it was unable to initiate the background process to perform the task of scraping, sorting and finally presenting your posts. The command used was:<br />'.PHP_EOL.'<br />'.PHP_EOL.$cmd.'<br />'.PHP_EOL.'<br />'.PHP_EOL.'You might like to try again or <a href="'.FUPS_CONTACT_URL.'">contact me</a> about this error.';
				}
			}
		}
	}
} else {
	$token = $_GET['token'];
	if (validate_token($token, $err)) {
		$status_filename     = make_status_filename    ($token);
		$errs_filename       = make_errs_filename      ($token);
		$errs_admin_filename = make_errs_admin_filename($token);

		if (isset($_GET['action']) && $_GET['action'] == 'resume') {
			$resumability_filename = make_resumability_filename($token);
			if (!file_exists($resumability_filename)) {
				$err = 'You have specified an action of "resume", however your task is not resumable (resumability file not found). This might be because you have already resumed it and it is currently running.';
			} else {
				$params = array('token' => $token, 'chained' => true, 'relogin' => true);
				if (isset($_GET['skip_topic']) && $_GET['skip_topic'] == 'yes') {
					$params['skip_topic'] = true;
				}
				$cmd = make_php_exec_cmd($params);
				if (!try_run_bg_proc($cmd)) {
					$err = 'Apologies, the server encountered a technical error: it was unable to resume the background process to perform the task of scraping, sorting and finally presenting your posts. The command used was:<br />'.PHP_EOL.'<br />'.PHP_EOL.$cmd.'<br />'.PHP_EOL.'<br />'.PHP_EOL.'You might like to <a href="'.make_resume_url_encoded($ajax, $token).'">try again or contact me about this error using the form below.'.PHP_EOL.PHP_EOL.make_error_contact_form($token);
				} else	unlink($resumability_filename);
			}
		}
	}
}
if (!$err) {
	$ts = @filemtime($status_filename);
	if ($ts === false) {
		$err = 'The status file for your FUPS process with token "'.$token.'" does not exist - possibly because you have already deleted it.';
	}
	$status     = @file_get_contents($status_filename);
	$errs       = @file_get_contents($errs_filename  );
	$errs_admin = @file_get_contents($errs_admin_filename);
}

$head_extra = '';

if (!$err) {
	global $fups_url_run, $fups_url_homepage;

	get_failed_done_cancelled($status, $done, $cancelled, $failed, $resumable_failure);

	if (!$ajax && ((!isset($_GET['last_status']) || $status != $_GET['last_status']) && !$done && !$failed && !$err)) {
		$head_extra = '<meta http-equiv="refresh" content="'.FUPS_META_REDIRECT_DELAY.'; URL='.$fups_url_run.'?token='.htmlspecialchars(urlencode($token)).'&amp;last_status='.htmlspecialchars(urlencode($status)).'" />';
	}
}

$page = substr(__FILE__, strlen(FUPS_INC_ROOT));
fups_output_page_start($page, 'FUPS progress', 'Monitor the progress of the scraping script.', $head_extra);
?>
			<ul class="fups_listmin">
				<li><a href="<?php echo $fups_url_homepage; ?>">&lt;&lt; Back to the FUPS homepage</a></li>
			</ul>

			<h2>FUPS progress</h2>
<?php
if ($file_errs) {
?>
			<div class="fups_error"><?php echo 'Apologies, however the following non-fatal error(s) occurred. These may affect updating and/or your final output: '.$file_errs; ?></div>
<?php
}
if ($err) {
?>
			<div class="fups_error"><?php echo $err; ?></div>
<?php
} else {
?>

			<script type="text/javascript">
			//<![CDATA[
			function toggle_ext_errs() {
				var elem = document.getElementById('id_ext_err');
				if (elem) {
					elem.style.display = (elem.style.display == 'none' ? 'block' : 'none');
				}
			}
			//]]>
			</script>

<?php
	if ($ajax && !$done && !$cancelled && !$failed) {
		global $fups_url_ajax_get_status;
?>
			<div id="ajax.fill">
<?php
		output_update_html($token, $status, $done, $cancelled, $failed, $resumable_failure, $err, $errs, $errs_admin, true);
?>
			</div>
			<script type="text/javascript">
				//<![CDATA[
				var xhr;
				var filesize = 0;
				var ts = <?php echo $ts; ?>;
				if (!xhr) try {
					if (window.XMLHttpRequest) {
						xhr = new XMLHttpRequest();
					} else if (window.ActiveXObject) {
						xhr = new ActiveXObject('Microsoft.XMLHTTP');
					}
				} catch (e) {
					xhr = null;
				}
				if (xhr) {
					function fups_xhr_state_change_function() {
						try {
							if (xhr.readyState == 4) {
								if (xhr.status == 200) {
									var p = xhr.responseText.indexOf("\n");
									filesize = xhr.responseText.substr(0, p);
									var p2 = xhr.responseText.indexOf("\n", p + 1);
									ts = xhr.responseText.substr(p + 1, p2);
									document.getElementById('ajax.fill').innerHTML = xhr.responseText.substr(p2 + 1);
									var len = xhr.responseText.length;
									var FUPS_FAILED_STR = '<?php echo FUPS_FAILED_STR; ?>';
									var FUPS_DONE_STR = '<?php echo FUPS_DONE_STR; ?>';
									var FUPS_CANCELLED_STR = '<?php echo FUPS_CANCELLED_STR; ?>';
									var FUPS_RESUMABLE_STR = '<?php echo FUPS_RESUMABLE_STR; ?>';
									var failed = (xhr.responseText.indexOf(FUPS_FAILED_STR) != -1);
									var done = (xhr.responseText.indexOf(FUPS_DONE_STR) != -1);
									var cancelled = (xhr.responseText.indexOf(FUPS_CANCELLED_STR) != -1);
									var resumable = (xhr.responseText.indexOf(FUPS_RESUMABLE_STR) != -1);

									if (!failed && !done && !cancelled && !resumable) {
										xhr.open('GET', base_url + '&filesize=' + filesize + '&ts=' + ts, true);
										xhr.onreadystatechange = fups_xhr_state_change_function;
										xhr.send(null);
									}
								}
							}
						} catch (e) { alert('Exception: ' + e); }
					}
					try {
						var base_url = '<?php echo $fups_url_ajax_get_status; ?>?token=<?php echo $token; ?>';

						xhr.open('GET', base_url + '&filesize=' + filesize + '&ts=' + ts, true);
						xhr.onreadystatechange = fups_xhr_state_change_function;
						xhr.send(null);
					} catch (e) { alert('Exception(2): ' + e); }
				}
				//]]>
			</script>
<?php
	} else	output_update_html($token, $status, $done, $cancelled, $failed, $resumable_failure, $err, $errs, $errs_admin);
}

fups_output_page_end($page);

?>
