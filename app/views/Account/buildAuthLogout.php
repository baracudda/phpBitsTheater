<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */

if (!$recite->getDirector()->isGuest()) {
	ob_start();
	ssi_logout();
	$theLink = ob_get_clean();
	$regexLink = '/<a\s[^>]*href=\"([^\"]*)\"[^>]*>(.*)<\/a>/siU';
	preg_match($regexLink,$theLink,$matches);
	//print('<pre>'.var_dump($matches).'</pre><br/>');
	print($matches[1]);
}	
