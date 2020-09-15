#!/usr/bin/php
<?php
use BitsTheater\Regisseur;
use BitsTheater\models\AmazonS3 as AmazonS3DB;
use BitsTheater\costumes\AmazonS3Item;

/**
 * CLI options should be defined by the special function `process_cli_options($aStageManger)`
 * @param Regisseur $aStageManager
 * @return string[] Returns the CLI arguments.
 * @link http://www.php.net/manual/en/function.getopt.php
 * @see \BitsTheater\Regisseur::processOptionsForCLI()
 */
function process_cli_options($aStageManager)
{
	//by the time this function is executed, Regisseur class will have been loaded & usable.
	return $aStageManager->processOptionsForCLI(Regisseur::DEFAULT_CLI_SHORT_OPTIONS
			. 'f::bc::', array(
			'list-files::',
			'list-buckets',
			'create-bucket::',
			'upload::',
			'upload-to::',
			'upload-as::',
			'upload-dir::',
	));
}

//standard means of setting up the environment to access the website via CLI
//  the processed CLI options returned by the process_cli_options() are returned as well.
$theCliOptions = require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');

function printFileList( $aList )
{
	foreach ($aList as $anS3Object)
	{
		/* @var $anS3Object AmazonS3Item */
		print($anS3Object->LastModified);
		print(' ');
		print($anS3Object->Key);
		print(' ');
		print($anS3Object->getSemanticSize());
		print(PHP_EOL);
	}
}

global $director;
/* @var $dbS3 AmazonS3DB */
$dbS3 = $director->getProp(AmazonS3DB::MODEL_NAME);

if ($dbS3->isConnected())
{
	try
	{
		switch (true) {
		case ( !empty($theCliOptions['list-files']) || !empty($theCliOptions['f']) ):
		{
			$theBucketName = $dbS3->getS3Bucket();
			print('Using bucket [' . $theBucketName . ']' . PHP_EOL);
			$theKeyPath = $theCliOptions['list-files'] || $theCliOptions['f'];
			print('Listing files for key path [' . $theKeyPath . ']' . PHP_EOL);
			printFileList( $dbS3->getFileList($theKeyPath) );
		} break;
		case ( !empty($theCliOptions['list-buckets']) || !empty($theCliOptions['b']) ):
		default:
		{
			$theList = $dbS3->getBucketListOfNames();
			foreach ($theList as $theBucketName)
			{
				print($theBucketName . PHP_EOL);
			}
			
		} break;
		case ( !empty($theCliOptions['create-bucket']) || !empty($theCliOptions['c']) ):
		{
			$theBucketName = $theCliOptions['create-bucket'] || $theCliOptions['c'];
			$dbS3->createBucket( $theBucketName );
			print('Created [' . $theBucketName . ']' . PHP_EOL);
		} break;
		case ( !empty($theCliOptions['upload']) ):
		{
			$theThingToUpload = $theCliOptions['upload'];
			if ( !empty($theCliOptions['upload-as']) )
			{ $theKeyPath = $theCliOptions['upload-as']; }
			else
			{ $theKeyPath = $dbS3->localNameAsKeyPath($theThingToUpload, $theCliOptions['upload-to']); }
			$dbS3->uploadFileAsKey( $theThingToUpload, $theKeyPath );
			print('Listing files for key path [' . $theKeyPath . ']' . PHP_EOL);
			printFileList( $dbS3->getFileList($theKeyPath) );
		} break;
		case ( !empty($theCliOptions['upload-dir']) ):
		{
			$theThingToUpload = $theCliOptions['upload'];
			if ( !empty($theCliOptions['upload-as']) )
			{ $theKeyPath = $theCliOptions['upload-as']; }
			else
			{ $theKeyPath = $dbS3->localNameAsKeyPath($theThingToUpload, $theCliOptions['upload-to']); }
			$dbS3->uploadFolderAsKey( $theThingToUpload, $theKeyPath );
			$thePathToList = dirname($theKeyPath);
			print('Listing files for key path [' . $thePathToList . ']' . PHP_EOL);
			printFileList( $dbS3->getFileList($thePathToList) );
		} break;
		}//end switch
	}
	catch (\Exception $x) {
		print($x->getMessage() . PHP_EOL);
	}
}
else
{
	print('Could not connect to S3' . PHP_EOL);
}
print(PHP_EOL);
