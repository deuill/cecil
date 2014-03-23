<?php
/**
 * Controller for login pages.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 * @link		http://thoughtmonster.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class LoginController extends AppController {
	public $models = array('Users');

	public function Login() {
		$result = $this->Users->validate($_POST['username'], $_POST['password']);
		if (is_object($result)) {
			// Reseed session ID.
			session_regenerate_id();
			$_SESSION['user'] = $result;
			$_SESSION['last-used'] = time();

			if (isset($_SESSION['redirect-url'])) {
				$t = $_SESSION['redirect-url'];
				unset($_SESSION['redirect-url']);

				Router::redirect($t);
			}
		} else {
			$_SESSION['login']['fail'] = true;
			$_SESSION['login']['user'] = $_POST['username'];
		}

		Router::redirect('/');
	}

	public function Logout() {
		$_SESSION = array();
		setcookie(session_name(), '', time() - 3600, '/');
		session_destroy();

		Router::redirect('login');
	}

	public function Index() {
		if (isset($_SESSION['login'])) {
			$this->set('fail', true);
			$this->set('username', $_SESSION['login']['user']);
			unset($_SESSION['login']);
		}

		$this->title('[[Login]]');
	}
}

/* End of file LoginController.php */