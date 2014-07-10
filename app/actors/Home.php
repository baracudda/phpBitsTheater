<?php
namespace BitsTheater\actors;
use BitsTheater\Actor;
use BitsTheater\Scene as MyScene;
	/* @var $v MyScene */
{//namespace begin

class Home extends Actor {
	const DEFAULT_ACTION = 'view';

	public function view() {
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('home');
	}
	
}//end class

}//end namespace

