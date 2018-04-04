<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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