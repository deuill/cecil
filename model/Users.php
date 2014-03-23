<?php
/**
 * User model for Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 * @link		http://thoughtmonster.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class Users extends AppModel {
	public $modules = array('Auth', 'Database', 'File', 'User');

	public function get($id = null) {
		$db = new Database;
		if ($id !== null) {
			$db->where('id', $id);
		}

		$users = $db->get('users');

		if (empty($users)) {
			return null;
		}

		foreach ($users as $i => $user) {
			$client = reset($db->where('id', $user->client_id)->get('clients'));
			$result = new User($client->auth_id);
			if (!isset($result->id)) {
				return null;
			}

			$users[$i]->authkey = $result->authkey;

			if (isset($user->image)) {
				$user->image = (object) array(
					'checksum'	=>	$user->image,
					'url'		=>	(string) new File($user->image)
				);
			} else {
				unset($user->image);
			}
		}

		if ($id !== null) {
			return reset($users);
		} else {
			return $users;
		}
	}

	public function save($data, $id = null) {
		$db = new Database;

		if ($data['image']['new']['error'] === 0) {
			move_uploaded_file(
				$data['image']['new']['tmp_name'],
				APP_DIR.'/tmp/'.$data['image']['new']['name']
			);

			$result = new File(APP_DIR.'/tmp/'.$data['image']['new']['name']);
			if (strlen($result) > 0) {
				$image = sha1_file(APP_DIR.'/tmp/'.$data['image']['new']['name']);
			}

			unlink(APP_DIR.'/tmp/'.$data['image']['new']['name']);
		}

		if ($id === null) {
			$user = $db->where('username', $data['username'])->get('users');
			if (!empty($user)) {
				return false;
			}

			$auth = new Auth($data['password']);

			$id = $db->put('users', array(
				'client_id'	=>	$data['client'],
				'username'	=>	$data['username'],
				'password'	=>	$auth->generate(),
				'fullname'	=>	$data['fullname'],
				'image'		=>	(isset($image)) ? $image : null
			));
		} else {
			$user = reset($db->where('id', $id)->get('users'));
			$update['fullname'] = $data['fullname'];

			if (isset($data['password'])) {
				$auth = new Auth($data['password']);
				$update['password'] = $auth->generate();
			}

			if (empty($data['image']['old']) && !empty($user->image)) {
				$file = new File($user->image);
				$file->delete();

				$update['image'] = null;
			}

			if (isset($image)) {
				$update['image'] = $image;
			}

			$db->where('id', $id)->put('users', $update);
		}

		return $id;
	}

	public function remove($id) {
		$db = new Database;
		$user = $db->where('id', $id)->get('users');
		if (!empty($user->image)) {
			$file = new File($user->image);
			$file->remove();
		}

		$db->where('id', $id)->delete('users');
		$db->where('user_id', $id)->delete('user_modules');
	}

	public function update($id, $data) {
		$db = new Database;
		$auth = new Auth($data['password']);

		$db->where('id', $id)->put('users', array(
			'password'	=>	$auth->generate()
		));
	}

	public function validate($username, $password) {
		$db = new Database;
		$user = $db->where('username', $username)->get('users');
		if (empty($user)) {
			return null;
		}

		$user = reset($user);
		$auth = new Auth($password);

		$result = $auth->validate($user->password);
		if ($result == false) {
			return null;
		}

		$user = $this->get($user->id);

		return $user;
	}

	public function addModule($data) {
		$db = new Database;
		$db->where('module_id', $data['module']);
		$db->where('client_id', $data['client']);
		$client_module = reset($db->get('client_modules'));

		if (empty($client_module)) {
			return false;
		}

		$db->put('user_modules', array(
			'user_id'		=> $data['user'],
			'client_module' => $client_module->id
		));

		return true;
	}

	public function removeModule($user, $client, $module) {
		$db = new Database;
		$db->where('module_id', $module);
		$db->where('client_id', $client);
		$client_module = reset($db->get('client_modules'));

		if (empty($client_module)) {
			return false;
		}

		$db->where('user_id', $user);
		$db->where('client_module', $client_module->id);
		$db->delete('user_modules');
	}
}

/* End of file User.php */