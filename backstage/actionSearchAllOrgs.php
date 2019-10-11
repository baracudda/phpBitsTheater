#!/usr/bin/php
<?php
use BitsTheater\Director; /* @var $director Director */
use BitsTheater\Regisseur; /* @var $theStageManager Regisseur */
use BitsTheater\Scene;
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\AuthOrgSet;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\models\Auth as AuthModel;
use BitsTheater\models\Config as ConfigModel;
use com\blackmoonit\Strings;

global $director;

/**
 * CLI options should be defined by the special function `process_cli_options($aStageManger)`
 * @param Regisseur $aStageManager
 * @return string[] Returns the CLI arguments.
 */
function process_cli_options($aStageManager)
{
	//by the time this function is executed, Regisseur class will have been loaded & usable.
	return $aStageManager->processOptionsForCLI(
			Regisseur::DEFAULT_CLI_SHORT_OPTIONS . 't:c:f:v:',
			array(
				'table',  //required
				'column', 'field', //one of them are required
				'value', //the value to search for
				'fieldlist', //option list of fields to return
			)
	);
}

//standard means of setting up the environment to access the website via CLI
//  the processed CLI options returned by the process_cli_options() are returned as well.
$theCliOptions = require_once(__DIR__ . DIRECTORY_SEPARATOR . 'cli_bootstrap.php');

//which table are we searching in?
if ( !empty($theCliOptions['t']) )  {
	$theSearchTable = $theCliOptions['t'];
}
if ( !empty($theCliOptions['table']) )  {
	$theSearchTable = $theCliOptions['table'];
}
if ( empty($theSearchTable) ) {
	print('-t|--table arg required' . PHP_EOL);
}
//which field are we searching in?
if ( !empty($theCliOptions['c']) )  {
	$theSearchField = $theCliOptions['c'];
}
if ( !empty($theCliOptions['column']) )  {
	$theSearchField = $theCliOptions['column'];
}
if ( !empty($theCliOptions['f']) )  {
	$theSearchField = $theCliOptions['f'];
}
if ( !empty($theCliOptions['field']) )  {
	$theSearchField = $theCliOptions['field'];
}
if ( empty($theSearchField) ) {
	print('-c|-f|--column|--field arg required' . PHP_EOL);
}
if ( !empty($theCliOptions['v']) )  {
	$theSearchValue = $theCliOptions['v'];
}
if ( !empty($theCliOptions['value']) )  {
	$theSearchValue = $theCliOptions['value'];
}
if ( empty($theSearchValue) ) {
	print('-v|--value arg required' . PHP_EOL);
}
if ( empty($theSearchTable) || empty($theSearchField) || empty($theSearchValue) ) {
	exit(1);
}
//optional fieldlist to return
$theResultFieldList = null; //all fields unless specified otherwise
if ( !empty($theCliOptions['fieldlist']) )  {
	$theResultFieldList = $theCliOptions['fieldlist'];
}
//allowed?
$director->admitAudience( new Scene() );
$director->checkAllowed('auth_orgs', 'transcend');
//time to work
$theSql = SqlBuilder::withModel($director->getProp(ConfigModel::MODEL_NAME));
try {
	$theSql->startWith('SELECT')->addFieldList($theResultFieldList)
		->add('FROM')->add($theSearchTable)
		->startWhereClause()
		->setParamOperator(' LIKE ')
		->mustAddParam($theSearchField, $theSearchValue)
		->endWhereClause()
		;
	$theOrgSet = AuthOrgSet::withContextAndColumns($director)
		->setPagerEnabled(false)
		->getOrganizationsToDisplay()
		;
	$theOrgList = $theOrgSet->mDataSet->fetchAll();
	//add & start with Root
	array_unshift($theOrgList, ((object) array(
			'org_id' => AuthModel::ORG_ID_4_ROOT,
			'org_name' => 'Root',
			'org_title' => 'Root',
	)));
	if ( !empty($theOrgList) ) {
		foreach ($theOrgList as $theOrg) {
			/* @var $theOrg AuthOrg */
			//config model is part of the org, can access any table after connection is made.
			$theSql->setModel($director->getProp(ConfigModel::MODEL_NAME, $theOrg->org_id));
			print('Searching org db: ' . $theOrg->org_name . PHP_EOL);
			$theRowSet = $theSql->query();
			if ( !empty($theRowSet) ) foreach( $theRowSet as $theRow ) {
				print(json_encode($theRow) . PHP_EOL);
			}
			$director->getPropsMaster()->closeConnection($theOrg->org_id);
		}
	}
	print('Finished searching.' . PHP_EOL);
}
catch( \Exception $x ) {
	$blx = BrokenLeg::tossException($director, $x);
	print($blx->getExtendedErrMsg() . PHP_EOL);
	exit(1);
}
