<?php
namespace BitsTheater\res;
use BitsTheater\res\BitsMenuInfo as BaseResources;
use BitsTheater\costumes\MenuItemResEntry;
{//begin namespace

class MenuInfo extends BaseResources
{
	//app-specific menu items
	public $menu_item_a;
	public $menu_item_b;
	public $menu_item_c;
	public $menu_item_x;
	
	//menus containing menu items
	public $menu_x;
	
	public function setup($aDirector) {
		parent::setup($aDirector);
		//strings that require concatination need to be defined during setup()
		
		$this->menu_item_a = MenuItemResEntry::makeEntry($aDirector,'a')
				->link(BITS_URL.'/actor_a')
				->filter('&method@isGuest=false')
				->label('Menu Item A')
		;
		$this->menu_item_b = MenuItemResEntry::makeEntry($aDirector,'b')
				->link(BITS_URL.'/actorB')
				->filter('&method@isGuest=false')
				->label('Menu Item B')
		;
		$this->menu_item_c = MenuItemResEntry::makeEntry($aDirector,'c')
				->link(BITS_URL.'/actorC/action1')
				->filter('&method@isGuest=false')
				//->filter('&right@my_namespace/my_right')
				//->gone(true)
				//->label('&res@my_namespace/menu_label_c')
				->label('Menu Item C')
		;
		$this->menu_item_x = MenuItemResEntry::makeEntry($aDirector,'x')
				->label('Menu Item X')
				->hasSubmenu(true)
		;
				
		//app menu defined here so that updates to main program will not affect derived menus
		$this->menu_item_home->icon(''); //do not want an icon on Home menu
		$this->menu_main = array( //no link defined means submenu is defined as $menu_%name%
				'home' => $this->menu_item_home,
				//'a' => $this->menu_item_a,
				//'x' => $this->menu_item_x,
				'account' => $this->menu_item_account,
				'admin' => $this->menu_item_admin,
		);
		
		$this->menu_x = array(
				'b' => $this->menu_item_b,
				'c' => $this->menu_item_c,
		);
		
	}
	
}//end class

}//end namespace
