<?php
/**
 * Date and time data type provider for Cecil administration module.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 * @link		http://deuill.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class DateProvider {
	public $modules = array('Database');

	public function get($module, $column, $id) {
		$db = new Database();
		$result = $db->where('id', $id)->select($column)->get($module);
		if (!empty($result)) {
			$t = reset(reset($result));
			return date('d/m/Y H:i', strtotime($t));
		}

		return null;
	}

	public function save($module, $column, $id, $data) {
		$d = date_parse_from_format('d/m/Y H:i', $data);
		if ($d['year'] !== false) {
			$date = $d['year'].'-'.$d['month'].'-'.$d['day'];
			$date = $date.' '.$d['hour'].':'.$d['minute'];
		} else {
			$date = date('Y-m-d H:i');
		}

		$db = new Database();
		$db->where('id', $id)->put($module, array(
			$column => $date
		));
	}

	public function create($database, $table, $field) {
		$db = new Database();
		$field = Inflector::normalize($field, '_');

		$db->query(
			'ALTER TABLE '.$database.'.'.$table.'
				ADD COLUMN '.$field.' DATETIME'
		);
	}

	public function options() {
		return array(
			'time'	=>	array(
				'label'	=>	'Disable time input',
				'value'	=>	false
			),
			'required'	=>	array(
				'label'		=>	'Required',
				'value'		=>	false
			)
		);
	}
}

// End of file DateProvider.php