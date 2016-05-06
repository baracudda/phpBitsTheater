<?php
use com\blackmoonit\Strings;

function downloadFile($aFile, $aMimeType) {
	if (file_exists($aFile)) {
		//if no headers are sent, send some
		if (!headers_sent()) {
			if (empty($aMimeType)) {
				if (Strings::endsWith($aFile,'.apk')) {
					$aMimeType = 'application/vnd.android.package-archive';
				} else {
					$aMimeType = 'application/octet-stream';
				}
			}
			header('Content-Type: '.$aMimeType);
			header('Expires: 0');
			header('Content-Length: ' . filesize($aFile));
		}
		readfile($aFile);
	}
}

downloadFile($v->results, $v->mimetype);
