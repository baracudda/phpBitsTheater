<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
/* @var $aExtraHeaderHtml string */ //This is the parameter sent into Scene->includeMyHeader()
//NOTE: $v and $recite are interchangable (one is more readable, one is nice and short (v for variables! ... and functions)
use com\blackmoonit\Strings ;

$w = '';

//print('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n");
print('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">');
print('<html xmlns="http://www.w3.org/1999/xhtml">'."\n");
print('<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n");
print('<title>'.$v->getRes('website/header_meta_title').'</title>'."\n");

//Theme
$theCssList = $v->getRes('website/css_load_list');
if (!empty($theCssList)) {
	foreach ($theCssList as $theCssFile => $theCssPath) {
		$v->loadCSS($theCssFile,$theCssPath);
	}//foreach
} else {
	$v->loadCSS('bits.css', BITS_RES.'/style');
}

$theLibsJsList = $v->getRes('website/js_libs_load_list');
if (!empty($theLibsJsList)) {
	foreach ($theLibsJsList as $theJsFile => $theJsPath) {
		if (is_int($theJsFile))
			$v->loadScript($theJsPath);
		else
			$v->loadScript($theJsFile,$theJsPath);
	}//foreach
} else {
	$v->loadScript('com/blackmoonit/jBits/jbits_mini.js');
}

$theJsList = $v->getRes('website/js_load_list');
if (!empty($theJsList)) {
	foreach ($theJsList as $theJsFile => $theJsPath) {
		$v->loadScript($theJsFile,$theJsPath);
	}//foreach
}

print($v->cueActor('Fragments', 'get', 'header-favicon'));
print($v->cueActor('Fragments', 'get', 'header-extras'));
if ( !empty($aExtraHeaderHtml) ) {
	print( $aExtraHeaderHtml . PHP_EOL ) ;
}
print( '</head>' . PHP_EOL ) ;

$w = '<body>' . Strings::eol(2) ;
//=============================================================================
$w .= '<table id="container-header">' . PHP_EOL
   .  ' <tr>' . PHP_EOL
//logo
   .  '  <td class="logo">'
   .     '<a href="' . $v->getSiteURL() . '">'
   .      '<img class="logo" title="logo" '
   .       'src="' . $v->getRes('website/site_logo_src') . '">'
   .     '</a>'
   .    '</td>' . PHP_EOL
//title & subtitle
   .  '  <td>' . PHP_EOL
   .  '   <a href="' . $v->getSiteURL() . '">'
   .      '<span class="title">' . $v->getRes('website/header_title')
   .      '</span>'
   ;
if( $v->getSiteMode() == $v::SITE_MODE_DEMO )
{
	$w .= '   <span class="title mode-demo">'
	   .  $v->getRes( 'generic/label_header_title_suffix_demo_mode' )
	   .  '</span>'
	   ;
}
$w .=    '</a><br/>' . PHP_EOL
   .     '<span class="subtitle">'
   .       $v->getRes( 'website/header_subtitle' )
   .     '</span>' . PHP_EOL
   .  '  </td>' . PHP_EOL
   ;
//login info
$w .= '  <td class="auth-area">' . PHP_EOL
   ;
if( $v->form_name !== 'register_user' )
{
	$w .= $recite->cueActor( 'Account', 'buildAuthArea' ) ;
}
$w .= PHP_EOL
   .  '  </td>' . PHP_EOL
   ;
$w .= ' </tr>' . PHP_EOL;
$w .= '</table>' . PHP_EOL;

print($w) ;

$w = '' ;
//=============================================================================

//menu
$w .= '<table id="container-menu">' . PHP_EOL
   .  ' <tr>' . PHP_EOL
   .  '  <td>' . Strings::eol(2)
   . $recite->cueActor('Menus','buildAppMenu') . PHP_EOL
   .  '  </td>' . PHP_EOL
   .  ' </tr>' . PHP_EOL
   .  '</table>' . PHP_EOL
   ;

print($w) ;

// Close this tag in footer.php!
print( '<div id="container-body">' . Strings::eol(2) ) ;
