<?php
/*
 * Copyright (C) 2016 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BitsTheater\costumes ;
use BitsTheater\Model ;
{

/**
 * A BitsTheater costume can make use of this trait when it wants to bind itself
 * to a model.
 */
trait WornByModel
{
	/**
	 * The model instance to which this costume is bound.
	 * @var Model
	 */
	protected $model = null ;
	
	/**
	 * Static builder method to return an instance of the costume pre-bound to a
	 * model instance.
	 * @param Model $aModel the model instance to bind
	 * @return static Returns an instance of the costume
	 */
	public static function withModel( Model $aModel )
	{
		$theClassName = get_called_class() ;
		return (new $theClassName($aModel->director))->setModel($aModel) ;
	}
	
	/**
	 * Accessor.
	 * @return Model Returns the model object.
	 */
	public function getModel()
	{ return $this->model ; }
	
	/**
	 * Binds the costume instance to an instance of a model.
	 * @param Model $aModel the model to bind
	 * @return static Returns the updated costume
	 */
	public function setModel( Model $aModel )
	{ $this->model = $aModel ; return $this ; }
	
} // end trait WornByModel

} // end namespace BitsTheater\costumes