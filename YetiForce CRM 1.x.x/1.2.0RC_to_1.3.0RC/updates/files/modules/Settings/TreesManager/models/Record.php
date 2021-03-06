<?php
/*+***********************************************************************************************************************************
 * The contents of this file are subject to the YetiForce Public License Version 1.1 (the "License"); you may not use this file except
 * in compliance with the License.
 * Software distributed under the License is distributed on an "AS IS" basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and limitations under the License.
 * The Original Code is YetiForce.
 * The Initial Developer of the Original Code is YetiForce. Portions created by YetiForce are Copyright (C) www.yetiforce.com. 
 * All Rights Reserved.
 *************************************************************************************************************************************/
class Settings_TreesManager_Record_Model extends Settings_Vtiger_Record_Model {
	/**
	 * Function to get the Id
	 * @return <Number> Role Id
	 */
	public function getId() {
		return $this->get('templateid');
	}
	
	/**
	 * Function to get the Role Name
	 * @return <String>
	 */
	public function getName() {
		return $this->get('rolename');
	}
	
	/**
	 * Function to get module of this record instance
	 * @return <Settings_Webforms_Module_Model> $moduleModel
	 */
	public function getModule() {
		return $this->module;
	}
	
	/**
	 * Function to get the Edit View Url for the Role
	 * @return <String>
	 */
	public function getEditViewUrl() {
		return 'index.php?module=TreesManager&parent=Settings&view=Edit&record='.$this->getId();
	}

	/**
	 * Function to get the Delete Action Url for the current role
	 * @return <String>
	 */
	public function getDeleteUrl() {
		return '?module=TreesManager&parent=Settings&action=Delete&record='.$this->getId();
	}
	
	/**
	 * Function to get Detail view url
	 * @return <String> Url
	 */
	public function getDetailViewUrl() {
		return "index.php?module=TreesManager&parent=Settings&view=Edit&record=".$this->getId();
	}
	
	/**
	 * Function to get List view url
	 * @return <String> Url
	 */
	public function getListViewUrl() {
		return "index.php?module=TreesManager&parent=Settings&view=List";
	}
	
	/**
	 * Function to get record links
	 * @return <Array> list of link models <Vtiger_Link_Model>
	 */
	public function getRecordLinks() {
		$links = array();
		$recordLinks = array(
			array(
					'linktype' => 'LISTVIEWRECORD',
					'linklabel' => 'LBL_EDIT',
					'linkurl' => $this->getEditViewUrl(),
					'linkicon' => 'icon-pencil'
			),
			array(
					'linktype' => 'LISTVIEWRECORD',
					'linklabel' => 'LBL_DELETE',
					'linkurl' => "javascript:Settings_Vtiger_List_Js.triggerDelete(event,'".$this->getDeleteUrl()."');",
					'linkicon' => 'icon-trash'
			)
		);
		foreach($recordLinks as $recordLink) {
			$links[] = Vtiger_Link_Model::getInstanceFromValues($recordLink);
		}
		return $links;
	}
	
	/**
	 * Function to save the role
	 */
	public function insertData($tree, $depth, $parenttrre) {
		$adb = PearDatabase::getInstance();
		$label = $tree['data'];
		$id = $tree['attr']['id'];
		$treeID = 'T'.$id;
		if($parenttrre != '')
			$parenttrre = $parenttrre.'::';
		$parenttrre = $parenttrre.$treeID;

		$sql = 'INSERT INTO vtiger_trees_templates_data(templateid, name, tree, parenttrre, depth, label) VALUES (?,?,?,?,?,?)';
		$params = array($this->getId(), $tree['data'], $treeID, $parenttrre, $depth, $label);
		$adb->pquery($sql, $params);
		if(!empty($tree['children'])){
			foreach ($tree['children'] as $tree) {
				$this->insertData( $tree , $depth+1,$parenttrre);
				if($tree['metadata']['replaceid'])
					$this->replaceValue($tree, $this->get('module'), $this->getId());
			}
		}
	}
	
	public function getTree() {
		$tree = array();
		$templateId = $this->getId();
		if(empty($templateId)) 
			return $tree;

		$adb = PearDatabase::getInstance();
		$parent = array();
		$data = array();
		$lastId = 0;
		$maxDepth = 0;
		$sql = 'SELECT * FROM vtiger_trees_templates_data WHERE templateid = ?';
		$params = array($templateId);
		$result = $adb->pquery($sql, $params);
		for($i = 0; $i < $adb->num_rows($result); $i++){
			$row = $adb->raw_query_result_rowdata($result, $i);
			$treeID = (int)str_replace('T', '', $row['tree']);
			$depth = (int)$row['depth']; 
			$data[$row['tree']] = array( 'data' => $row['name'], 'attr' =>  array('id' => $treeID ) );
			if($depth != 0)
				$parent[$depth][] = $row;
			if( $depth > $maxDepth)
				$maxDepth = $depth;
			if( $treeID > $lastId)
				$lastId = $treeID;
		}
		$this->set('lastId',$lastId);
		for($i = $maxDepth; $i > 0; $i--){
			foreach ($parent[$i] as $row) {
				$treeID = (int)str_replace('T', '', $row['tree']);
				$cut = strlen('::'.$row['tree']);
				$parenttrre = substr($row['parenttrre'], 0, - $cut);
				$pieces = explode('::', $parenttrre);
				$data[end($pieces)]['children'][] = $data[$row['tree']];
				unset($data[$row['tree']]);
			}
		}
		foreach ($data as $row) {
			$tree[] = $row;
		}
		return $tree;
	}
	/**
	 * Function to save the role
	 */
	public function save() {
		$adb = PearDatabase::getInstance();
		$templateId = $this->getId();
		$mode = 'edit';

		if(empty($templateId)) {
			$sql = 'INSERT INTO vtiger_trees_templates(name, module) VALUES (?,?)';
			$params = array($this->get('name'), $this->get('module'));
			$adb->pquery($sql, $params);
			$this->set('templateid', $adb->getLastInsertID() );
			foreach ($this->get('tree') as $tree) {
				$this->insertData( $tree , 0, '');
			}
		} else {
			$sql = 'UPDATE vtiger_trees_templates SET name=?, module=? WHERE templateid=?';
			$params = array($this->get('name'), $this->get('module'), $templateId);
			$adb->pquery($sql, $params);
			$adb->pquery('DELETE FROM vtiger_trees_templates_data WHERE `templateid` = ?', array($templateId));
			foreach ($this->get('tree') as $tree) {
				$this->insertData( $tree , 0, '');
				if($tree['metadata']['replaceid'])
					$this->replaceValue($tree, $this->get('module'), $templateId);
			}
		}
	}

	/**
	 * Function to replaces value in module records
	 * @param <Array> $tree
	 * @param <String> $moduleId
	 * @param <String> $templateId
	 */
	public function replaceValue($tree, $moduleId, $templateId) {
		$adb = PearDatabase::getInstance();
		$query='SELECT `tablename`,`columnname` FROM `vtiger_field` WHERE `tabid` = ? AND `fieldparams` = ? AND presence in (0,2)';
		$result = $adb->pquery($query, array($moduleId, $templateId));
		$num_row = $adb->num_rows($result);

		for($i=0; $i<$num_row; $i++) {
			$row = $adb->query_result_rowdata($result, $i);
			$tableName = $row['tablename'];
			$columnName = $row['columnname'];
			foreach($tree['metadata']['replaceid'] as $id){
				$query = 'UPDATE '.$tableName.' SET '.$columnName.' = ? WHERE '.$columnName.' = ?';
				$params = array('T'.$tree['attr']['id'], 'T'.$id);
				$adb->pquery($query, $params);
			}
		}
	}
	
	/**
	 * Function to delete the role
	 * @param <Settings_Roles_Record_Model> $transferToRole
	 */
	public function delete() {
		$adb = PearDatabase::getInstance();
		$templateId = $this->getId();
		$adb->pquery('DELETE FROM vtiger_trees_templates WHERE `templateid` = ?', array($templateId));
		$adb->pquery('DELETE FROM vtiger_trees_templates_data WHERE `templateid` = ?', array($templateId));
	}

	/**
	 * Function to get the instance of Roles record model from query result
	 * @param <Object> $result
	 * @param <Number> $rowNo
	 * @return Settings_Roles_Record_Model instance
	 */
	public static function getInstanceFromQResult($result, $rowNo) {
		$db = PearDatabase::getInstance();
		$row = $db->raw_query_result_rowdata($result, $rowNo);
		$tree = new self();
		return $tree->setData($row);
	}

	/**
	 * Function to get the instance of Role model, given role id
	 * @param <Integer> $record
	 * @return Settings_Roles_Record_Model instance, if exists. Null otherwise
	 */
	public static function getInstanceById($record) {
		$db = PearDatabase::getInstance();
		$sql = 'SELECT * FROM vtiger_trees_templates WHERE templateid = ?';
		$params = array($record);
		$result = $db->pquery($sql, $params);
		if($db->num_rows($result) > 0) {
			return self::getInstanceFromQResult($result, 0);
		}
		return null;
	}
}