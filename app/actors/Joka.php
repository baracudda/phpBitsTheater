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
	
	/**
	 * Used as REST API, do not put on website menu.
	 */
	public function queues() {
		/* Debugging Basic HTTP auth
		 * needed the following line put into the .htaccess:
		 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
		 * it was placed just before the framework redirection lines.
		*/
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		$v->checkForBasicHttpAuth();
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

	/**
	 * Display the transmit and receive log.
	 */
	public function commlog() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//auth
		if ($this->isGuest())
			return $this->getHomePage();
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('lists');
		//get account id of whomever is logged in
		$v->myUserId = $this->getMyAccountID();
		//get the model to use
		$dbJokaQueues = $this->getProp('JokaQueues');
		
		$v->results = $dbJokaQueues->displayPayloadLog($v);
		if (empty($v->results)) {
			$v->results = null;
			$v->addUserMsg($v->getRes('generic/msg_nothing_found'));
		}
		
		//display this particular html page to view
		//$this->renderThisView = ;
	}
		
	/**
	 * Display the outgoing payload queue.
	 */
	public function outq() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//auth
		if ($this->isGuest())
			return $this->getHomePage();
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('lists');
		//get account id of whomever is logged in
		$v->myUserId = $this->getMyAccountID();
		//get the model to use
		$dbJokaQueues = $this->getProp('JokaQueues');
		
		$v->results = $dbJokaQueues->displayPayloadOutgoingQueue($v);
		if (empty($v->results)) {
			$v->results = null;
			$v->addUserMsg($v->getRes('generic/msg_nothing_found'));
		}
		
		//display this particular html page to view
		//$this->renderThisView = ;
	}
		
}//end class

}//end namespace
