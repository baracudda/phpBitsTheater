<?php
use BitsTheater\Scene;
/* @var $recite Scene */
/* @var $v Scene */
//NOTE: $v and $recite are interchangable (one is more readable, one is nice and short (v for variables! ... and functions)
$w = '';

//print('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n");
print('<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">');
print('<html xmlns="http://www.w3.org/1999/xhtml">'."\n");
print('<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n");
print('<title>BitsTheater</title>'."\n");

//jQuery
if ($v->getSiteMode() != $v::SITE_MODE_DEMO)
	print('<script src="//code.jquery.com/jquery-1.10.1.min.js"></script>'."\n");
else
	$v->loadScript('jquery/jquery-1.7.min.js');

//jQuery-ui
//$v->loadCSS('jquery-ui/jquery-ui.css');
//$v->loadScript('jquery-ui/jquery-ui-1.8.custom.min.js');

//apycom menu (needs to be after jQuery, else use the jquery sublib)
$v->loadCSS('apycom/menu.css');
//$v->loadScript('apycom/jquery.js'); //do not need if already using jQuery
$v->loadScript('apycom/menu.js');

//minification from http://www.jsmini.com/ using Basic and no jquery included.
$v->loadScript('com/blackmoonit/jBits/jbits_mini.js');
//  !-remove the below space and comment out the above line to debug un-minified JS code
/* * /
$v->loadScript('com/blackmoonit/jBits/BasicObj.js');
$v->loadScript('com/blackmoonit/jBits/AjaxDataUpdater.js');
/* end of jBits JS */

//minification from http://www.jsmini.com/ using Basic and no jquery included.
//$v->loadScript('webapp_mini.js',WEBAPP_JS_URL);
//  !-remove the below space and comment out the above line to debug un-minified JS code
/* * /
$v->loadScript('webapp.js',WEBAPP_JS_URL);
$v->loadScript('AnotherFile.js',WEBAPP_JS_URL);
/* end of webapp JS */

//Theme
$v->loadCSS('bits.css', BITS_RES.'/style');

print("</head>\n");
$w .= '<body>'."\n";
$w .= '<table id="container-header">'."\n";
$w .= '<tr>'."\n";

//logo
$w .= '<td class="logo">';
$w .= '<a href="'.$v->getSiteURL().'">';
$w .= '<img class="logo" title="logo" src="'.$v->getRes('header/imgsrc/site_logo.png').'">';
$w .= '</a>';
$w .= '</td>'."\n";

//title & subtitle
$w .= '<td>'."\n";
$w .= '<a href="'.$v->getSiteURL().'">';
$w .= '<span class="title">BitsTheater</span>';
if ($v->getSiteMode() == $v::SITE_MODE_DEMO) {
	$w .= ' <span class="title mode-demo">(demo)</span>';
}
$w .= '</a>'."<br />\n";
$w .= '<span class="subtitle">An ity-bity framework.</span>';
$w .= '</td>'."\n";

//login info
$w .= '<td class="auth-area">'."\n";
$w .= $recite->cueActor('Account','buildAuthArea');
$w .= '</td>'."\n";

$w .= '</tr>'."\n";
$w .= '</table>'."\n";

//menu
$w .= '<table id="container-menu">'."\n";
$w .= '<tr><td>'.$recite->cueActor('Menus','buildAppMenu').'</td></tr>'."\n";
$w .= '</table>'."\n";

print($w);
print('<div id="container-body">'."\n"); //this needs to be matched in the footer.php
