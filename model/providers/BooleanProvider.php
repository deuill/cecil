<?php
/**
 * Boolean data type provider for Cecil administration module.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 * @link		http://thoughtmonster.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class BooleanProvider {
	public $modules = array('Database');

	public function get($module, $column, $id) {
		$db = new Database;
		$result = $db->where('id', $id)->select($column)->get($module);
		if (!empty($result)) {
			$result = reset(reset($result));
			return ($result) ? 'checked' : '';
		}

		return null;
	}

	public function save($module, $column, $id, $data) {
		$db = new Database;
		$data = (strlen($data) > 0) ? true : false;
		$db->where('id', $id)->put($module, array(
			$column => $data
		));
	}

	public function create($database, $table, $field) {
		$db = new Database;
		$field = Inflector::normalize($field, '_');

		$db->query(
			'ALTER TABLE '.$database.'.'.$table.'
				ADD COLUMN '.$field.' BOOL'
		);

		$db->query(
			'ALTER TABLE '.$database.'.'.$table.'
				ADD INDEX '.$field.' ('.$field.')'
		);
	}

	public function options() {
		return array(
			'required'	=>	array(
				'label'		=>	'Required',
				'value'		=>	false
			)
		);
	}
}

// End of file BooleanProvider.php