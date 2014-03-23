<?php
/**
 * Category model for Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 * @link		http://thoughtmonster.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class Category extends AppModel {
	public $modules = array('File', 'Image', 'Database');

	public function get($id = null) {
		$db = new Database;

		if (!class_exists('FileProvider') && !include(APP_DIR.'model/providers/FileProvider.php')) {
			Exceptions::log('Couldn\'t find type provider in `providers/FileProvider.php`.');
			Exceptions::error();
		}

		$provider = new FileProvider;
		foreach ($provider->modules as $module) {
			$name = Inflector::title($module);
			$provider->$name =& Sleepy::load($name, 'modules');
		}

		if (!empty($id)) {
			$category = $db->where('id', $id)->get('categories');
			if (empty($category)) {
				return null;
			}

			$category = reset($category);
			$category->files = $provider->get('categories', 'files', $id);
			return $category;
		}

		$data = $db->orderBy('order', 'ASC')->get('categories');
		$categories = null;

		// Iterate through the categories, fetch the image URLs and place
		// the categories in a tree-like heirerarchical array.
		$count = count($data);
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
				'id'			=>	$category->id,
				'module'		=>	$category->module,
				'mother'		=>	$category->mother,
				'name'			=>	$category->name,
				'description'	=>	$category->description,
				'order'			=>	$category->order,
				'sub'			=>	null
			);

			$tmp->files = $provider->get('categories', 'files', $tmp->id);

			if ($category->mother == 0) {
				$categories[$category->module][] = $tmp;

				end($categories[$category->module]);
				$index = key($categories[$category->module]);

				$ref[$category->id] =& $categories[$category->module][$index];
				unset($data[$current['key']]);
			} else if (isset($ref[$category->mother])) {
				$ref[$category->mother]->hassub = true;
				$ref[$category->mother]->sub[] = $tmp;

				end($ref[$category->mother]->sub);
				$index = key($ref[$category->mother]->sub);

				$ref[$category->id] =& $ref[$category->mother]->sub[$index];
				unset($data[$current['key']]);
			}

			if ($i >= ($count * 2)) {
				break;
			}
		}

		return $categories;
	}

	public function save($data, $id = null) {
		$db = new Database;

		if (!class_exists('FileProvider') && !include(APP_DIR.'model/providers/FileProvider.php')) {
			Exceptions::log('Couldn\'t find type provider in `providers/FileProvider.php`.');
			Exceptions::error();
		}

		$provider = new FileProvider;
		foreach ($provider->modules as $module) {
			$name = Inflector::title($module);
			$provider->$name =& Sleepy::load($name, 'modules');
		}

		$files = $data['files'];
		$data = array(
			'module'		=>	$data['category']['module'],
			'mother'		=>	$data['category']['mother'],
			'name'			=>	$data['category']['name'],
			'description'	=>	$data['category']['description']
		);

		if (empty($id)) {
			$id = $db->put('categories', array('id' => 'DEFAULT'));
			$db->where('id', $id)->put('categories', array('order' => $id));
		}

		$provider->save('categories', 'files', $id, $files);
		$result = $db->where('id', $id)->put('categories', $data);

		return (!empty($id)) ? $id : $result;
	}

	public function remove($id) {
		$db = new Database;

		$category = reset($db->where('id', $id)->get('categories'));
		if (empty($category)) {
			return false;
		}

		if (!class_exists('FileProvider') && !include(APP_DIR.'model/providers/FileProvider.php')) {
			Exceptions::log('Couldn\'t find type provider in `providers/FileProvider.php`.');
			Exceptions::error();
		}

		$provider = new FileProvider;
		foreach ($provider->modules as $mod) {
			$name = Inflector::title($mod);
			$provider->$name =& Sleepy::load($name, 'modules');
		}

		$provider->remove('categories', 'files', $id);

		$db->where('id', $id)->delete('categories');
		$db->where('mother', $id)->put('categories', array('mother' => $category->mother));
		return true;
	}

	public function reorder($data) {
		$db = new Database;

		$i = 1;
		$mother = array(0);
		foreach ($data['order'] as $order) {
			if ($order === 'sub-start') {
				$mother[] = $id;
			} else if ($order === 'sub-stop') {
				array_pop($mother);
			} else if (is_numeric($order)) {
				$id = (int) $order;
				$db->where('id', $id)->put('categories', array('mother' => end($mother), 'order' => $i));
				$i++;
			}
		}

		return true;
	}

	public function modules() {
		$db = new Database;

		$default = Sleepy::get('database', 'default');
		$modules = $db->query("
			SELECT DISTINCT {$default}.module_data.module_id AS id, {$default}.modules.name, {$default}.modules.icon,
			COALESCE({$default}.client_modules.alias, {$default}.modules.alias) AS alias
				FROM {$default}.module_data
				JOIN {$default}.modules ON {$default}.module_data.module_id = {$default}.modules.id
				JOIN {$default}.client_modules ON
					{$default}.client_modules.module_id = {$default}.modules.id AND
					{$default}.client_modules.client_id = ?
				WHERE data_id = (SELECT id FROM {$default}.data WHERE type = 'category')
		", array($_SESSION['user']->client_id));

		$first = reset($modules);
		$first->active = true;

		return $modules;
	}
}