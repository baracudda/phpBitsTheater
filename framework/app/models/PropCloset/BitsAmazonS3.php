<?php
/*
 * Copyright (C) 2017 Blackmoon Info Tech Services
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

namespace BitsTheater\models\PropCloset;
use BitsTheater\models\PropCloset\ANonDbModel as BaseModel ;
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\AmazonS3Item;
use com\blackmoonit\Strings;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\CommandPool;
use Aws\CommandInterface;
use Aws\ResultInterface;
use GuzzleHttp\Stream\Stream as GuzzleStream;
use GuzzleHttp\Promise\PromiseInterface;
{//begin namespace

/**
 * Interfaces with Amazon's S3 accounts to store files.
 * @since BitsTheater [NEXT]
 */
class BitsAmazonS3 extends BaseModel
{
	protected $mConfigNamespaceForAmazonS3 = 'aws_s3';
	protected $mConfigNameForAPIKey = 'api_key';
	protected $mConfigNameForSecretKey = 'secret_key';
	protected $mConfigNameForRegion = 'region_name';
	protected $mConfigNameForBucket = 'bucket_name';
	protected $mConfigNameForRootPath = 'root_path';
	
	/**
	 * The Amazon S3 client.
	 * @var S3Client
	 */
	protected $mS3Client = null;
	/**
	 * Bucket to use when accessing S3.
	 * @var string
	 */
	protected $mBucketName = null;
	/**
	 * Path to use as a part of every S3 interaction.
	 * @var string
	 */
	protected $mPathPrefix = '';
	
	/**
	 * Return the config setting using our defined namespace.
	 * @param string $aConfigKeyName - one of the $mConfigNameFor* properties.
	 * @return string Returns the config setting value.
	 */
	protected function getSettingFor( $aConfigKeyName )
	{
		$theResult = trim($this->getConfigSetting(
				$this->mConfigNamespaceForAmazonS3 . '/' . $aConfigKeyName
		));
		if ( !empty($theResult) )
			return $theResult;
		else
			return null;
	}
	
	/**
	 * If the config settings are not fully defined, you may toss this
	 * generic exception if desired.
	 * @param string $aConfigNamespace - the namespace of the config settings.
	 * @throws BitsTheater\BrokenLeg
	 */
	public function tossWhenNotDefined( $aConfigNamespace )
	{
		$theCondition = strtoupper($aConfigNamespace) . '_NOT_DEFINED';
		throw BrokenLeg::pratfallRes($this, $theCondition, 412,
				'generic/errmsg_x_not_defined',
				$this->getRes('config/namespace')[$aConfigNamespace]->label
		);
	}
	
	/**
	 * Retrieve the settings and create the S3 client.
	 * @return S3Client
	 */
	protected function createClient()
	{
		$theAccountKey = $this->getSettingFor( $this->mConfigNameForAPIKey );
		$theSecretKey = $this->getSettingFor( $this->mConfigNameForSecretKey );
		$theRegionName = $this->getSettingFor( $this->mConfigNameForRegion );
		$this->mBucketName = $this->getSettingFor( $this->mConfigNameForBucket );
		$this->mPathPrefix = $this->getSettingFor( $this->mConfigNameForRootPath );
		
		$theOptions = array( 'version' => 'latest' );
		if ( empty($theRegionName) )
			$theRegionName = 'us_east_1'; //Amazon's default region
		if ( !empty($theAccountKey) && !empty($theSecretKey) && !empty($theRegionName) )
		{
			$theOptions['credentials'] = array(
					'key' => $theAccountKey,
					'secret' => $theSecretKey,
			);
			$theOptions['region'] = $theRegionName;
			return S3Client::factory( $theOptions );
		}
		return null;
	}
	
	/**
	 * Initializes the object based on the configuration settings set for the
	 * instance.
	 * {@inheritDoc}
	 * @see BaseModel::setupNonDbModel() (this overrides)
	 */
	public function setupNonDbModel()
	{
		$this->mS3Client = $this->createClient();
	}
	
	/**
	 * @return boolean Returns TRUE if the S3Client object was created successfully.
	 * {@inheritDoc}
	 * @see \BitsTheater\Model::isConnected()
	 */
	public function isConnected()
	{ return ( !empty($this->mS3Client) ) ; }
	
	/**
	 * @return S3Client|NULL Return the client object.
	 */
	public function getS3Client()
	{ return $this->mS3Client; }
	
	/**
	 * @return string|NULL Return the name of the bucket to use.
	 */
	public function getS3Bucket()
	{ return $this->mBucketName; }
	
	/**
	 * Get the file info from a particular bucket. If no bucket name is
	 * supplied, the one defined in Config Settings will be used.
	 * @param string $aKeyPath - key path inside the bucket to retrieve.
	 * @param string $aBucketName - (optional) bucket name to retrieve listing.
	 * @return AmazonS3Item Returns an AmazonS3Item object.
	 * @throws BrokenLeg::DB_CONNECTION_FAILED on failure to connect.
	 * @throws BrokenLeg::MISSING_VALUE on failure to find a bucket name to use.
	 */
	public function getFileInfo( $aKeyPath, $aBucketName=null )
	{
		if ( !$this->isConnected() )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_CONNECTION_FAILED ); }

		$theBucketName = (!empty($aBucketName)) ? $aBucketName : $this->getS3Bucket();
		if ( empty($theBucketName) )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_MISSING_VALUE, 'S3 Bucket' ); }

		$theResults = $this->getFileList( $aKeyPath, $theBucketName );
		//$this->logStuff(__METHOD__, ' r=', $theResults); //DEBUG
		return ( !empty($theResults) ) ? $theResults[0] : null;
	}
	
	
	public function getS3ObjStream( $aKeyPath, $aBucketName=null )
	{
		if ( !$this->isConnected() )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_CONNECTION_FAILED ); }
		
		$theBucketName = (!empty($aBucketName)) ? $aBucketName : $this->getS3Bucket();
		if ( empty($theBucketName) )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_MISSING_VALUE, 'S3 Bucket' ); }
		$theKeyToUse = trim($aKeyPath);
		
		$theResults = $this->getS3Client()->getObject( array(
				'Bucket'     => $theBucketName,
				'Key'        => $theKeyToUse,
		));
		if ( !empty($theResults) )
			return $theResults['Body'];
	}
	
	/**
	 * Get the file list in a particular bucket. If no bucket name is
	 * supplied, the one defined in Config Settings will be used.
	 * @param string $aKeyPath - (optional) key path inside the bucket to list.
	 * @param string $aBucketName - (optional) bucket name to retrieve listing.
	 * @return AmazonS3Item[]|NULL Returns an array of AmazonS3Item objects.
	 */
	public function getFileList( $aKeyPath='', $aBucketName=null )
	{
		if ( !$this->isConnected() )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_CONNECTION_FAILED ); }

		$theBucketName = (!empty($aBucketName)) ? $aBucketName : $this->getS3Bucket();
		if ( empty($theBucketName) )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_MISSING_VALUE, 'S3 Bucket' ); }
		//$this->logStuff(__METHOD__, ' req=', $aKeyPath, 'buk=', $theBucketName); //DEBUG
		
		/*
		(Guzzle\Service\Resource\Model)|O-1|{
		- Name -> "ryan7405-test-123"
		- Prefix -> ""
		- Marker -> ""
		- MaxKeys -> "1000"
		- IsTruncated -> false
		- Contents -> Array(1)|A-2|[
		- - 0 = Array(6)|A-3|[
		- - - Key = "image/12.13.16_fisch_rule_request.csv"
		- - - LastModified = "2017-05-01T21:17:41.000Z"
		- - - ETag = ""fa1cac3e284291db91bb10148110ced3""
		- - - Size = "11938"
		- - - Owner = Array(2)|A-4|[
		- - - - ID = "6fb4ee82dc554eac95450d5e782227b6b71d0caaab386f96ba154dc54f538691"
		- - - - DisplayName = "red.baracudda"
		- - - ]
		- - - StorageClass = "STANDARD"
		- - ]
		- ]
		- RequestId -> "A002E663A4DB34FF"
		}
		*/
		/* @var $theResults Guzzle\Service\Resource\Model */
		/*
		$theResults = $this->getS3Client()->listObjects(array(
				'Bucket' => $theBucketName,
				'Key' => $aKeyPath,
		))->get('Contents');
		*/
		
		$theResults = array();
		foreach ( $this->getS3Client()->getIterator('ListObjects', array(
		        "Bucket" => $theBucketName,
		        "Prefix" => $aKeyPath,
	    )) as $anS3item )
		{
			$theResults[] = AmazonS3Item::fromArray($anS3item);
		}
		return $theResults;
	}
	
	/**
	 * Create a bucket in our S3 account.
	 * @param strings $aBucketName - the bucket name to use.
	 * @return $this Returns $this for chaining.
	 * @see Strings::sanitizeFilename() for name limitations.
	 * @throws AwsException if S3 fails for some reason.
	 */
	public function createBucket( $aBucketName )
	{
		if ( !$this->isConnected() )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_CONNECTION_FAILED ); }
		
		$theBucketName = Strings::sanitizeFilename(trim($aBucketName));
		if ( !empty($theBucketName) )
		{
			try {
				$result = $this->getS3Client()->createBucket(array(
						'Bucket' => $theBucketName,
				));
			}
			catch (AwsException $x) {
				$this->errorLog( __METHOD__ . ' ' . $x->getMessage() );
				throw $x;
			}
		}
		return $this;
	}
	
	/**
	 * Get the list of bucket names.
	 * @return string[]|false Returns the list of bucket names or FALSE if not connected.
	 * @throws AwsException if S3 fails for some reason.
	 */
	public function getBucketListOfNames()
	{
		if ( !$this->isConnected() )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_CONNECTION_FAILED ); }
		
		$theBuckets = $this->getS3Client()->listBuckets();
		$theList = array();
		foreach ($theBuckets['Buckets'] as $theBucket) {
			$theList[] = $theBucket['Name'];
		}
		return $theList;
	}
	
	/**
	 * Convert a local file or folder name to be the last segment in an S3 key path.
	 * @param string $aName - name of the file or folder.
	 * @param string $aKeyPathToUse - base keypath to append $aName onto.
	 * @return string Returns the key path with base name of $aName.
	 */
	public function localNameAsKeyPath( $aName, $aKeyPathToUse )
	{
		$theKeyToUse = Strings::sanitizeFilename(trim($aKeyPathToUse));
		if ( !Strings::endsWith($theKeyToUse, '/') )
			$theKeyToUse .= '/';
		$theKeyToUse .= basename($aName);
		return $theKeyToUse;
	}
	
	/**
	 * Uploads a file to S3 using the key as the new filename.
	 * @param string $aFileToUpload - full path to the file.
	 * @param string $aKeyToUse - full destination path and name.
	 * @param string $aBucketName - (optional) specify another bucket to use.
	 * @return array Returns the S3 results.
	 * @throws AwsException if S3 fails for some reason.
	 */
	public function uploadFileStreamAsKey( $aFileStreamToUpload, $aKeyToUse, $aBucketName=null )
	{
		if ( !$this->isConnected() )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_CONNECTION_FAILED ); }
		
		$theKeyToUse = trim($aKeyToUse);
		$theBucketToUse = ( !empty($aBucketName) ) ? $aBucketName : $this->getS3Bucket();
		$theOptions = array(
				'Bucket' => $theBucketToUse,
				'Key' => $theKeyToUse,
				'Body' => new GuzzleStream($aFileStreamToUpload),
		);
		//$this->logStuff(__METHOD__, ' upload=', $theOptions); //DEBUG
		$theResult = $this->getS3Client()->putObject( $theOptions );
		//$this->logStuff(__METHOD__, ' ', $theResult); //DEBUG
		return $theResult;
	}
	
	/**
	 * Uploads a file to S3 using the key as the new filename.
	 * @param string $aFileToUpload - full path to the file.
	 * @param string $aKeyToUse - full destination path and name.
	 * @param string $aBucketName - (optional) specify another bucket to use.
	 * @return \Aws\Result Returns the result of the putObject operation.
	 * @throws AwsException if S3 fails for some reason.
	 */
	public function uploadFileAsKey( $aFileToUpload, $aKeyToUse, $aBucketName=null )
	{
		if ( !$this->isConnected() )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_CONNECTION_FAILED ); }
		
		$theFileToUpload = trim($aFileToUpload);
		if ( !file_exists($theFileToUpload) || is_dir($theFileToUpload) )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_FILE_NOT_FOUND, $theFileToUpload ); }
		
		$theKeyToUse = Strings::sanitizeFilename(trim($aKeyToUse));
		
		$theBucketToUse = ( !empty($aBucketName) ) ? $aBucketName : $this->getS3Bucket();
		
		$theResult = $this->getS3Client()->putObject( array(
				'Bucket'     => $theBucketToUse,
				'Key'        => $theKeyToUse,
				'SourceFile' => $theFileToUpload,
		));
		//$this->debugLog(__METHOD__ . ' ' . $this->debugStr($theResult) );
		/*(Aws\Result)|O-1|{
			Expiration -> "",
			ETag -> ""6eed59473949c1f4acf1c2dbdb99aacb"",
			ServerSideEncryption -> "",
			VersionId -> "",
			SSECustomerAlgorithm -> "",
			SSECustomerKeyMD5 -> "",
			SSEKMSKeyId -> "",
			RequestCharged -> "",
			@metadata -> Array(4)|A-2|[
				statusCode = (integer) 200,
				effectiveUri = "https://%bucketname%.s3.amazonaws.com/image/1/2/3/BitsTheater.png",
				headers = Array(6)|A-3|[
					x-amz-id-2 = "8i+cOWFbFmW/yfeGzMv7FsHYsV1dIyUveyYCi/rgIByOfhuDziMIvIIeCjwqYspEK+vujmUM30c=",
					x-amz-request-id = "0AC471BBD68557EE",
					date = "Wed, 15 Nov 2017 22:28:43 GMT",
					etag = ""6eed59473949c1f4acf1c2dbdb99aacb"",
					content-length = "1234",
					server = "AmazonS3",
				],
				transferStats = Array(1)|A-4|[
					http = Array(1)|A-5|[
						0 = Array(0)|A-6|[],
					],
				],
			],
			ObjectURL -> "https://%bucket%.s3.amazonaws.com/image/1/2/3/BitsTheater.png",
		}
		*/
		
		return $theResult;
	}
	
	/**
	 * Uploads a file to S3 maintaining the original file name.
	 * @param string $aFileToUpload - full path to the file.
	 * @param string $aKeyPathToUse - full destination path.
	 * @param string $aBucketName - (optional) specify another bucket to use.
	 * @return $this Returns $this for chaining.
	 * @throws AwsException if S3 fails for some reason.
	 */
	public function uploadFileToKey( $aFileToUpload, $aKeyPathToUse=null, $aBucketName=null)
	{
		$theKeyToUse = $this->localNameAsKeyPath( $aFileToUpload, $aKeyPathToUse );
		return $this->uploadFileAsKey( $aFileToUpload, $theKeyToUse, $aBucketName );
	}
	
	/**
	 * Uploads a folder to S3 using the key as the new folder name.
	 * <code><b>NOTE: SUBFOLDERS ARE NOT TRAVERSED!</b></code>
	 * @param string $aFolderToUpload - full path to the folder.
	 * @param string $aKeyToUse - full destination path and name.
	 * @param string $aBucketName - (optional) specify another bucket to use.
	 * @return PromiseInterface Returns the promise interface.
	 * @throws AwsException if S3 fails for some reason.
	 */
	public function uploadFolderAsKey( $aFolderToUpload, $aKeyToUse, $aBucketName=null )
	{
		if ( !$this->isConnected() )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_CONNECTION_FAILED ); }
		
		$theFolderToUpload = trim($aFolderToUpload);
		if ( !file_exists($theFolderToUpload) || !is_dir($theFolderToUpload) )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_FILE_NOT_FOUND, $theFolderToUpload ); }
		
		$theBaseKeyToUse = Strings::sanitizeFilename(trim($aKeyToUse));
		if ( !Strings::endsWith($theBaseKeyToUse, '/') )
			$theBaseKeyToUse .= '/';
		
		$theBucketToUse = ( !empty($aBucketName) ) ? $aBucketName : $this->getS3Bucket();

		// Create an iterator that yields files from a directory.
		$theFiles = new \DirectoryIterator($theFolderToUpload);
		
		// Create a generator that converts the SplFileInfo objects into
		// Aws\CommandInterface objects. This generator accepts the iterator that
		// yields files and the name of the bucket to upload the files to.
		$theS3Client = $this->getS3Client();
		$theCommandGenerator = function (\Iterator $aFiles, $aBucket) use ($theS3Client) {
			foreach ($aFiles as $theFileInfo) {
				// @var $theFileInfo \SplFileInfo
				// Skip "." and ".." files and folders.
				if ( $theFileInfo->isDot() || $theFileInfo->isDir() ) {
					continue;
				}
				$theKeyToUse = $theBaseKeyToUse . $theFileInfo->getBasename();
				// Yield a command that will be executed by the pool.
				yield $theS3Client->getCommand('PutObject', array(
						'Bucket' => $aBucket,
						'Key'    => $theKeyToUse,
						'Body'   => fopen($theFileInfo->getRealPath(), 'r'),
				));
			}
		};
	
		// Now create the generator using the files iterator.
		$theCommands = $theCommandGenerator($theFiles, $theBucketToUse);
		
		$theCmdOptions = array(
				// Only send 5 files at a time (this is set to 25 by default).
				'concurrency' => 5,
		);
		if ( $this->isRunningUnderCLI() )
		{
			// Invoke this function before executing each command.
			$theCmdOptions['before'] =
				function (CommandInterface $cmd, $iterKey) {
					print( "About to send {$iterKey}: " );
					print( print_r($cmd->toArray(), true) );
					print( PHP_EOL );
				};
			// Invoke this function for each successful transfer.
			$theCmdOptions['fulfilled'] =
				function (ResultInterface $aResult, $iterKey, PromiseInterface $aPromise) {
					print( "Completed {$iterKey}: {$aResult}" . PHP_EOL );
				};
			// Invoke this function for each failed transfer.
			$theCmdOptions['rejected'] =
				function (AwsException $aReason, $iterKey, PromiseInterface $aPromise) {
					print( "Failed {$iterKey}: {$aReason}" . PHP_EOL );
				};
		}
		// Create a pool and provide an optional array of configuration.
		$theCmdPool = new CommandPool( $theS3Client, $theCommands, $theCmdOptions );
		
		// Initiate the pool transfers
		$thePromise = $theCmdPool->promise();
		
		// Force the pool to complete synchronously
		//$promise->wait();
		
		// Or you can chain then calls off of the pool
		if ( $this->isRunningUnderCLI() )
		{
			$thePromise->then(function() { print("Done" . PHP_EOL); });
		}
		return $thePromise;
	}
	
	/**
	 * Uploads a folder to S3 maintaining the original folder name.
	 * @param string $aFolderToUpload - full path to the folder.
	 * @param string $aKeyPathToUse - full destination path.
	 * @param string $aBucketName - (optional) specify another bucket to use.
	 * @return PromiseInterface Returns the promise interface.
	 * @throws AwsException if S3 fails for some reason.
	 */
	public function uploadFolderToKey( $aFolderToUpload, $aKeyPathToUse, $aBucketName=null )
	{
		$theKeyToUse = $this->localNameAsKeyPath( $aFolderToUpload, $aKeyPathToUse );
		return $this->uploadFolderAsKey( $aFileToUpload, $theKeyToUse, $aBucketName );
	}
	
}//end class

}//end namespace
