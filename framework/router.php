<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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

namespace BitsTheater;
{//begin namespace

global $director; //exposed as a Global Var so legacy systems can interface with us
$director = Director::requisition();
//passing in the ?url= (which .htaccess gives us) rather than $_SERVER['REQUEST_URI']
$director->routeRequest( REQUEST_URL ) ;

}//end namespace
