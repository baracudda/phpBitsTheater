<?php
namespace BitsTheater\actors;
use BitsTheater\Actor;
{//namespace begin

class Home extends Actor {
	const DEFAULT_ACTION = 'view';

	public function view() {
		//indicate what top menu we are currently in
		$this->scene->_director['current_menu_key'] = 'home';
	}
	
}//end class

}//end namespace

