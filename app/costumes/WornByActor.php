<?php
namespace BitsTheater\costumes ;
use BitsTheater\Actor ;
{

/**
 * A BitsTheater costume can make use of this trait when it wants to bind itself
 * to an actor.
 */
trait WornByActor
{
	/** The actor instance to which this costume is bound. */
	protected $actor = null ;

	/**
	 * Static builder method to return an instance of the costume pre-bound to
	 * an actor instance.
	 * @param Actor $aActor the actor instance to bind
	 * @return \BitsTheater\costumes\ABitsCostume an instance of the costume
	 */
	public static function withActor( Actor $aActor )
	{
		$theClassName = get_called_class() ;
		return (new $theClassName($aActor->director))
			->setDirector($aActor->director)
			->setActor($aActor)
			;
	}

	/** Accessor. */
	public function getActor()
	{ return $this->actor ; }

	/**
	 * Binds the costume instance to an instance of an actor.
	 * @param Actor $aActor the actor to bind
	 * @return \BitsTheater\costumes\ABitsCostume the updated costume
	 */
	public function setActor( Actor $aActor )
	{
		$this->actor = $aActor ;
		$this->setDirector($aActor->director) ;
		return $this ;
	}

} // end trait WornByActor

} // end namespace BitsTheater\costumes