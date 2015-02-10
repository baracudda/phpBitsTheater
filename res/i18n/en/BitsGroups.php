<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Resources as BaseResources;
{

class BitsGroups extends BaseResources {

	//note, this could have been a static function that loaded it's array from a file and returned it
	public $group_names = array(
			0=>'unregistered visitor',
			1=>'titan',  //super admin
			2=>'admin',
			3=>'privileged',
			4=>'restricted',
	);

}//end class

}//end namespace
