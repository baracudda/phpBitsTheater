<?php
namespace BitsTheater\actors;
use BitsTheater\Actor;
use BitsTheater\models\Auth;
use BitsTheater\models\JokaQueues;
use BitsTheater\models\JokaPackage;
use com\blackmoonit\Strings;
{//namespace begin

class Joka extends Actor {
	const DEFAULT_ACTION = 'queues';
	
	/**
	 * IDE helper function, Code-Complete will display defined functions.
	 */
	static protected function asAuth(Auth $aModel) {
		return $aModel;
	}
	static protected function asJokaQueues(JokaQueues $aModel) {
		return $aModel;
	}
	

	public function queues() {
		/* Debugging Basic HTTP auth
		 * needed the following line put into the .htaccess:
		 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
		 * it was placed just before the framework redirection lines.
		*/
		$v =& $this->scene;
		
		//authenticate
		if (empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW'])) {
			$theAuthKey = (!empty($_SERVER['HTTP_AUTHORIZATION'])) ? $_SERVER['HTTP_AUTHORIZATION'] : $v->HTTP_AUTHORIZATION;
			if (!empty($theAuthKey))
				list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($theAuthKey,6)));
		}
		if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
			$theUserName = $_SERVER['PHP_AUTH_USER'];
			$theUserPw = $_SERVER['PHP_AUTH_PW'];
			$dbAuth = self::asAuth($this->getProp('Auth'));
			$dbAuth->checkTicket($theUserName, $theUserPw);
		}
		$bAuthorized = !$this->director->isGuest();
		//print('isAuth='.($bAuthorized?'true':'false'));
		
		if ($bAuthorized) {
			$dbJokaQueues = self::asJokaQueues($this->getProp('JokaQueues'));
			//POST vars are incoming queue
			if ($v->joka_inbound) {
				if (Strings::beginsWith($v->joka_inbound,'[')) {
					$theInboundPackages = json_decode($v->joka_inbound);
					foreach((array)$theInboundPackages as $thePackage) {
						$jp = JokaPackage::fromStdClass($thePackage);
						$dbJokaQueues->addIncomingPayload($jp->payload_id, $jp->payload, $jp->package_name, $jp->device_id, $jp->transmit_ts);
					}
				} else {
					$jp = JokaPackage::fromJson($v->joka_inbound);
					$dbJokaQueues->addIncomingPayload($jp->payload_id, $jp->payload, $jp->package_name, $jp->device_id, $jp->transmit_ts);
				}
				$dbJokaQueues->processIncomingPayloads();
			}
						
			//outgoing queue is response
			try {
				$v->result = array();
				$v->result['joka_outbound'] = $dbJokaQueues->getOutgoingPayloads();
			} catch (Exception $e) {
				$v->result['code'] = $e->getCode();
				$v->result['message'] = $e->getMessage();
			}
			
			$this->renderThisView = 'json_response';
		} else {
			header('WWW-Authenticate: Basic realm="'.$this->getMyUrl().'"');
			header('HTTP/1.0 401 Unauthorized');
			die("Not authorized");
		}
		
	}
	
}//end class

}//end namespace

