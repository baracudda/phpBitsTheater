<?php
namespace BitsTheater\models;
use BitsTheater\models\JokaPackage;
{//begin namespace

interface IJokaProcessing {
	public function processIncomingPayload(JokaPackage $aJokaPackage);
}

}//end namespace