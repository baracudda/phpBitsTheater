<?php
print '<html><head><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">'."\n";
print '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n";
print '<title>phpBitsTheater MicroFramework </title>'."\n";

//typical resource patterns
$aCssPattern = '<link rel="stylesheet" type="text/css" href="'.BITS_RES.'/style/%s">'."\n";
$aScriptPattern = '<script type="text/javascript" src="'.BITS_LIB.'/%s" language="javascript"></script>'."\n";

//apycom menu
printf($aCssPattern,'apycom/menu.css');
printf($aScriptPattern,'apycom/jquery.js');
printf($aScriptPattern,'apycom/menu.js');

//jquery
//printf($aScriptPattern,'jquery/jquery-1.7.min.js');

//jquery-ui
//printf($aCssPattern,'jquery-ui/jquery-ui.css');
//printf($aScriptPattern,'jquery-ui/jquery-ui-1.8.custom.min.js');

print "</head>\n";

print "<body>\n";

print '<div align="center"><div id="bodyContainer">'."\n"; //these two need to be matched in the footer.php
print '<div style="text-align:left"><table width="100%">'."\n";
print '  <tr valign="top">'."\n";

//logo
print '    <td>'."\n";
print '      <a href="'.BITS_URL.'">';
print '      <div align="left"><img src="'.BITS_RES.'/images/blackmoonit_logo.png" border="0"></div>';
print '      </a>'."\n";
print '    </td>'."\n";

print '  </tr>'."\n";

print '  <tr>'."\n";
//login info
print '    <td>'."\n";
print '      <div align="left"><div style="font-size:12px; text-align:left; padding: 5px;">';
print $recite->cueActor('Account','buildAuthArea');
print "      </div></div>\n";
print '    </td>'."\n";
print '    <td></td>';
print '  </tr>'."\n";


print '</table></div>'."\n";
print "<br/>\n";

//menu
print '<table width="100%" cellpadding="0" cellspacing="0" border="0">'."\n";
print '  <tr><td>'."\n";
print $recite->cueActor('Menus','buildAppMenu');
print '  </td></tr>'."\n";
print '</table>'."\n";

print "<br/>\n";
