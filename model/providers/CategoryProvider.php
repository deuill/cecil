<?php
/**
 * Category data type provider for Cecil administration module.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 * @link		http://deuill.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class CategoryProvider {
	public $modules = array('Database');

	public function get($module, $column, $id) {
		$db = new Database();

		$table = Inflector::tableize($module.'_categories');
		$db->select('categories.*');
		$db->join('categories', "categories.id = {$table}.category_id", 'left');
		$db->where("{$table}.item_id", $id, 'categories.module', $module);
		$categories = $db->orderBy('categories.order', 'ASC')->get($table);

		foreach ((array) $categories as $i => $c) {
			$categories[$i] = $c->id;
		}

		return $categories;
	}

	public function save($module, $column, $id, $data) {
		$db = new Database();

		$table = Inflector::tableize($module.'_categories');
		$db->where('item_id', $id)->delete($table);
		foreach ($data as $category) {
			if (!empty($category)) {
				$db->put($table, array(
					'item_id'		=>	$id,
					'category_id'	=>	$category
				));
			}
		}
	}

	public function categories($module) {
		$db = new Database();

		$data = $db->select('id', 'name', 'mother')->where('module', $module)->get('categories');
		if (empty($data)) {
			return null;
		}

		$count = count($data);
		$categories = null;
		for ($i = 0; !empty($data); $i++) {
			$current = each($data);
			if ($current === false) {
				reset($data);
				$current = each($data);
			}

			$category = $current['value'];
			if ($category->id == $category->mother) {
				break;
			}

			$tmp = (object) array(
				'id'	=>	$category->id,
				'name'	=>	$category->name,
				'depth'	=>	''
			);

			if ($category->mother == 0) {
				$categories[$category->id] = $tmp;
				unset($data[$current['key']]);
			} else if (isset($categories[$category->mother])) {
				$tmp->depth = $categories[$category->mother]->depth.'—';

				$index = array_search($category->mother, array_keys($categories));
				$categories =
					array_slice($categories, 0, $index + 1, true) +
					array($category->id => $tmp) +
					array_slice($categories, $index, null, true);

				unset($data[$current['key']]);
			}

			if ($i >= $count) {
				break;
			}
		}

		foreach ($categories as $category) {
			$category->name = $category->depth.' '.$category->name;
			unset($category->depth);
		}

		$categories = array_values($categories);

		return $categories;
	}

	public function create($database, $table, $field) {
		$db = new Database();

		$db->query('
			CREATE TABLE IF NOT EXISTS '.$database.'.categories (
				`id` INT PRIMARY KEY AUTO_INCREMENT,
				`module` VARCHAR(255) NOT NULL,
				`mother` INT,
				`name` VARCHAR(255) NOT NULL,
				`description` TEXT,
				`order` SMALLINT UNSIGNED,
				INDEX(`module`),
				INDEX(`mother`),
				INDEX(`order`)
			)
		');

		$category_table = Inflector::tableize($table.'_categories');
		$db->query(
			'CREATE TABLE IF NOT EXISTS '.$database.'.'.$category_table.' (
				`item_id` INT NOT NULL,
				`category_id` INT NOT NULL,
				FOREIGN KEY (`item_id`)  REFERENCES '.$database.'.'.$table.'(id),
				FOREIGN KEY (`category_id`) REFERENCES '.$database.'.categories(id),
				PRIMARY KEY (`item_id`, `category_id`)
			)'
		);

		if (!class_exists('FileProvider') && !include(APP_DIR.'model/providers/FileProvider.php')) {
			Exceptions::log('Couldn\'t find type provider in `providers/FileProvider.php`.');
			Exceptions::error();
		}

		$provider = new FileProvider;

		foreach ($provider->modules as $module) {
			$name = Inflector::title($module);
			$provider->$name =& Sleepy::load($name, 'modules');
		}

		$provider->create($database, 'categories', 'files');
	}

	public function options() {
		return array(
			'type'		=>	array(
				'label'		=>	'Category type',
				'value'		=>	array('Multiple' => 'multiple', 'Single' => 'single')
			),
			'required'	=>	array(
				'label'		=>	'Required',
				'value'		=>	false
			)
		);
	}
}

// End of file CategoryProvider.php