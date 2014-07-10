<?php
namespace BitsTheater\actors;
use BitsTheater\Actor;
use BitsTheater\Scene as MyScene;
	/* @var $v MyScene */
use BitsTheater\models\Auth;
	/* @var $dbAuth Auth */
use BitsTheater\models\JokaQueues;
	/* @var $dbJokaQueues JokaQueues */
use BitsTheater\costumes\JokaPackage;
use com\blackmoonit\Strings;
{//namespace begin

class Joka extends Actor {
	const DEFAULT_ACTION = 'queues';
	
	public function queues() {
		/* Debugging Basic HTTP auth
		 * needed the following line put into the .htaccess:
		 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
		 * it was placed just before the framework redirection lines.
		*/
		//shortcut variable $v also in scope in our view php file.
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
			$dbAuth = $this->getProp('Auth');
			$dbAuth->checkTicket($theUserName, $theUserPw);
		}
		$bAuthorized = !$this->director->isGuest();
		//print('isAuth='.($bAuthorized?'true':'false'));
		
		if ($bAuthorized) {
			$dbJokaQueues = $this->getProp('JokaQueues');
			//POST vars are incoming queue
			if ($v->joka_inbound) {
				if (Strings::beginsWith($v->joka_inbound,'[')) {
					$theInboundPackages = json_decode($v->joka_inbound);
					foreach((array)$theInboundPackages as $thePackage) {
						$jp = JokaPackage::fromStdClass($this->director, $thePackage);
						if (empty($v->package_name) && !empty($jp->package_name))
							$v->package_name = $jp->package_name;
						$dbJokaQueues->addIncomingPayload($jp->payload_id, $jp->payload, $jp->package_name, $jp->device_id, $jp->transmit_ts);
					}
				} else {
					$jp = JokaPackage::fromJson($this->director, $v->joka_inbound);
					$dbJokaQueues->addIncomingPayload($jp->payload_id, $jp->payload, $jp->package_name, $jp->device_id, $jp->transmit_ts);
				}
				$dbJokaQueues->processIncomingPayloads();
			}
			
			//POST var acknowledgeing a prior getOutgoingPayloads
			if ($v->joka_outbound_ack) {
				if (Strings::beginsWith($v->joka_outbound_ack,'[')) {
					$theOutboundPackageIds = json_decode($v->joka_outbound_ack,true);
					foreach((array)$theOutboundPackageIds as $thePackageId) {
						$dbJokaQueues->ackOutgoingPayload($thePackageId);
					}
				} else {
					$thePackageId = $v->joka_outbound_ack;
					$dbJokaQueues->ackOutgoingPayload($thePackageId);
				}
			}
						
			//outgoing queue is response
			try {
				$v->result = array();
				$v->result['joka_outbound'] = $dbJokaQueues->getOutgoingPayloads($v->package_name);
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
