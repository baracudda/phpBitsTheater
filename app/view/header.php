<?php
print '<html><head><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">'."\n";
print '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n";
print '<title>phpBitsTheater MicroFramework </title>'."\n";

//typical resource patterns
$aCssPattern = '<link rel="stylesheet" type="text/css" href="'.BITS_RES.'/style/%s">'."\n";
$aScriptPattern = '<script type="text/javascript" src="'.BITS_LIB.'/%s" language="javascript"></script>'."\n";

//Theme
printf($aCssPattern,'bits.css');

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

print '<body>'."\n";
print '<table id="container-header" width="100%">'."\n";
print '  <tr valign="center">'."\n";
//logo
print '    <td width=80px>'."\n";
print '      <a href="'.BITS_URL.'">';
print '        <img height="72" width="72" title="logo" src="'.BITS_RES.'/images/blackmoonit_logo.png" border="0">';
print '      </a>'."\n";
print '    </td>'."\n";
print '    <td>'."\n";
print '      <a href="'.BITS_URL.'">';
print '        <span class="title">phpBitsTheater</span>';
print '      </a>'."\n";
print '    </td>'."\n";
//login info
print '    <td align="right">'."\n";
print $recite->cueActor('Account','buildAuthArea');
print '    </td>'."\n";
print '  </tr>'."\n";
print '</table>'."\n";

//menu
print '<table id="container-header" width="100%" cellpadding="0" cellspacing="0" border="0">'."\n";
print '  <tr><td>'."\n";
print $recite->cueActor('Menus','buildAppMenu');
print '  </td></tr>'."\n";
print '</table>'."\n";

print '<div id="container-body">'."\n"; //this needs to be matched in the footer.php
