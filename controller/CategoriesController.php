<?php
/**
 * Controller for category administration.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 * @link		http://deuill.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class CategoriesController extends AdminController {
	public $models = array('Module', 'Category');

	public function Submit() {
		$action = reset(array_keys($_POST['action']));
		unset($_POST['action']);

		$data = array_merge_recursive($_POST, $_FILES);
		$id = (isset($data['category']['id'])) ? $data['category']['id'] : null;

		switch ($action) {
		case 'save':
			$result = $this->Category->save($data, $id);

			$id = (empty($id)) ? $result : $id;
			Router::redirect("/categories");
		case 'remove':
			$this->Category->remove($id);
			break;
		case 'reorder':
			$this->Category->reorder($data);
			break;
		}

		Router::redirect('/categories');
	}

	public function Edit($id) {
		if (empty($id)) {
			Router::redirect('/categories');
		}

		$this->setup();

		$category = $this->Category->get($id);
		if (empty($category)) {
			Router::redirect('/categories');
		}

		$this->set('category', $category);

		$this->render('Categories/edit', '');
	}

	public function Add($module, $mother = 0) {
		$this->setup();
		$this->set('category-module', $module);
		$this->set('category-mother', $mother);

		$this->render('Categories/add', '');
	}

	public function Index($id = null) {
		$this->setup();

		$tmp = $this->Category->modules();
		foreach ($tmp as $i => $module) {
			$modules[$i] = clone $module;
			$modules[$i]->alias = $module->alias;
			$modules[$i]->name = Inflector::tableize($module->name);

			if ($module->id == $id) {
				$this->title($modules[$i]->alias);
				$selected = $modules[$i]->name;
				$modules[$i]->active = true;
			}
		}

		$this->set('category-modules', $modules);

		$tmp = $this->Category->get();
		if (isset($selected) && !empty($tmp[$selected])) {
			$this->set('categories', $tmp[$selected]);
		} else if (!isset($id) && !empty($tmp[$modules[0]->name])) {
			$this->set('categories', $tmp[$modules[0]->name]);
		}

		if (isset($selected)) {
			$this->set('module-name', $selected);
		} else {
			$this->title($modules[0]->alias);
			$this->set('module-name', $modules[0]->name);
		}

		foreach ($modules as $i => $module) {
			$modules[$i]->name = $module->alias;
			$modules[$i]->link = 'categories/index/'.$module->id;
		}

		$this->breadcrumbs[] = array(
			'select'		=>	true,
			'placeholder'	=>	'[[New category]]',
			'icon'			=>	'bars',
			'options'		=>	array_values($modules)
		);

		$this->set('breadcrumbs', $this->breadcrumbs);
		$this->render('Categories/index', 'Layouts/default');
	}

	public function setup() {
		$params = Dispatcher::params(false);
		$this->set('module', $params['controller']);

		$modules = $this->Module->getModules($_SESSION['user']->client_id, $_SESSION['user']->id);
		foreach ($modules as $i => $m) {
			if ($m->name == $params['controller']) {
				$modules[$i]->active = true;
				$module = $m;
			}
		}

		$this->title($module->alias);
		$this->set('modules', $modules);
		$this->breadcrumbs = array(
		array(
			'name'	=>	'[[Main]]',
			'icon'	=>	'compass',
			'link'	=>	''
		),
		array(
			'name'	=>	$module->alias,
			'icon'	=>	$module->icon,
			'link'	=>	$module->name
		));
	}
}

/* End of file CategoriesController.php */