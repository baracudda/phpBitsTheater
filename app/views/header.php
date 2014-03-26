<?php
print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n";
print '<html xmlns="http://www.w3.org/1999/xhtml">'."\n";
print '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n";
print '<title>phpBitsTheater MicroFramework </title>'."\n";

//typical resource patterns
$aCssPattern = '<link rel="stylesheet" type="text/css" href="'.BITS_RES.'/style/%s">'."\n";
$aScriptPattern = '<script type="text/javascript" src="'.BITS_LIB.'/%s"></script>'."\n";

//Theme
printf($aCssPattern,'bits.css');

//jquery
print('<script src="//code.jquery.com/jquery-1.10.1.min.js"></script>'."\n");

//jquery-ui
//printf($aCssPattern,'jquery-ui/jquery-ui.css');
//printf($aScriptPattern,'jquery-ui/jquery-ui-1.8.custom.min.js');

//apycom menu (needs to be after jQuery, else use the jquery sublib)
printf($aCssPattern,'apycom/menu.css');
//printf($aScriptPattern,'apycom/jquery.js');
printf($aScriptPattern,'apycom/menu.js');

$jbitsScriptPattern = '<script type="text/javascript" src="'.BITS_LIB.'/com/blackmoonit/jBits/%s"></script>'."\n";
//minification from http://www.jsmini.com/ using Basic and no jquery included.
printf($jbitsScriptPattern,'jbits_mini.js');
//  !-remove the below space and comment out the above line to debug un-minified JS code
/* * /
printf($jbitsScriptPattern,'BasicObj.js');
printf($jbitsScriptPattern,'AjaxDataUpdater.js');
/* end of jBits JS */

$webappJsPattern = '<script type="text/javascript" src="'.WEBAPP_JS_URL.'/%s"></script>'."\n";
//minification from http://www.jsmini.com/ using Basic and no jquery included.
//printf($webappJsPattern,'webapp_mini.js');
//  !-remove the below space and comment out the above line to debug un-minified JS code
/* * /
 printf($webappJsPattern,'webapp.js');
printf($webappJsPattern,'AnotherFile.js');
/* end of webapp JS */


print "</head>\n";

print '<body>'."\n";
print '<table id="container-header" width="100%">'."\n";
print '  <tr valign="center">'."\n";
//logo
print '    <td width=80px>'."\n";
print '      <a href="'.BITS_URL.'">';
print '        <img height="72" width="72" title="logo" src="'.BITS_RES.'/images/site_logo.png" border="0">';
print '      </a>'."\n";
print '    </td>'."\n";
print '    <td>'."\n";
print '      <a href="'.BITS_URL.'">';
print '        <span class="title">phpBitsTheater</span>';
print '      </a>'."<br />\n";
print("      <em>An ity-bity framework.</em>");
print '    </td>'."\n";
//login info
print '    <td align="right">'."\n";
print $recite->cueActor('Account','buildAuthArea');
print '    </td>'."\n";
print '  </tr>'."\n";
print '</table>'."\n";

//menu
print '<table id="container-menu" width="100%" cellpadding="0" cellspacing="0" border="0">'."\n";
print '  <tr><td>'."\n";
print $recite->cueActor('Menus','buildAppMenu');
print '  </td></tr>'."\n";
print '</table>'."\n";

print '<div id="container-body">'."\n"; //this needs to be matched in the footer.php
