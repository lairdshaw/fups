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

/* File       : notify-email-address.php.
 * Description: Attempts, at the user's request, to send a notification email
 *              to the FUPS email address configured as FUPS_EMAIL_RECIPIENT
 *              in settings.php, letting the recipient know that the user
 *              would like further assistance with a FUPS error.
 *              Part of the web app functionality of FUPS.
 */

require_once __DIR__.'/common.php';

$err = false;
if (empty($_POST['token']) || empty($_POST['email_address'])) {
	$err = 'Fatal error: one or both of the URL parameters "token" and "email_address" were not set.';
}
if (!$err) {
	$email_addr = $_POST['email_address'];
	if (!filter_var($email_addr, FILTER_VALIDATE_EMAIL)) {
		$err = "Fatal error: the email address you supplied, <$email_addr>, is not valid.";
	}
	if (!$err) {
		$msg = 'The contact email address for the FUPS process "'.$_POST['token'].'" is <'.$_POST['email_address'].'>.'."\n\n".'Any message the user included follows:'."\n\n".$_POST['message'];
		$headers = 'From: '.FUPS_EMAIL_SENDER."\r\n".
		           'Reply-To: '.$email_addr;
		if (!mail(FUPS_EMAIL_RECIPIENT, 'Contact email address for FUPS process '.$_POST['token'], $msg, $headers)) {
			$err = fups_notify_email_address_fail_msg($_POST['token']);
		}
	}
}

$page = substr(__FILE__, strlen(FUPS_INC_ROOT));
fups_output_page_start($page, 'FUPS: notifying me of your contact email address', 'Notifying me of your contact email address so that I can let you know if/when I fix the error you experienced with the FUPS scraping script.');

if ($err) {
?>
			<div class="fups_error"><?php echo htmlspecialchars($err); ?></div>
<?php
} else {
?>
			<h2>FUPS: Email sent successfully</h2>
			
			<p>Thank you for sharing your email address with me. I will get back to you on this error and your message (if any) as soon as I can.</p>
<?php
}

fups_output_page_end($page);

?>
