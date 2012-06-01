<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

$w = '<div align="left">'."\n";
$w .= '<h3>Database Info:</h3><br />'."\n";
$w .= '<br />';
$w .= 'Table Prefix: '.Widgets::createTextBox('table_prefix',$recite->table_prefix,true)."<br/>\n";
$w .= '<br />'."\n";
$w .= 'phpBitsTheater uses PHP\'s PDO database classes, please specify how to specify the DNS:<br />';
$w .= '<br />';

$theDnsSchemes = array(
		'alias' => 'php.ini Alias',
		'ini' => 'INI file',
		'uri' => 'URI',
);
$w .= Widgets::createRadioSet('dns_scheme',$theDnsSchemes,'ini').'<br />';

$w .= 'Alias/Uri: '.Widgets::createTextBox('dns_value',$recite->dns_value,true)."<br/>\n";
$w .= '<br />'."\n";

$w .= "<hr />\n";
$w .= '<br />'."\n";
$w .= "INI contents: <br/>\n";
$w .= '<br />'."\n";
$w .= 'Host: '.Widgets::createTextBox('dbhost',$recite->dbhost,true)."<br/>\n";
$w .= 'Database Type: '.Widgets::createDropDown('dbtype',$recite->db_types,$recite->dbtype)."<br/>\n";
$w .= 'Database Name: '.Widgets::createTextBox('dbname',$recite->dbname,true)."<br/>\n";
$w .= 'Username: '.Widgets::createTextBox('dbuser',$recite->dbuser,true)."<br/>\n";
$w .= 'Password: '.Widgets::createTextBox('dbpwrd',$recite->dbpwrd,true)."<br/>\n";
$w .= '<br />(you can edit the /app/config/_dbconn_.ini later if this info is insufficient)<br />'."\n";
$w .= $recite->continue_button;
$w .= '</div>';

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,'',false);
print $form_html;

$recite->includeMyFooter();
