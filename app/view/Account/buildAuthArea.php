<?php
if ($recite->_director->isGuest()) {
	ssi_login();
} else {
	//print $recite->_director->account_info['account_name'].'(';
	//You can show other stuff here.  Like ssi_welcome().  That will show a welcome message like.
	//Hey, username, you have 552 messages, 0 are new.
	ssi_welcome();
	print(" ");
	ssi_logout();
	//print ')';
}	
