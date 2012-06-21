<?php
namespace app\model;
use com\blackmoonit\Strings;
use app\Model;
use app\DbException;
{//namespace begin

class Permissions extends Model {
	const VALUE_Allow = '+';
	const VALUE_Deny = 'x';

	public $tnPermissions; const TABLE_Permissions = 'permissions';

	public function setup($aDbConn) {
		parent::setup($aDbConn);
		$this->tnPermissions = $this->tbl_.self::TABLE_Permissions;
		$this->sql_get_namespace = "SELECT * FROM {$this->tnPermissions} WHERE namespace = :ns AND value = :value";
		$this->sql_get_group = "SELECT * FROM {$this->tnPermissions} WHERE group_id = :group_id";
		$this->sql_del_group = "DELETE FROM {$this->tnPermissions} WHERE group_id = :group_id";
		$this->sql_add_right = "INSERT INTO {$this->tnPermissions} (namespace, permission, group_id, value) VALUES (:ns,:perm,:group_id,:value)";
	}
	
	public function isEmpty($aTableName=null) {
		if ($aTableName==null)
			$aTableName = $this->tnPermissions;
		return parent::isEmpty($aTableName);
	}
	
	public function setupModel() {
		$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnPermissions} ".
				"( namespace CHAR(40) NULL COLLATE utf8_unicode_ci".
				", permission CHAR(40) NOT NULL COLLATE utf8_unicode_ci".
				", group_id INT NOT NULL".
				", value CHAR(1) CHARACTER SET ascii COLLATE ascii_bin NOT NULL".
				", PRIMARY KEY (namespace, permission, group_id)".
				", KEY IdxValuePermissions (namespace, value)".
				", UNIQUE KEY IdxGroupPermissions (group_id, namespace, permission)".
				") CHARACTER SET utf8 COLLATE utf8_bin";
		$this->execDML($theSql);
	}
	
	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		if (empty($this->_permCache[$aNamespace])) {
			$this->_permCache[$aNamespace]['allow'] = array();
			$this->_permCache[$aNamespace]['deny'] = array();
			try {
				$r = $this->query($this->sql_get_namespace,array('ns'=>$aNamespace,'value'=>self::VALUE_Allow));
				while ($row = $r->fetch()) {
					if (empty($this->_permCache[$aNamespace]['allow'][$row['permission']]))
						$this->_permCache[$aNamespace]['allow'][$row['permission']] = array();
					$this->_permCache[$aNamespace]['allow'][$row['permission']][] = $row['group_id'];
				}
				$r->closeCursor();
				$this->bindValues($r,array('ns'=>$aNamespace,'value'=>self::VALUE_Deny));
				$r->execute();
				while ($row = $r->fetch()) {
					if (empty($this->_permCache[$aNamespace]['deny'][$row['permission']]))
						$this->_permCache[$aNamespace]['deny'][$row['permission']] = array();
					$this->_permCache[$aNamespace]['deny'][$row['permission']][] = $row['group_id'];
				}
				$r->closeCursor();
			} catch (DbException $dbe) {
				//use default empty arrays which will mean all permissions will be not allowed
			}
		}
		if (empty($acctInfo)) {
			$acctInfo =& $this->director->account_info; 
		}
		if (empty($acctInfo)) {
			return false; //if still no account, nothing to check against, return false.
		}
		//Strings::debugLog('acctinfo:'.Strings::debugStr($acctInfo));
		//Strings::debugLog('perms:'.Strings::debugStr($this->_permCache));
		if (!empty($acctInfo['groups']) && !(array_search(1,$acctInfo['groups'],true)===false)) {
			return true; //group 1 is always allowed everything
		}
		//check deny first
		if (!empty($this->_permCache[$aNamespace]['deny'][$aPermission])) {
			if (count(array_intersect($this->_permCache[$aNamespace]['deny'][$aPermission],$acctInfo['groups']))>0)
				return false;
		}
		//check allow next
		//Strings::debugLog($aNamespace.'/'.$aPermission.'&allow:'.Strings::debugStr(array_intersect($this->_permCache[$aNamespace]['allow'][$aPermission],$acctInfo['groups'])));
		if (!empty($this->_permCache[$aNamespace]['allow'][$aPermission])) {
			if (count(array_intersect($this->_permCache[$aNamespace]['allow'][$aPermission],$acctInfo['groups']))>0)
				return true;
		}
		//if neither denied, nor allowed, then it is not allowed
		return false;
	}
	
	/**
	 * Use "namespace" to retrieve all the different namespaces for permissions.
	 */
	public function getPermissionRes($aNamespace) {
		return $this->director->getRes('Permissions/'.$aNamespace);
	}
	
	public function getAssignedRights($aGroupId) {
		$r = $this->query($this->sql_get_group,array('group_id'=>$aGroupId));
		$ar = array();
		while ($row = $r->fetch()) {
			$ar[$row['namespace']][$row['permission']] = ($row['value']==self::VALUE_Allow)?'allow':'deny';
		}
		return $ar;
	}
	
	public function modifyGroupRights($aScene) {
		$theGroupId = $aScene->group_id;
		$this->execDML($this->sql_del_group,array('group_id'=>$theGroupId));
		$right_groups = $this->getPermissionRes('namespace');
		foreach ($right_groups as $ns => $nsInfo) {
			foreach ($this->getPermissionRes($ns) as $theRight => $theRightInfo) {
				$varName = $ns.'__'.$theRight;
				$theAssignment = $aScene->$varName;
				//Strings::debugLog($varName.'='.$theAssignment);
				if ($theAssignment=='allow') {
					$this->query($this->sql_add_right,array('ns'=>$ns, 'perm'=>$theRight, 'group_id'=>$theGroupId, 'value'=>self::VALUE_Allow)); 
				} elseif ($theAssignment=='deny') {
					$this->query($this->sql_add_right,array('ns'=>$ns, 'perm'=>$theRight, 'group_id'=>$theGroupId, 'value'=>self::VALUE_Deny)); 
				}
			}//end foreach
		}//end foreach
	}

}//end class

}//end namespace
