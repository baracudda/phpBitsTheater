<?php
use com\blackmoonit\Strings;

function downloadFile($aFile, $aMimeType, $aDownloadAsFilename, $aDisposition) {
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
			// disposition / encoding on response body
			if (empty($aDisposition))
				$aDisposition = 'inline'; //W3C defaults to 'inline'
			// Setting $aDisposition to 'attachment' will indicate it should be downloaded
			if (empty($aDownloadAsFilename))
				$aDownloadAsFilename = basename($aFile);
			header("Content-Disposition: {$aDisposition};filename={$aDownloadAsFilename}");
			header('Content-Type: '.$aMimeType);
			header('Expires: 0');
			header('Content-Length: ' . filesize($aFile));
		}
		readfile($aFile);
	}
}

downloadFile($v->results, $v->mimetype, $v->output_filename, $v->content_disposition);
