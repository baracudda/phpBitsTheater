<?php
use com\blackmoonit\Strings;

function downloadFile($aFile) {
	if (file_exists($aFile)) {
		header('Content-Description: File Transfer');
		if (Strings::endsWith($aFile,'.apk')) {
			header('Content-Type: application/vnd.android.package-archive');
		} else {
			header('Content-Type: application/octet-stream');
		}
		header('Content-Disposition: attachment; filename='.basename($aFile));
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($aFile));
		readfile($aFile);
	}
}

downloadFile($v->results);
