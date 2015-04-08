<?php
use BitsTheater\scenes\Install as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
$w = '';

print('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n");
print('<html xmlns="http://www.w3.org/1999/xhtml">'."\n");
print('<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n");

//jQuery
$v->loadScript('jquery/jquery-1.7.min.js');
//minification from http://www.jsmini.com/ using Basic and no jquery included.
$v->loadScript('com/blackmoonit/jBits/jbits_mini.js');

//Theme
$v->loadCSS('bits.css', BITS_RES.'/style');

print('</head>'."\n");

$w .= '<body>'."\n";
$w .= '<table id="container-header">'."\n";
$w .= '<tr>'."\n";

//logo
$w .= '<td class="logo">';
$w .= '<a href="'.$v->getSiteURL().'">';
$w .= '<img class="logo" title="logo" src="'.$v->getRes('website/site_logo_src').'">';
$w .= '</a>';
$w .= '</td>'."\n";

//title
$w .= '<td>'."\n";
$w .= '<a href="'.$v->getSiteURL().'">';
$w .= '<span class="title">Website Installation</span>';
$w .= '</a>'."\n";
$w .= '</td>'."\n";

$w .= '</tr>'."\n";
$w .= '</table>'."\n";

//menu
$w .= '<table id="container-menu">'."\n";
$w .= '<tr><td><br /></td></tr>'."\n";
$w .= '</table>'."\n";
$w .= '<br />';

print($w);
print('<div id="container-body">'."\n"); //this needs to be matched in the footer.php
