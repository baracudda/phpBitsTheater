<?php

if (!$recite->_director->isGuest()) {
	ob_start();
	ssi_logout();
	$theLink = ob_get_clean();
	$regexLink = '/<a\s[^>]*href=\"([^\"]*)\"[^>]*>(.*)<\/a>/siU';
	preg_match($regexLink,$theLink,$matches);
	//print('<pre>'.var_dump($matches).'</pre><br/>');
	print($matches[1]);
}	
