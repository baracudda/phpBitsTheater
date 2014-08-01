<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Resources as BaseResources;
{

class SetupDefaultData extends BaseResources {

	//install page defauts
	public $continue_button_text = '&gt;&gt; Continue';
	public $back_button_text = 'Back &lt;&lt;';

	//note, this could have been a static function that loaded it's array from a file and returned it
	public $group_names = array(
			1=>'titan',  //super admin
			2=>'admin',
			3=>'privileged',
			4=>'restricted',
			5=>'dinner_guest',
	);

}//end class

}//end namespace
