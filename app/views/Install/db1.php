<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';
//$w .= '<div align="left">'."\n";
$w .= '<h3>Database Info:</h3><br />'."\n";
$w .= '<br />';
$w .= 'Table Prefix: '.Widgets::createTextBox('table_prefix',$recite->table_prefix)."<br/>\n";
$w .= '<br />'."\n";
$w .= "<hr />\n";
$w .= 'This site uses PHP\'s PDO database classes, please specify how to specify the DNS:<br />';
$w .= '<br />';

$w .= '<table class="data-entry">';
$w .= '<tr class="rowh"><th>Pick one</th><th></th></tr>';

$w .= '<tr class="'.$v->_rowClass.'"><td class="data-label">';
$w .= '<label for="dns_scheme1" class="radiolabel">Standard Credentials</label>';
$w .= '<input type="radio" name="dns_scheme" id="dns_scheme1" class="radiobutton" value="ini" checked />';
$w .= '</td><td class="data-entry">';
$w .= "<br/>\n";
$w .= 'Host: '.Widgets::createTextBox('dbhost',$recite->dbhost)."<br/>\n";
$w .= 'Database Type: '.Widgets::createDropDown('dbtype',$recite->db_types,$recite->dbtype)."<br/>\n";
$w .= 'Database Name: '.Widgets::createTextBox('dbname',$recite->dbname)."<br/>\n";
$w .= 'Username: '.Widgets::createTextBox('dbuser',$recite->dbuser)."<br/>\n";
$w .= 'Password: '.Widgets::createPassBox('dbpwrd',$recite->dbpwrd)."<br/>\n";
$w .= "<br/>\n";
$w .= '</td></tr>';

$w .= '<tr class="'.$v->_rowClass.'"><td class="data-label">';
$w .= '<label for="dns_scheme2" class="radiolabel">Alias defined in php.ini</label>';
$w .= '<input type="radio" name="dns_scheme" id="dns_scheme2" class="radiobutton" value="alias" />';
$w .= '</td><td class="data-entry">';
$w .= "<br/>\n";
$w .= 'Alias: '.Widgets::createTextBox('dns_alias',$recite->dns_alias)."<br/>\n";
$w .= "<br/>\n";
$w .= '</td></tr>';

$w .= '<tr class="'.$v->_rowClass.'"><td class="data-label">';
$w .= '<label for="dns_scheme3" class="radiolabel">Custom URI</label>';
$w .= '<input type="radio" name="dns_scheme" id="dns_scheme3" class="radiobutton" value="uri" />';
$w .= '</td><td class="data-entry">';
$w .= "<br/>\n";
$w .= 'URI: '.Widgets::createTextBox('dns_customuri',$recite->dns_customuri)."<br/>\n";
$w .= "<br/>\n";
$w .= '</td></tr>';

$w .= '</tr></table>';
$w .= "<br/>\n";
$w .= Widgets::createCheckBox('do_not_delete_failed_config').' Keep config file on failure'."<br />\n";
$w .= "<br/>\n";

$w .= $recite->continue_button;
//$w .= '</div>';

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,'',false);
print $form_html;

print(str_repeat('<br />',3));
$recite->includeMyFooter();
