<?php
use BitsTheater\scenes\Install as MyScene; /* @var $recite MyScene */ /* @var $v MyScene */
use com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$w .= '<h3>Database Connection Settings:</h3>'."<br />\n";
$w .= "<br />\n";
$w .= 'This site uses PHP\'s PDO database classes, please specify the DNS required to connect to the following databases.'."<br />\n";
$w .= "<br />\n";

$w .= '<table class="db-entry">';
$w .= '<tr>';
/* @var $theDbConnInfo BitsTheater\costumes\DbConnInfo */
foreach($v->db_conns as $theDbConnInfo) {
	$r = '<td align="center" style="border: 1px solid">';
	
	if (!empty($theDbConnInfo->dbConnOptions->ini_filename)) {
		$r .= '<strong>Database Connection: </strong>'.$theDbConnInfo->dbConnOptions->ini_filename.''."<br/>\n";
		$r .= "<br />\n";
	}

	$theFormIdPrefix = $theDbConnInfo->myDbConnName;
	$r .= '<span class="db-field-label">'.$v->getRes('install/label_dns_table_prefix').': </span>';
	$r .= Widgets::createTextBox($theFormIdPrefix.'_table_prefix',$theDbConnInfo->dbConnOptions->table_prefix,false,20)."<br/>\n";
	$r .= "<br />\n";
	$r .= "<hr />\n"; //horizontal line here
	if (empty($theDbConnInfo->dbConnOptions->dns_scheme)) {
		$r .= '<table class="db-entry">';
		$r .= '<tr class="rowh"><th>Pick one</th><th></th></tr>';
		
		$r .= '<tr class="'.$v->_rowClass.'"><td class="db-field-label">';
		$r .= '<label for="'.$theFormIdPrefix.'_dns_scheme_ini" class="radiolabel">'.$v->getRes('install/label_dns_scheme_ini').'</label>';
		$r .= '<input type="radio" name="'.$theFormIdPrefix.'_dns_scheme" id="'.$theFormIdPrefix.'_dns_scheme_ini" class="radiobutton" value="ini" checked />';
		$r .= '</td><td class="db-entry">';
		$r .= "<br/>\n";
		$r .= $v->getDnsWidgets($theDbConnInfo,$v);
		$r .= "<br/>\n";
		$r .= '</td></tr>';
		
		$r .= '<tr class="'.$v->_rowClass.'"><td class="db-field-label">';
		$r .= '<label for="'.$theFormIdPrefix.'_dns_scheme_alias" class="radiolabel">'.$v->getRes('install/label_dns_scheme_alias').'</label>';
		$r .= '<input type="radio" name="'.$theFormIdPrefix.'_dns_scheme" id="'.$theFormIdPrefix.'_dns_scheme_alias" class="radiobutton" value="alias" />';
		$r .= '</td><td class="db-entry">';
		$r .= "<br/>\n";
		$r .= $v->getDnsWidgets($theDbConnInfo,$v);
		$r .= "<br/>\n";
		$r .= '</td></tr>';
		
		$r .= '<tr class="'.$v->_rowClass.'"><td class="db-field-label">';
		$r .= '<label for="'.$theFormIdPrefix.'_dns_scheme_uri" class="radiolabel">'.$v->getRes('install/label_dns_scheme_uri').'</label>';
		$r .= '<input type="radio" name="'.$theFormIdPrefix.'_dns_scheme" id="'.$theFormIdPrefix.'_dns_scheme_uri" class="radiobutton" value="uri" />';
		$r .= '</td><td class="db-entry">';
		$r .= "<br/>\n";
		$r .= $v->getDnsWidgets($theDbConnInfo,$v);
		$r .= "<br/>\n";
		$r .= '</td></tr>';
		
		$r .= '<tr class="'.$v->_rowClass.'"><td class="db-field-label">';
		$r .= '<label for="'.$theFormIdPrefix.'_dns_scheme_custom" class="radiolabel">'.$v->getRes('install/label_dns_scheme_custom').'</label>';
		$r .= '<input type="radio" name="'.$theFormIdPrefix.'_dns_scheme" id="'.$theFormIdPrefix.'_dns_scheme_custom" class="radiobutton" value="custom" />';
		$r .= '</td><td class="db-entry">';
		$r .= "<br/>\n";
		$r .= $v->getDnsWidgets($theDbConnInfo,$v);
		$r .= "<br/>\n";
		$r .= '</td></tr>';
		
		$r .= '</tr></table>';
	} else {
		$r .= $v->getDnsWidgets($theDbConnInfo,$v);
	}
	
	$r .= '</td>';
	$w .= $r;
}//foreach
$w .= '</tr></table>';

$w .= "<br/>\n";
$w .= Widgets::createCheckBox('do_not_delete_failed_config').' Keep config file on failure'."<br />\n";
$w .= "<br/>\n";

$w .= $recite->continue_button;
//$w .= '</div>';

$theHtmlForm = Widgets::buildForm($recite->next_action)
	->setName($recite->form_name)
	->append($w)
	;
print($theHtmlForm);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
