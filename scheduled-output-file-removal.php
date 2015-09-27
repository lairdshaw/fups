<?php

require_once __DIR__.'/common.php';

$prefix = 'fups.output.';
$min_delete_age = FUPS_SCHEDULED_DELETION_MIN_AGE_IN_DAYS * 24 * 60 * 60; // in seconds
$excluded_dirs = array('.', '..');

if ($dh = opendir(FUPS_OUTPUTDIR)) {
	while (($file = readdir($dh)) !== false) {
		if (is_dir(FUPS_OUTPUTDIR.$file) && !in_array($file, $excluded_dirs) && time() - stat(FUPS_OUTPUTDIR.$file)['mtime'] > $min_delete_age) {
			delete_dir_older_than_files(FUPS_OUTPUTDIR.$file, $min_delete_age);
			rmdir(FUPS_OUTPUTDIR.$file);
		}
	}
	closedir($dh);
} else	fwrite(STDERR, 'Error: failed to open directory "'.FUPS_OUTPUTDIR."\".\n");

delete_dir_older_than_files(FUPS_DATADIR, $min_delete_age);

function delete_dir_older_than_files($dir, $min_delete_age) {
	if ($dh = opendir($dir)) {
		while (($file = readdir($dh)) !== false) {
			if (is_file($dir.'/'.$file) && time() - stat($dir.'/'.$file)['mtime'] > $min_delete_age) {
				unlink($dir.'/'.$file);
			}	
		}
	} else	fwrite(STDERR, 'Non-fatal error: failed to open directory "'.$dir."\".\n");
	closedir($dh);
}

?>
