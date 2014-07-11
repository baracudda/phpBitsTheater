<?php

namespace BitsTheater\costumes;
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\costumes\JokaPackage;
use BitsTheater\Director;
use \stdClass as StandardClass;
use com\blackmoonit\exceptions\IllegalArgumentException;
use com\blackmoonit\Strings;
{//begin namespace

/**
 * Ancestor class used in JokaPackage->payload.
 */
abstract class AJokaPayload extends BaseCostume {
	
	static public function fromPackage(JokaPackage $aPackage) {
		if ($aPackage!=null) {
			if (is_string($aPackage->payload)) {
				return static::fromJson($aPackage->getDirector(), $aPackage->payload);
			} else if (is_array($aPackage->payload)) {
				return static::fromArray($aPackage->getDirector(), $aPackage->payload);
			} else if ($aPackage->payload instanceof AJokaPayload) {
				return $aPackage->payload;
			} else if ($aPackage->payload instanceof StandardClass) {
				return static::fromStdClass($aPackage->getDirector(), $aPackage->payload);
			} else {
				throw new IllegalArgumentException('JokaPackage->payload is an unknown type; cannot perform fromPackage().');
			}
		} else {
			throw new IllegalArgumentException('JokaPackage should not be NULL.');
		}
	}
	
	public function toPackage (JokaPackage $aPackage) {
		if ($aPackage!=null) {
			$aPackage->payload = $this->toJson();
		} else {
			throw new IllegalArgumentException('JokaPackage should not be NULL.');
		}
	}
	

}//end class

}//end namespace
