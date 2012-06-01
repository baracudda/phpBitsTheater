<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

$w = '<div align="left">'."\n";
if ($recite->_dbError) {
	$w .= $recite->_dbError;
	$w .= $recite->old_vals;
} else {
	$w .= 'All done!'."<br/>\n";
	$w .= $v->continue_button;
}
$w .= '</div>';

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,'',false);
print $form_html;

$recite->includeMyFooter();
