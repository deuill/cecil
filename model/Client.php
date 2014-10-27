<?php
/**
 * Client model for Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 * @link		http://deuill.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class Client extends AppModel {
	public $modules = array('User', 'Database');

	public function get($id = null) {
		$db = new Database;

		if ($id != null) {
			$db->where('id', $id);
		}

		$clients = $db->get('clients');

		if (empty($clients)) {
			return null;
		}

		foreach ($clients as $i => $user) {
			$result = new User($user->auth_id);
			$clients[$i]->authkey = $result->authkey;
		}

		if ($id != null) {
			return reset($clients);
		} else {
			return $clients;
		}
	}

	public function save($data, $id = null) {
		$db = new Database;

		if ($id === null) {
			// Check if client already exists.
			$db->where('name', $data['name']);
			$client = $db->get('clients');
			if (!empty($client)) {
				return false;
			}

			// Add client to our databases.
			$c = new User();
			$result = $c->save();
			$id = $db->put('clients', array(
				'auth_id'	=>	$result->id,
				'name'		=>	$data['name'],
				'url'		=>	$data['url']
			));

			// Add common client options.
			$prefix = Sleepy::get('database', 'prefix');
			$db_name = $prefix.Inflector::normalize($data['name'], '_');

			$c->options(array(
				'database' => array('database' => array('name' => $db_name))
			));

			// Prepare environment for client (i.e. create databases etc).
			$db->query("CREATE DATABASE IF NOT EXISTS {$db_name} DEFAULT CHARACTER SET utf8");
		} else {
			$db->where('id', $id)->put('clients', array(
				'url'	=>	$data['url']
			));

		}

		return $id;
	}

	public function remove($id) {
		$client = $this->get($id);

		$user = new User($client->auth_id);
		$user->remove();

		$db = new Database();
		$db->where('client_id', $id)->delete('client_module_data');
		$db->where('client_id', $id)->delete('client_modules');
		$db->where('id', $id)->delete('clients');
	}

	public function getOption($clientId, $module, $section, $option) {
		$client = $this->get($clientId);
		$user = new User($client->auth_id);

		return $user->options($module, $section, $option);
	}

	public function setOption($clientId, $module, $section = "", $option = "", $value = "") {
		$client = $this->get($clientId);
		$user = new User($client->auth_id);

		return $user->options($module, $section, $option, $value);
	}

	public function deleteOption($clientId, $module = "", $section = "", $option = "") {
		$client = $this->get($clientId);
		$user = new User($client->auth_id);

		return $user->unset($module, $section, $option);
	}

	public function addModule($data) {
		$db = new Database;
		$db->put('client_modules', array(
			'client_id'		=> $data['client'],
			'module_id'		=> $data['module']
		));

		$this->prepareModule($data['client'], $data['module']);

		return true;
	}

	public function editModule($clientId, $data) {
		$db = new Database;

		$db->query(
			"DELETE client_module_data 
				FROM client_module_data 
				INNER JOIN module_data ON client_module_data.data_id = module_data.id
				WHERE module_data.module_id = ?", $data['module']['id']
		);

		$db->where('client_id', $clientId, 'module_id', $data['module']['id']);
		$db->put('client_modules', array(
			'alias'	=>	$data['module']['alias']
		));

		foreach ($data['field'] as $name => $field) {
			$db->put('client_module_data', array(
				'client_id'	=>	$clientId,
				'data_id'	=>	$field['id'],
				'alias'		=>	$field['alias'],
				'status'	=>	(isset($field['disable'])) ? true : false
			));
		}
	}

	public function removeModule($clientId, $moduleId) {
		$db = new Database;
		$db->where('client_id', $clientId)->where('module_id', $moduleId);
		$module = reset($db->get('client_modules'));

		if (!empty($module)) {
			$db->where('id', $module->id)->delete('client_modules');
			$db->where('client_module', $module->id)->delete('user_modules');
		}
	}

	public function getFields($clientId, $moduleId) {
		$db = new Database;
		$name = Sleepy::get('database', 'default');
		$fields = $db->query(
			"SELECT id, name, COALESCE(client_module_data.alias, '') AS alias, status
				FROM {$name}.module_data
				LEFT JOIN {$name}.client_module_data
					ON module_data.id = client_module_data.data_id AND client_module_data.client_id = ?
				WHERE module_id = ? ORDER BY `order` ASC", array($clientId, $moduleId)
		);

		foreach ((array) $fields as $field) {
			if (!$field->status) {
				unset($field->status);
			}
		}

		return $fields;
	}

	private function prepareModule($clientId, $moduleId) {
		$db = new Database;
		$module = reset($db->where('id', $moduleId)->get('modules'));

		$db->select('module_data.name', 'data.type')->where('module_id', $moduleId);
		$data = $db->join('data', 'module_data.data_id = data.id', 'left')->get('module_data');

		$table_name = Inflector::tableize($module->name);
		$db_name = $this->getOption($clientId, 'database', 'database', 'name');

		$exists = (bool) reset(reset($db->query(
			'SELECT COUNT(*) FROM information_schema.TABLES 
				WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
				array($db_name, $table_name)
		)));

		if ($exists) {
			return false;
		}

		$db->query("CREATE TABLE `{$db_name}`.`{$table_name}` (`id` INT PRIMARY KEY AUTO_INCREMENT, `order` INT, INDEX(`order`))");

		foreach ($data as $value) {
			$type = Inflector::title($value->type).'Provider';
			if (!class_exists($type) && !include(APP_DIR.'model/providers/'.$type.'.php')) {
				Exceptions::log('Couldn\'t find type provider in `providers/'.$type.'.php`.');
				Exceptions::error();
			}

			$provider = new $type;

			foreach ($provider->modules as $module) {
				$name = Inflector::title($module);
				$provider->$name =& Sleepy::load($name, 'modules');
			}

			$provider->create($db_name, $table_name, $value->name);
		}
	}
}

/* End of file Client.php */