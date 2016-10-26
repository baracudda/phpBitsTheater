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
{ // begin namespace

/**
 * A set of consts and methods useful when running under CLI mode.
 */
interface ITerminalOutput
{
	const CLI_NORMAL = '[0m';
	
	const CLI_BLACK = '[0;30m';
	const CLI_WHITE = '[1;37m';
	
	const CLI_RED = '[0;31m';
	const CLI_LIGHT_RED = '[1;31m';
	
	const CLI_GREEN = '[0;32m';
	const CLI_LIGHT_GREEN = '[1;32m';
	
	const CLI_BLUE = '[0;34m';
	const CLI_LIGHT_BLUE = '[1;34m';
	
	const CLI_CYAN = '[0;36m';
	const CLI_LIGHT_CYAN = '[1;36m';
	
	const CLI_BROWN = '[0;33m';
	const CLI_YELLOW = '[1;33m';
	const CLI_MAGENTA = '[1;35m';
	
	const CLI_BOLD = '[1m';
	const CLI_UNDERSCORE = '[4m';
	const CLI_REVERSE = '[7m';
	
	/**
	 * Determine if we are executing in CLI mode or not.
	 * @return boolean
	 */
	function isRunningUnderCLI();

	/**
	 * Return string used to start the CLI effect.
	 * @param string $aCliEffect - one of the CLI_* consts.
	 * @return string Returns the CLI effect string.
	 */
	function startCliEffect($aCliEffect) ;
	
	/**
	 * Return string used to end any CLI effect.
	 * @return string Returns the CLI normal effect string.
	 */
	function endCliEffect() ;
	
	/**
	 * Wrap the string with codes needed to give the terminal window the
	 * desired effect.
	 * @param string $aStr - the string to wrap.
	 * @param string $aCliEffect - one of the CLI_* consts.
	 * @return string Returns the wrapped string.
	 */
	function strWithCliEffect($aStr, $aCliEffect);
		
} // end interface

} // end namespace
