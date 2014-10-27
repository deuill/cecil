<?php
/**
 * Controller for flexible administration pages.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 * @link		http://deuill.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class AdminController extends AppController {
	public $models = array('Import', 'Module');
	public $breadcrumbs;

	public function Submit($id = null) {
		$params = Dispatcher::params(false);
		$action = reset(array_keys($_POST['action']));
		$search = reset(array_values($_POST['action']));
		$id = (isset($id)) ?  $id : reset(array_values($_POST['action']));

		unset($_POST['action']);
		$data = array_merge_recursive($_POST, $_FILES);

		switch ($action) {
		case 'save':
			$result = $this->Module->saveItem($data, $params['controller'], $id);
			if (!empty($id)) {
				Router::redirect('/'.$params['controller'].'/edit/'.$id);
			}

			Router::redirect('/'.$params['controller'].'/edit/'.$result);
		case 'remove':
			$this->Module->removeItem($params['controller'], $id);
			Router::redirect('/'.$params['controller']);
		case 'reorder':
			$this->Module->orderItems($params['controller'], $_POST['order']);
			Router::redirect('/'.$params['controller']);
		case 'search':
			Router::redirect('/'.$params['controller'].'?search='.$search);
		default:
			Router::redirect('/'.$params['controller']);
		}
	}

	public function Edit($id = null) {
		$params = Dispatcher::params(false);
		$this->setup();

		$this->set('module-id', $id);
		$tmp = $this->Module->getItem($params['controller'], $_SESSION['user']->client_id, $id);

		if (isset($id) && empty($tmp['data']->id)) {
			Router::redirect('/'.$params['controller']);
		}

		// Categorize data by type and importance.
		foreach ($tmp['meta'] as $type) {
			$name = $type->name;

			if (empty($type->alias)) {
				$type->alias = Inflector::title($type->name);
			}

			$value = array(
				'name'	=>	$name,
				'alias'	=>	$type->alias
			);

			if (!empty($id)) {
				$value['data'] = $tmp['data']->$name;
			}

			if (isset($type->options['required'])) {
				$value['required'] = 'required';
			}

			// Parse options.
			switch ($type->type) {
			case 'string':
				if (isset($type->options['type'])) {
					if ($type->options['type'] === 'tag') {
						$value['type'] = 'text';
						$value['tag'] = true;
					} else {
						$value['type'] = $type->options['type'];
					}
				} else {
					$value['type'] = 'text';
				}
				break;
			case 'text':
				if (isset($type->options['type'])) {
					if ($type->options['type'] === 'html') {
						$value['class'] = 'js-wysiwyg';
					} else if ($type->options['type'] === 'list') {
						$value['class'] = 'js-tag-list';
					}
				}
				break;
			case 'date':
				if (!isset($type->options['time'])) {
					$value['time'] = true;
				}
				break;
			case 'category':
				if (isset($type->options['type'])) {
					if ($type->options['type'] === 'multiple') {
						$value['type'] = 'multiple';
					}
				} else {
					$value['type'] = '';
				}

				foreach ((array) $type->categories as $category) {
					if (isset($value['data'])) {
						foreach ($value['data'] as $i => $selected) {
							if ($category->id == $selected) {
								$category->selected = 'selected';
								unset($value['data'][$i]);
								break;
							}
						}
					}

					$value['categories'][] = $category;
				}

				if (isset($value['categories'])) {
					$value['data'] = $value['categories'];
					unset($value['categories']);
				}
				break;
			}

			// Add types to data.
			switch ($type->type) {
			case 'file':
				if (isset($type->options['type'])) {
					$data['media'][$type->options['type']][] = $value;
				} else {
					$data['media'][$type->type][] = $value;
				}

				break;
			case 'boolean':
			case 'date':
				if (!isset($value['time']) && isset($value['data'])) {
					$value['data'] = reset(explode(' ', $value['data']));
				}

				$data['primary'][$type->type][] = $value;
				break;
			case 'number':
			case 'text':
				$data['primary'][$type->type][] = $value;
				break;
			default:
				if (!isset($data['primary'][$type->type])) {
					$data['primary'][$type->type] = $value;
				} else {
					$data['secondary'][$type->type][] = $value;
				}
			}
		}

		// First media tab has class 'active'.
		if (isset($data['media'])) {
			$type = reset(array_keys($data['media']));
			$data['media'][$type][0]['active'] = 'active';
		}

		// First text tab has class 'active'.
		if (isset($data['primary']['text'])) {
			$data['primary']['text'][0]['active'] = 'active';
		}

		if (isset($data['primary']['string']['data'])) {
			$title = $data['primary']['string']['data'];
		} else {
			$title = '[[Add new]]';
		}

		$this->title($title);

		$this->breadcrumbs[] = array(
			'name'	=>	$title,
			'icon'	=>	'edit',
			'link'	=>	$params['controller'].'/edit/'.$id
		);

		$this->set('breadcrumbs', $this->breadcrumbs);
		$this->set($data);
	}

	public function Index($offset = 0) {
		$params = Dispatcher::params(false);
		$this->setup();

		$query = '';
		if (isset($_GET['search'])) {
			$this->set('item-search', $_GET['search']);
			$search = '%'.$_GET['search'].'%';
			$query = '?search='.$_GET['search'];
		} else {
			$search = '%';
		}

		$data = $this->Module->listItems($params['controller'], $offset, $search);
		$this->set('items', $data->items);

		$prev = null;
		if ($data->page->offset - $data->page->limit >= 0) {
			$prev = $data->page->offset - $data->page->limit;
		}

		$next = null;
		if ($data->page->offset + $data->page->limit < $data->page->total) {
			$next = $data->page->offset + $data->page->limit;
		}

		$this->set('page', array(
			'from'	=>	($data->page->total) ? $data->page->offset + 1 : 0,
			'to'	=>	(isset($next)) ? $next : $data->page->total,
			'prev'	=>	(isset($prev)) ? $prev.$query : null,
			'next'	=>	(isset($next)) ? $next.$query : null,
			'total'	=>	$data->page->total
		));

		$this->set('breadcrumbs', $this->breadcrumbs);
	}

	public function Dashboard() {
		// The Administrator is redirected to use the Admin Panel directly.
		if ($_SESSION['user']->client_id === 1 && $_SESSION['user']->id === 1) {
			Router::redirect('/panel');
		}

		$modules = $this->Module->getModules($_SESSION['user']->client_id, $_SESSION['user']->id);
		if (!empty($modules)) {
			// Users with only one module enabled are redirected to that module directly.
			if (count($modules) == 1) {
				Router::redirect('/'.$modules[0]->name);
			}

			$c = 0;
			foreach ($modules as $i => $module) {
				if ($i % 4 === 0 && $i !== 0) {
					$c++;
				}

				$moduleRows[$c][] = $module;
			}

			$this->set('modules', $moduleRows);
		}

		$this->title('[[Main]]');
		$this->set('breadcrumbs', $this->breadcrumbs);
	}

	public function Delegate() {
		$params = Dispatcher::params(false);

		// Disallow outside access to this method.
		if (isset($params['args'][0]) && $params['args'][0] == 'delegate') {
			Router::redirect('/');
		}

		// Disallow access to user for disabled modules.
		$status = $this->Module->validate($_SESSION['user']->client_id, $_SESSION['user']->id);
		if ($status == false) {
			Router::redirect('/');
		}

		// Dispatch request to the module class, if any exists, or attempt to
		// handle it using our own generic methods.
		if (file_exists(APP_DIR.'controller/'.Inflector::camelize($params['controller']).'Controller.php')) {
			include(APP_DIR.'controller/'.Inflector::camelize($params['controller']).'Controller.php');

			$name = Inflector::camelize($params['controller']).'Controller';
			$controller = new $name;

			Dispatcher::loadModels($controller);

			Mediator::unsubscribe('beforeCall');
			Mediator::subscribe('beforeCall', array($controller, 'beforeCall'));

			Mediator::unsubscribe('beforeRender');
			Mediator::subscribe('beforeRender', array($controller, 'beforeRender'));

			Mediator::unsubscribe('afterRender');
			Mediator::subscribe('afterRender', array($controller, 'afterRender'));

			$action = Inflector::camelize($params['action']);
			if (in_array($action, get_class_methods($controller))) {
				call_user_func_array(array($controller, $action), $params['args']);
			}

			// Render the template if it hasn't been already.
			$controller->render(Inflector::camelize($params['controller']).'/'.$params['action']);
		} else {
			$action = Inflector::camelize($params['action']);
			if (in_array($action, get_class_methods($this))) {
				call_user_func_array(array($this, $action), $params['args']);
			}

			// Render the template if it hasn't been already.
			$this->render('Admin/'.$params['action']);
		}

		// Show all generated warnings at the end of the page.
		Exceptions::show();
		exit;
	}

	public function setup() {
		$params = Dispatcher::params(false);
		$this->set('module', $params['controller']);

		$this->breadcrumbs[] = array(
			'name'	=>	'[[Main]]',
			'icon'	=>	'compass',
			'link'	=>	''
		);

		$modules = $this->Module->getModules($_SESSION['user']->client_id, $_SESSION['user']->id);
		foreach ($modules as $i => $module) {
			if ($module->name == $params['controller']) {
				$modules[$i]->active = true;
				$this->title($module->alias);
				$this->breadcrumbs[] = array(
					'name'	=>	$module->alias,
					'icon'	=>	$module->icon,
					'link'	=>	$module->name
				);
			}
		}

		$this->set('modules', $modules);
	}
}

// End of file AdminController.php