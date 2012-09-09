<?php
namespace com\blackmoonit\bits_theater\res\en;
use com\blackmoonit\bits_theater\res\Resources;
{

class SetupDefaultData extends Resources {

	//install page defauts
	public $continue_button_text = '&gt;&gt; Continue';
	public $back_button_text = 'Back &lt;&lt;';

	//note, this could have been a static function that loaded it's array from a file and returned it
	public $group_names = array(
			1=>'titan',
			2=>'admin',
			3=>'privileged',
			4=>'restricted',
			5=>'dinner_guest',
	);

}//end class

}//end namespace
