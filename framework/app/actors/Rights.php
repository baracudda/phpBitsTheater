<?php
namespace BitsTheater\actors;
use BitsTheater\actors\Understudy\AuthGroups as BaseActor;
use BitsTheater\models\AuthGroups as MyModel;
use BitsTheater\scenes\Rights as MyScene;
{//namespace begin

class Rights extends BaseActor
{
	/**
	 * {@inheritDoc}
	 * @return MyScene Returns a newly created scene descendant.
	 * @see \BitsTheater\Actor::createMyScene()
	 */
	protected function createMyScene($anAction)
	{ return new MyScene($this, $anAction); }

	/**
	 * @return MyModel Returns the database model reference.
	 */
	protected function getMyModel()
	{ return $this->getProp(MyModel::MODEL_NAME); }
	
	
}//end class

}//end namespace

