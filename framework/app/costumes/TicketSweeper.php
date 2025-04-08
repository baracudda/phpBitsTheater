<?php
namespace BitsTheater\costumes ;
use BitsTheater\costumes\Wardrobe\TicketSweeper as BaseCostume ;
{//namespace begin

/**
 * A cron job will run the TicketSweeper every few minutes to clean up
 * stale tokens. It may also be used for other things that need to occur
 * every few minutes as well.
 */
class TicketSweeper extends BaseCostume
{
	
	//nothing to override, yet
	
}//end class

}//end namespace
