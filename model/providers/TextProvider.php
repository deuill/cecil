<?php
/**
 * Text data type provider for Cecil administration module.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 * @link		http://deuill.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class TextProvider {
	public $modules = array('Database');

	public function get($module, $column, $id) {
		$db = new Database();
		$result = $db->where('id', $id)->select($column)->get($module);
		if (!empty($result)) {
			return reset(reset($result));
		}

		return null;
	}

	public function save($module, $column, $id, $data) {
		$db = new Database();
		$db->where('id', $id)->put($module, array(
			$column => $data
		));
	}

	public function create($database, $table, $field) {
		$db = new Database();
		$field = Inflector::normalize($field, '_');

		$db->query(
			'ALTER TABLE '.$database.'.'.$table.'
				ADD COLUMN '.$field.' MEDIUMTEXT'
		);
	}

	public function options() {
		return array(
			'type'	=>	array(
				'label'	=>	'Input type',
				'value'	=>	array('HTML text' => 'html', 'Plain text' => 'plain', 'Item list' => 'list')
			),
			'required'	=>	array(
				'label'		=>	'Required',
				'value'		=>	false
			)
		);
	}
}

// End of file TextProvider.php