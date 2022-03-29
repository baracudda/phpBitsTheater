<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Strings;

function downloadFile($aFile) {
	if (file_exists($aFile)) {
		//if no headers are sent, send some
		if (!headers_sent()) {
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
			header('X-Content-Type-Options: nosniff'); //IE & Edge only
		}
		readfile($aFile);
	}
}

downloadFile($v->results);
