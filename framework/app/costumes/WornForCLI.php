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
use BitsTheater\BrokenLeg ;
use BitsTheater\Director ;
use BitsTheater\Model ;
use com\blackmoonit\Strings ;
{ // begin namespace

/**
 * A set of methods useful when running under CLI mode.
 */
trait WornForCLI
{
	/**
	 * Determine if we are executing in CLI mode or not.
	 * @return boolean
	 */
	protected function isRunningUnderCLI()
	{
		return (php_sapi_name() === 'cli' OR defined('STDIN'));
	}

	/**
	 * Return string used to start the CLI effect.
	 * @param string $aCliEffect - one of the CLI_* consts.
	 * @return string Returns the CLI effect string.
	 * @see ITerminalOutput interface
	 */
	protected function startCliEffect($aCliEffect)
	{
		return chr(27) . $aCliEffect;
	}
	
	/**
	 * Return string used to end any CLI effect.
	 * @return string Returns the CLI normal effect string.
	 * @see ITerminalOutput interface
	 */
	protected function endCliEffect()
	{
		return chr(27) . self::CLI_NORMAL;
	}
	
	/**
	 * Wrap the string with codes needed to give the terminal window the
	 * desired effect.
	 * @param string $aStr - the string to wrap.
	 * @param string $aCliEffect - one of the CLI_* consts.
	 * @return string Returns the wrapped string.
	 * @see ITerminalOutput interface
	 */
	protected function strWithCliEffect($aStr, $aCliEffect)
	{
		return $this->startCliEffect($aCliEffect) . $aStr . $this->endCliEffect();
	}
	
	/**
	 * Writes a message to the appropriate output stream. If the costume is
	 * running under CLI, then the message will appear on standard output or
	 * standard error; otherwise, it will be logged to the PHP logs.
	 * @param string $aMessage the message to be logged
	 * @param boolean $isError indicates that the message represents an error
	 * @param boolean $isFatal indicates that the message represents a fatal
	 *  error which should kill the script (trumps $isError)
	 * @return the object, for fluid invocations
	 * @throws BrokenLeg if not running under CLI and the message is a fatal
	 *  error
	 */
	protected function writeLog( $aMessage, $isError=false, $isFatal=false )
	{
		if( $this->isRunningUnderCLI() )
		{
			if( $isFatal ) self::printErrorAndDie($aMessage) ;
			else if( $isError ) self::printError($aMessage) ;
			else print( $aMessage . PHP_EOL ) ;
		}
		else
		{
			if( $isError )
				Strings::errorLog( $aMessage ) ;
			else
				Strings::debugLog( $aMessage ) ;
			if( $isFatal )
				throw BrokenLeg::toss( $this, BrokenLeg::ACT_DEFAULT ) ;
		}
		return $this ;
	}
	
	/**
	 * Writes an error message to the standard error output stream on the
	 * console.
	 * @param string $aMessage
	 * @since BitsTheater 3.6
	 */
	public static function printError( $aMessage )
	{
		fwrite( STDERR, $aMessage . PHP_EOL ) ;
	}

	/**
	 * Writes an error message to the standard error output stream on the
	 * console, then dies.
	 * @param string $aMessage the error message to be displayed
	 * @since BitsTheater 3.6
	 */
	public static function printErrorAndDie( $aMessage )
	{
		self::printError($aMessage) ;
		die ;
	}

	/**
	 * Tries to get a DB model in the given context, and verify that it is
	 * successfully connected to the database.
	 * @param Director $aContext the context in which to construct the model
	 * @param string $aModelName the model name
	 * @return boolean|Model - the connected model, or `false` if not connected
	 * @since BitsTheater 3.6
	 */
	public static function getDatabaseModel( Director $aContext, $aModelName )
	{
		$theModel = $aContext->getProp( $aModelName ) ;
		if( ! $theModel->isConnected() )
			return false ;
		else
			return $theModel ;
	}

	/**
	 * As getDatabaseModel(), but dies with an error message if the model is not
	 * connected.
	 * @param Director $aContext the context in which to construct the model
	 * @param string $aModelName the model name
	 * @return Model - the connected model
	 * @since BitsTheater 3.6
	 */
	public static function requireDatabaseModel( Director $aContext, $aModelName )
	{
		$theModel = self::getDatabaseModel( $aContext, $aModelName ) ;
		if( $theModel === false )
		{
			self::printErrorAndDie( "[FATAL] Cannot connect to model ["
					. $aModelName . "]." ) ;
		}
		return $theModel ;
	}

} // end trait

} // end namespace
