<?php
//lib autoloader first
require_once(BITS_LIB_PATH.'autoloader.php');
//app autoloader next most frequent & priority
require_once(BITS_PATH.'app'.DIRECTORY_SEPARATOR.'autoloader.php');
//res autoloader last
include_once(BITS_RES_PATH.'autoloader.php');

require_once(BITS_LIB_PATH.'router.php');

