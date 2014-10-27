<?php
/**
 * Shared controller for Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 * @link		http://deuill.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class AppController extends Controller {
	public $models = array('Module');

	public $title = 'Cecil Admin';
	public $separator = '&rsaquo;';
	public $direction = 'right';

	public function beforeCall() {
		// Start session.
		session_name('s');
		session_start();

		$status = $this->check();
		$params = Dispatcher::params();

		// Redirect to login page if we're not logged in.
		if ($status == false && $params['controller'] != 'login') {
			$_SESSION['redirect-url'] = $_SERVER['REQUEST_URI'];
			Router::redirect('login');
		} else if ($status == true && $params['controller'] == 'login' && $params['action'] == 'index') {
			Router::redirect('/');
		}

		// Replace authkey with that of the user if we're logged in.
		if ($status == true) {
			Sleepy::set('client', 'authkey', $_SESSION['user']->authkey);
		}
	}

	public function beforeRender() {
		$params = Dispatcher::params();

		if ($params['controller'] != 'login') {
			$userInfo = array(
				'page' => array(
					'user'	=>	array(
						'id'		 => $_SESSION['user']->id,
						'username'	 => $_SESSION['user']->username,
						'fullname'	 => $_SESSION['user']->fullname,
					)
				),
			);

			if (isset($_SESSION['user']->image)) {
				$userInfo['page']['user']['image'] = $_SESSION['user']->image;
			}

			$this->set($userInfo);
		}
	}

	public function check() {
		// Invalidate sessions after 6 hours of inactivity.
		if (isset($_SESSION['user']) && (time() - $_SESSION['last-used']) > 21600) {
			Router::redirect('login/logout');
		} else if (!isset($_SESSION['user'])) {
			return false;
		}

		$_SESSION['last-used'] = time();

		return true;
	}
}

/* End of file AppController.php */