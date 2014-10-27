<?php
/**
 * Controller for unified Cecil administration panel.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 * @link		http://deuill.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.3.0
 */

class PanelController extends AppController {
	public $models = array('Client', 'Module', 'Users');
	public $breadcrumbs;

	/*
	 * Module administration.
	 * 
	 */
	public function SubmitModule($id = null) {
		$action = reset(array_keys($_POST['action']));
		unset($_POST['action']);

		switch ($action) {
		case 'save':
			$result = $this->Module->save($_POST, $id);
			if ($result > 0) {
				Router::redirect('panel/modules/'.$result);
			}

			Router::redirect('panel/modules');
		case 'remove':
			$this->Module->remove($id);
			Router::redirect('panel/modules');
		default:
			Router::redirect('panel/modules');
		}
	}

	public function ModuleOptions($name, $type, $data_id = null) {
		$options = $this->Module->getOptions($type, $data_id);
		$this->set('module-name', $name);
		$this->set('data', $options);

		$this->render('Panel/Modules/options', '');
	}

	public function Modules($id = null) {
		$this->setup();
		$this->set('module', true);
		$this->title('[[Modules]]');
		$this->breadcrumbs[] = array(
			'name'	=>	'[[Modules]]',
			'icon'	=>	'sitemap',
			'link'	=>	'panel/modules'
		);

		$modules = $this->Module->getModules();
		foreach ($modules as $i => $module) {
			if ($module->id == $id) {
				$module->active = true;
			}
		}

		$modules = array_values($modules);
		$this->set('modules', $modules);

		$types = $this->Module->getTypes();
		$this->set('types', $types);

		$icons = $this->Module->getIcons();
		$this->set('icons', $icons);

		if ($id != null) {
			$module = $this->Module->get($id);
			if (empty($module)) {
				Router::redirect('panel/modules');
			} else if ($module->essential == 0) {
				unset($module->essential);
			}

			$this->title($module->name);
			$this->set('module', $module);
		} else {
			$this->title('[[New Module]]');
		}

		foreach ($modules as $i => $module) {
			$modules[$i]->name = $module->alias;
			$modules[$i]->link = 'panel/modules/'.$module->id;
		}

		array_unshift($modules, array('name' => '[[New module]]', 'link' => 'panel/modules'));
		$this->breadcrumbs[] = array(
			'select'		=>	true,
			'icon'			=>	'sitemap',
			'options'		=>	array_values($modules)
		);

		$this->set('breadcrumbs', $this->breadcrumbs);
		$this->render('Panel/index', 'Layouts/default', 'Panel/Modules/index');
	}

	/*
	 * Client administration.
	 * 
	 */
	public function SubmitUser($client_id, $id = null) {
		$action = reset(array_keys($_POST['action']));

		if ($client_id == 1) {
			Router::redirect('panel/clients');
		}

		switch ($action) {
		case 'save':
			if ($id == null) {
				if ($_POST['new-password'] != $_POST['confirm-password']) {
					Router::redirect('panel/users/'.$client_id);
				}

				$data = array(
					'username'	=>	$_POST['new-username'],
					'password'	=>	$_POST['new-password'],
					'client'	=>	$client_id,
					'fullname'	=>	$_POST['fullname'],
					'image'		=>	array(
						'new'		=>	$_FILES['upload']
					)
				);

				$client = $this->Client->get($client_id);
				$id = $this->Users->save($data);

				foreach ($_POST['module'] as $module_id => $module) {
					if (isset($module['checked'])) {
						$data = array(
							'user'	 => $id,
							'client' => $client_id,
							'module' => $module_id
						);

						$this->Users->addModule($data);
					}
				}

				if ($id === false) {
					Router::redirect('panel/users/'.$client_id);
				} else {
					Router::redirect('panel/users/'.$client_id.'/'.$id);
				}
			} else {
				$data = array(
					'fullname'	=>	$_POST['fullname'],
					'image'		=>	array(
						'new'		=>	$_FILES['upload'],
						'old'		=>	$_POST['image']
					)
				);

				$user = $this->Users->get($id);

				if (strlen($_POST['previous-password']) > 0) {
					$result = $this->Users->validate($user->username, $_POST['previous-password']);
					if ($result == false) {
						Router::redirect('panel/users/'.$client_id.'/'.$id);
					}

					if ($_POST['new-password'] != $_POST['confirm-password']) {
						Router::redirect('panel/users/'.$client_id.'/'.$id);
					}

					$data['password'] = $_POST['new-password'];
				}

				$this->Users->save($data, $id);

				$modules = $this->Module->getModules($user->client_id, $user->id);
				foreach ($modules as $module) {
					$this->Users->removeModule($user->id, $user->client_id, $module->id);
				}

				foreach ($_POST['module'] as $module_id => $module) {
					if (isset($module['checked'])) {
						$data = array(
							'user'	 => $user->id,
							'client' => $user->client_id,
							'module' => $module_id
						);

						$this->Users->addModule($data);
					}
				}

				Router::redirect('panel/users/'.$user->client_id.'/'.$id);
			}

			Router::redirect('panel/clients');

			break;
		case 'remove':
			if ($id == $_SESSION['user']->id) {
				Router::redirect('panel/clients');
			}

			$user = $this->Users->get($id);
			$this->Users->remove($id);

			Router::redirect('panel/clients/'.$user->client_id);
			break;
		}

		Router::redirect('/clients');
	}

	public function SubmitClient($id = null) {
		$action = reset(array_keys($_POST['action']));

		if ($id == 1) {
			Router::redirect('panel/clients');
		}

		switch ($action) {
		case 'save':
			if ($id == null) {
				$data = array(
					'name'	=>	$_POST['name'],
					'url'	=>	$_POST['url']
				);

				$id = $this->Client->save($data);
				if ($id === false) {
					Router::redirect('panel/clients');
				}

				foreach ($_POST['module'] as $module_id => $module) {
					if (isset($module['checked'])) {
						$data = array(
							'client' => $id,
							'module' => $module_id
						);

						$this->Client->addModule($data);
					}
				}

				if ($id === false) {
					Router::redirect('panel/clients');
				} else {
					Router::redirect('panel/clients/'.$id);
				}
			} else {
				$data = array(
					'url' => $_POST['url']
				);

				$id = $this->Client->save($data, $id);

				$client = $this->Client->get($id);
				$modules = $this->Module->getModules($client->id);

				foreach ($_POST['module'] as $module_id => $module) {
					$skip = false;
					foreach ($modules as $existing) {
						if ($module_id == $existing->id) {
							if (!isset($module['checked'])) {
								$this->Client->removeModule($client->id, $existing->id);
							}

							$skip = true;
						}
					}

					if ($skip) {
						$skip = false;
						continue;
					}

					if (isset($module['checked'])) {
						$data = array(
							'client' => $client->id,
							'module' => $module_id
						);

						$this->Client->addModule($data);
					}
				}

				Router::redirect('panel/clients/'.$id);
			}

			break;
		case 'remove':
			if ($id == $_SESSION['user']->client_id) {
				Router::redirect('panel/clients');
			}

			$users = $this->Users->get();
			foreach ($users as $i => $user) {
				if ($user->client_id == $id) {
					$this->Users->remove($user->id);
				}
			}

			$this->Client->remove($id);

			Router::redirect('panel/clients');
			break;
		case 'module-save':
			$this->Client->editModule($id, $_POST);
			Router::redirect('panel/clients/'.$id);
			break;
		}

		Router::redirect('panel/clients');
	}

	public function Users($client_id, $user_id = null) {
		$this->setup();
		$this->set('user', true);
		$this->title('[[Users]]');

		if (empty($client_id) || $client_id == 1) {
			Router::redirect('panel/clients');
		}

		$this->breadcrumbs[] = array(
			'name'	=>	'[[Clients]]',
			'icon'	=>	'users',
			'link'	=>	'panel/clients'
		);

		$modules = $this->Module->getModules($client_id);

		if (!empty($user_id)) {
			$user = $this->Users->get($user_id);
			if (empty($user) || $user->client_id == 1) {
				Router::redirect('panel/clients');
			}

			$selected = $this->Module->getModules($user->client_id, $user->id);
			foreach ((array) $modules as $i => $module) {
				foreach ((array) $selected as $sel) {
					if ($module->id == $sel->id) {
						$modules[$i]->selected = true;
					}
				}
			}

			$this->title($user->username);
			$this->set('user', $user);
		} else {
			$this->title('[[New user]]');
		}

		$this->set('user_id', $user_id);
		$this->set('client_id', $client_id);
		if (!empty($modules)) {
			$this->set('modules', $modules);
		}

		$this->set('breadcrumbs', $this->breadcrumbs);
		$this->render('Panel/index', 'Layouts/default', 'Panel/Users/index');
	}

	public function ClientModule($client_id, $module_id) {
		$modules = $this->Module->getModules($client_id);
		foreach ($modules as $module) {
			if ($module->id == $module_id) {
				$this->set('module', $module);
				break;
			}
		}

		$fields = $this->Client->getFields($client_id, $module_id);
		$this->set('fields', $fields);

		$this->render('Panel/Clients/module', '');
	}

	public function Clients($id = null) {
		$this->setup();
		$this->set('client', true);
		$this->title('[[Clients]]');
		$this->breadcrumbs[] = array(
			'name'	=>	'[[Clients]]',
			'icon'	=>	'users',
			'link'	=>	'panel/clients'
		);

		$modules = $this->Module->getModules();

		if ($id !== null) {
			$client = $this->Client->get($id);
			if (empty($client) || $client->id == 1) {
				Router::redirect('panel/clients');
			}

			$selected = $this->Module->getModules($client->id);
			foreach ($modules as $i => $module) {
				foreach ((array) $selected as $sel) {
					if ($module->id == $sel->id) {
						$modules[$i]->selected = true;
					}
				}

				if (!$module->essential) {
					unset($module->essential);
				}
			}

			$users = $this->Users->get();
			foreach ($users as $i => $user) {
				if ($user->client_id != $id) {
					unset($users[$i]);
				}
			}

			if (!empty($users)) {
				$this->set('users', array_values($users));
			}

			$this->title($client->name);
			$this->set('client', $client);
		} else {
			$this->title('[[New client]]');
		}

		$clients = array();
		$tmp = $this->Client->get();
		foreach ($tmp as $i => $c) {
			if ($c->id == 1) {
				continue;
			}

			$clients[$i] = array(
				'name'	=>	$c->name,
				'link'	=>	'panel/clients/'.$c->id
			);

			if ($c->id == $id) {
				$clients[$i]['active'] = true;
			}
		}

		array_unshift($clients, array('name' => '[[New client]]', 'link' => 'panel/clients'));
		$this->breadcrumbs[] = array(
			'select'		=>	true,
			'icon'			=>	'user',
			'options'		=>	array_values($clients)
		);

		$this->set('breadcrumbs', $this->breadcrumbs);
		$this->set('modules', $modules);

		$partials = array('Panel/Clients/index', 'Panel/Users/index', 'Panel/Modules/index');
		$this->render('Panel/index', 'Layouts/default', $partials);
	}

	public function Index() {
		Router::redirect('panel/clients');
	}

	public function setup() {
		$this->title('[[Control panel]]');
		$this->breadcrumbs[] = array(
			'name'	=>	'[[Control panel]]',
			'icon'	=>	'wrench',
			'link'	=>	'panel'
		);
	}
}

/* End of file PanelController.php */