<?php
/**
 * Global route definitions for Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 * @link		http://thoughtmonster.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

Router::add('*', array('controller' => 'admin', 'action' => 'delegate'));
Router::add('/', array('controller' => 'admin', 'action' => 'dashboard'));
Router::add('login/logout', array('controller' => 'login', 'action' => 'logout'));
Router::add('login/login', array('controller' => 'login', 'action' => 'login'));
Router::add('login', array('controller' => 'login', 'action' => 'index'));

/* End of file routes.php */