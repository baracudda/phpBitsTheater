<?php
namespace BitsTheater\costumes ;
use BitsTheater\Model ;
{

/**
 * A BitsTheater costume can make use of this trait when it wants to bind itself
 * to a model.
 */
trait WornByModel
{
	/** The model instance to which this costume is bound. */
	protected $model = null ;
	
	/**
	 * Static builder method to return an instance of the costume pre-bound to a
	 * model instance.
	 * @param Model $aModel the model instance to bind
	 * @return \BitsTheater\costumes\ABitsCostume an instance of the costume
	 */
	public static function withModel( Model $aModel )
	{
		$theClassName = get_called_class() ;
		return (new $theClassName($aModel->director))->setModel($aModel) ;
	}
	
	/** Accessor. */
	public function getModel()
	{ return $this->model ; }
	
	/**
	 * Binds the costume instance to an instance of a model.
	 * @param Model $aModel the model to bind
	 * @return \BitsTheater\costumes\ABitsCostume the updated costume
	 */
	public function setModel( Model $aModel )
	{ $this->model = $aModel ; return $this ; }
	
} // end trait WornByModel

} // end namespace BitsTheater\costumes