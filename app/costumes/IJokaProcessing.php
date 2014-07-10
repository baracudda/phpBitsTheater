<?php
namespace BitsTheater\costumes;
use BitsTheater\costumes\JokaPackage;
{//begin namespace

interface IJokaProcessing {
	public function processIncomingPayload(JokaPackage $aJokaPackage);
}

}//end namespace