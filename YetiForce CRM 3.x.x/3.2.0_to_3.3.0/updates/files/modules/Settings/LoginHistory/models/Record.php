<?php

/**
 * 
 * @package YetiForce.Models
 * @license licenses/License.html
 * @author Mriusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class Settings_LoginHistory_Record_Model extends Settings_Vtiger_Record_Model
{

	/**
	 * Function to get the Id
	 * @return <Number> Profile Id
	 */
	public function getId()
	{
		return $this->get('login_id');
	}

	/**
	 * Function to get the Profile Name
	 * @return <String>
	 */
	public function getName()
	{
		return $this->get('user_name');
	}

	public function getAccessibleUsers()
	{
		$adb = PearDatabase::getInstance();
		$usersListArray = array();

		$query = 'SELECT user_name FROM vtiger_loginhistory';
		$result = $adb->pquery($query, array());
		while ($row = $adb->fetchByAssoc($result)) {
			$usersListArray[$row['user_name']] = $row['user_name'];
		}
		return $usersListArray;
	}

	/**
	 * Function to retieve display value for a field
	 * @param <String> $fieldName - field name for which values need to get
	 * @return <String>
	 */
	public function getDisplayValue($fieldName, $recordId = false)
	{
		if ($fieldName == 'login_time' || $fieldName == 'logout_time') {
			if ($this->get($fieldName) != '0000-00-00 00:00:00') {
				return Vtiger_Datetime_UIType::getDateTimeValue($this->get($fieldName));
			} else {
				return '---';
			}
		} elseif ($fieldName == 'status') {
			return vtranslate($this->get($fieldName), 'Settings::Vtiger');
		} else {
			return $this->get($fieldName);
		}
	}
}
