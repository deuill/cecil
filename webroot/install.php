<?php
/**
 * Installer for Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 * @link		http://deuill.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.5.0
 */

if (!defined('APP_DIR')) {
	define('APP_DIR', dirname(dirname(__FILE__)).'/');
}

if (!defined('WEBROOT_DIR')) {
	define('WEBROOT_DIR', dirname(__FILE__).'/');
}

if (!include(APP_DIR.'config/config.php')) {
	echo 'Config file not found. Please check that the config file exists and is readable.';
	exit;
}

if (!include($config['client']['path'].'init.php')) {
	echo 'Client files not found. Please check your configuration file.';
	exit;
}

Sleepy::load('Auth', 'modules');
Sleepy::load('Database', 'modules');
Sleepy::load('User', 'modules');

$db = new Database;
$db_name = Sleepy::get('database', 'default');

$db_structure = array(
	"CREATE TABLE `client_module_data` (
		`client_id` int(11) NOT NULL REFERENCES `clients(id)`,
		`data_id` int(11) NOT NULL REFERENCES `data(id)`,
		`alias` varchar(255) DEFAULT NULL,
		`status` tinyint(4) DEFAULT NULL,
		KEY `client_id` (`client_id`),
		KEY `data_id` (`data_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

	"CREATE TABLE `client_modules` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`client_id` int(11) NOT NULL REFERENCES `clients(id)`,
		`module_id` int(11) NOT NULL REFERENCES `modules(id)`,
		`alias` varchar(255) DEFAULT NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;",

	"CREATE TABLE `clients` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`auth_id` int(11) NOT NULL,
		`name` varchar(255) NOT NULL,
		`url` varchar(255) DEFAULT '',
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

	"CREATE TABLE `data` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`type` varchar(255) NOT NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;",

	"INSERT INTO `data` (`id`, `type`) VALUES
		(1, 'string'), (2, 'text'), (3, 'date'),
		(4, 'file'), (5, 'boolean'), (6, 'number'),
		(7, 'category')",

	"CREATE TABLE `module_data` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`module_id` int(11) NOT NULL REFERENCES `modules(id)`,
		`data_id` int(11) NOT NULL REFERENCES `data(id)`,
		`name` varchar(255) NOT NULL,
		`alias` varchar(255) DEFAULT NULL,
		`order` tinyint(4) DEFAULT NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;",

	"CREATE TABLE `module_options` (
		`data_id` int(11) NOT NULL REFERENCES `data(id)`,
		`option` varchar(255) NOT NULL,
		`value` varchar(255) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

	"CREATE TABLE `modules` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`name` varchar(255) NOT NULL,
		`alias` varchar(255) DEFAULT NULL,
		`icon` varchar(255) DEFAULT NULL,
		`essential` tinyint(1) DEFAULT '0',
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;",

	"INSERT INTO modules (`name`, `icon`, `essential`) VALUES
		('Categories', 'bars', 1)",

	"CREATE TABLE `user_modules` (
		`user_id` int(11) NOT NULL REFERENCES `users(id)`,
		`client_module` int(11) NOT NULL REFERENCES `client_modules(id)`
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

	"CREATE TABLE `users` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`client_id` int(11) NOT NULL REFERENCES `clients(id)`,
		`username` varchar(255) NOT NULL,
		`password` varchar(255) NOT NULL,
		`fullname` varchar(255) DEFAULT '',
		`image` varchar(255) DEFAULT NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;"
);

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>Installer</title>

		<meta name="description" content="">
		<meta name="viewport" content="width=device-width">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

		<link rel="stylesheet" href="css/bootstrap.css">
		<link rel="stylesheet" href="css/main.css">
	</head>
	<body>

		<div class="container">
			<div class="row" style="margin-top: 10%">
				<div class="col-md-3">
					<h1>Installer</h1>
					<p>
					This page will help you prepare the environment required for the operation of Cecil.
					</p>
				</div>

				<div class="col-md-6">
					<div class="panel panel-default">
						<div class="panel-body">
<?php
/*
 * Installer logic.
 */ 
switch ($_POST['action']) {
case 'install':
	if ($_POST['password'] !== $_POST['confirm']) {
		header('Location: '.$_SERVER['DOCUMENT_URI'].'?fail=true');
		exit;
	}

	$exists = $db->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", $db_name);
	if (!empty($exists)) {
		Exceptions::error(404);
	}

	$db->query("CREATE DATABASE IF NOT EXISTS {$db_name} DEFAULT CHARACTER SET utf8");
	$db->query("USE {$db_name}");

	foreach ($db_structure as $q) {
		$db->query($q);
	}

	$user = new User($config['client']['authkey']);
	$user->options('database', 'database', 'name', $db_name);

	$auth = new Auth($_POST['password']);
	$db->query('INSERT INTO clients (id, auth_id, name) VALUES (?, ?, ?)', array(1, $user->id, 'Administrator'));
	$db->query('INSERT INTO users (id, client_id, username, password) VALUES (?, ?, ?, ?)',
		array(1, 1, $_POST['username'], $auth->generate())
	);

	header('Location: '.$_SERVER['DOCUMENT_URI'].'?page=summary');
	exit;
}

/*
 * Per-page content starts here.
 */

/* Landing page */
switch ($_GET['page']) {
case 'summary':
	?>
	<p><b>Installation complete!</b>
	<p>Cecil is now ready to use!</p>
	<p>
		We suggest you remove this installation script from the 'webroot' directory, to protect against
		unauthorized access.
	</p>
	<p>
		Pressing the <i>'Close'</i> button below will redirect you to the login page for Cecil.
	</p>
	<p>Enjoy!</p>
	<a href="/" class="btn btn-primary">Close</a>

	<?php
	break;
default:
	if (strlen($config['client']['authkey']) === 0) {
		?>
		<p>To proceed with installation, Cecil has to be assigned an authkey from the server.</p>
		<p>
			To create a new user and receive an authkey, run the following command on the system
			where the sleepy daemon is running:
		</p>
		<pre>sleepyd user -a</pre>
		<p>
			Take the authkey returned by the server and place its value in the <i>'client.authkey'</i>
			option of the <i>'config.php'</i> configuration file.
		</p>
		<p>Press the <i>'Next'</i> button when the above steps have been completed.</p>
		<a href="" class="btn btn-primary">Next</a>
		<?php
	} else {
		if (isset($_GET['fail'])) {
			?>
			<div class="alert alert-danger">The passwords supplied did <b>not</b> match!</div>
			<?php
		}
		?>
		<p class="text-center"><b>Enter a username and password for the default administrator user:</b></p>
		<form method="POST" action="">
			<div class="form-group">
				<label for="username" class="control-label">Username</label>
				<input type="text" name="username" class="form-control">
			</div>

			<div class="form-group">
				<label for="password" class="control-label">Password</label>
				<input type="password" name="password" class="form-control">
			</div>

			<div class="form-group">
				<label for="repeat-password" class="control-label">Repeat password</label>
				<input type="password" name="confirm" class="form-control">
			</div>

			<button type="submit" name="action" value="install" class="btn btn-primary">Next</button>
		</form>
		<?php
	}

	break;
}
?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</body>
</html>