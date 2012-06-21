<?php
namespace res;
use res\Resources;
use res\MenuInfoBase;
{//begin namespace

class MenuInfo extends MenuInfoBase {
	
	public function setup() {//strings that require concatination need to be defined during setup()
		parent::setup();
		
		//app menu defined here so that updates to main program will not affect derived menus
	}
		
}//end class

}//end namespace
