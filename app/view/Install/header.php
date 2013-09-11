<?php
print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n";
print '<html xmlns="http://www.w3.org/1999/xhtml">'."\n";
print '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'."\n";

//typical resource patterns
$aCssPattern = '<link rel="stylesheet" type="text/css" href="'.BITS_RES.'/style/%s">'."\n";
$aScriptPattern = '<script type="text/javascript" src="'.BITS_LIB.'/%s" language="javascript"></script> //'."\n";

//Theme
printf($aCssPattern,'bits.css');

print '</head>'."\n";

print '<body bgcolor="Silver">'."\n";

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
print '        <span class="title">phpBitsTheater MicroFramework Installation</span>';
print '      </a>'."\n";
print '    </td>'."\n";
print '  </tr>'."\n";
print '</table>'."\n";

print '<hr><br />'."\n";
print '<div id="container-body">'."\n"; //this needs to be matched in the footer.php

