<?php
namespace BitsTheater\outtakes ;
use BitsTheater\BrokenLeg ;
{

/**
 * Provides exception templates for various file I/O exception scenarios.
 * @since BitsTheater [NEXT]
 */
class FileIOException extends BrokenLeg
{
	const ACT_COULD_NOT_CREATE_FILE = 'COULD_NOT_CREATE_FILE' ;
	const ERR_COULD_NOT_CREATE_FILE = self::HTTP_INTERNAL_SERVER_ERROR ;
	const MSG_COULD_NOT_CREATE_FILE = 'generic/errmsg_could_not_create_file' ;
	
	const ACT_COULD_NOT_READ_FILE = 'COULD_NOT_READ_FILE' ;
	const ERR_COULD_NOT_READ_FILE = self::HTTP_INTERNAL_SERVER_ERROR ;
	const MSG_COULD_NOT_READ_FILE = 'generic/errmsg_could_not_read_file' ;
	
	const ACT_COULD_NOT_WRITE_TO_FILE = 'COULD_NOT_WRITE_TO_FILE' ;
	const ERR_COULD_NOT_WRITE_TO_FILE = self::HTTP_INTERNAL_SERVER_ERROR ;
	const MSG_COULD_NOT_WRITE_TO_FILE = 'generic/errmsg_could_not_write_to_file' ;
	
	const ACT_COULD_NOT_OPEN_ZIP = 'COULD_NOT_OPEN_ZIP' ;
	const ERR_COULD_NOT_OPEN_ZIP = self::HTTP_INTERNAL_SERVER_ERROR ;
	const MSG_COULD_NOT_OPEN_ZIP = 'generic/errmsg_could_not_open_zip' ;
	
	const ACT_COULD_NOT_EXPAND_ZIP = 'COULD_NOT_EXPAND_ZIP' ;
	const ERR_COULD_NOT_EXPAND_ZIP = self::HTTP_INTERNAL_SERVER_ERROR ;
	const MSG_COULD_NOT_EXPAND_ZIP = 'generic/errmsg_could_not_expand_zip' ;
}
}