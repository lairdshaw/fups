<?php

require_once __DIR__.'/common.php';

$prefix = 'fups.output.';
$min_delete_age = FUPS_SCHEDULED_DELETION_MIN_AGE_IN_DAYS * 24 * 60 * 60; // in seconds

delete_files_in_dir_older_than_r(FUPS_DATADIR, $min_delete_age, false, array('.htaccess'));

/* $excluded_files applies to the top level only */
function delete_files_in_dir_older_than_r($dir, $min_delete_age, $delete_dir_too = false, $excluded_files = array()) {
	static $excluded_dirs = array('.', '..');

	$dir_is_empty = true;
	// Stat before making changes.
	$dir_m_time = stat($dir)['mtime'];

	if ($dh = opendir($dir)) {
		while (($file = readdir($dh)) !== false) {
			// Ignore . and ..
			if (in_array($file, $excluded_dirs)) continue;

			if (in_array($file, $excluded_files)) {
				$dir_is_empty = false;
				continue;
			}
			$filepath = $dir.'/'.$file;
			if (is_file($filepath)) {
				if (time() - stat($filepath)['mtime'] > $min_delete_age) {
					if (!unlink($filepath)) {
						fwrite(STDERR, 'Non-fatal error: failed to unlink file "'.$filepath."\"\n");
						$dir_is_empty = false;
					}
				} else	$dir_is_empty = false;
			} else if (is_dir($filepath) && !delete_files_in_dir_older_than_r($filepath, $min_delete_age, true)) {
				$dir_is_empty = false;
			}
		}
	} else	fwrite(STDERR, 'Non-fatal error: failed to open directory "'.$dir."\".\n");
	closedir($dh);

	if ($delete_dir_too && $dir_is_empty && time() - $dir_m_time > $min_delete_age) {
		if (!rmdir($dir)) {
			fwrite(STDERR, 'Non-fatal error: failed to remove directory "'.$dir."\"\n");
			$dir_is_empty = false;
		}
	}

	return $dir_is_empty;
}

?>
