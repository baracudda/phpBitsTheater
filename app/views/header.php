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
print('<title>'.$v->getRes('website/header_meta_title').'</title>'."\n");

//$v->loadCSS('jquery-ui/jquery-ui.css');
$v->loadCSS('apycom/menu.css');
$v->loadCSS('bootstrap/css/bootstrap.css');

//Theme
$theCssList = $v->getRes('website/css_load_list');
if (!empty($theCssList)) {
	foreach ($theCssList as $theCssFile => $theCssPath) {
		$v->loadCSS($theCssFile,$theCssPath);
	}//foreach
} else {
	$v->loadCSS('bits.css', BITS_RES.'/style');
}

//TODO: ======================================================================================
//TODO: figure out a mechanism so we can put all JS at bottom of the page, as is, need at top

//jQuery
if ($v->getSiteMode() != $v::SITE_MODE_DEMO) {
	//since we will be using the local jquery script tag inside JS, we need to escape '/' else get ILLEGAL char errors.
	$thejQueryLocalCopyScriptTag = str_replace('/', '\/', $v->getScriptTag('jquery/jquery.min.js'));
	print('<script src="//code.jquery.com/jquery-1.10.1.min.js"></script>'."\n");
	print("<script>if (!window.jQuery) document.write('{$thejQueryLocalCopyScriptTag}');</script>"."\n");
} else {
	$v->loadScript('jquery/jquery.min.js');
	//$v->loadScript('jquery/jquery-1.7.min.js');
}
//bootstrap needs to be after jQuery
$v->loadScript('bootstrap/js/bootstrap.min.js');

$v->loadScript('bootbox/bootbox.js');

//$v->loadScript('jquery-ui/jquery-ui.min.js');
//$v->loadScript('jquery-ui/jquery.ui.touch-punch.min.js');

//apycom menu (needs to be after jQuery, else use the jquery sublib)
//$v->loadScript('apycom/jquery.js'); //do not need if already using jQuery
$v->loadScript('apycom/menu.js');

$theJsList = $v->getRes('website/js_load_list');
if (!empty($theJsList)) {
	foreach ($theJsList as $theJsFile => $theJsPath) {
		$v->loadScript($theJsFile,$theJsPath);
	}//foreach
} else {
	$v->loadScript('com/blackmoonit/jBits/jbits_mini.js');
}
//TODO: ======================================================================================

print("</head>\n");
$w = '<body>'."\n";
//=============================================================================
$w .= '<table id="container-header">'."\n";
$w .= '<tr>'."\n";

//logo
$w .= '<td class="logo">';
$w .= '<a href="'.$v->getSiteURL().'">';
$w .= '<img class="logo" title="logo" src="'.$v->getRes('website/imgsrc/site_logo.png').'">';
$w .= '</a>';
$w .= '</td>'."\n";

//title & subtitle
$w .= '<td>'."\n";
$w .= '<a href="'.$v->getSiteURL().'">';
$w .= '<span class="title">'.$v->getRes('website/header_title').'</span>';
if ($v->getSiteMode() == $v::SITE_MODE_DEMO) {
	$w .= ' <span class="title mode-demo">'.$v->getRes('generic/label_header_title_suffix_demo_mode').'</span>';
}
$w .= '</a>'."<br />\n";
$w .= '<span class="subtitle">'.$v->getRes('website/header_subtitle').'</span>';
$w .= '</td>'."\n";

//login info
$w .= '<td class="auth-area">'."\n";
$w .= $recite->cueActor('Account','buildAuthArea');
$w .= '</td>'."\n";

$w .= '</tr>'."\n";
$w .= '</table>'."\n";

print($w);
$w = '';
//=============================================================================

//menu
$w .= '<table id="container-menu">'."\n";
$w .= '<tr><td>'.$recite->cueActor('Menus','buildAppMenu').'</td></tr>'."\n";
$w .= '</table>'."\n";

print($w);
print('<div id="container-body">'."\n"); //this needs to be matched in the footer.php
