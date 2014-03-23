<?php
/**
 * Administration module model for Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 * @link		http://thoughtmonster.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class Module extends AppModel {
	public $modules = array('Database', 'File');

	public function get($id) {
		$db = new Database;
		$module = $db->where('id', $id)->get('modules');
		if (empty($module)) {
			return null;
		}

		$module = reset($module);
		$types = $db->get('data');

		$db->select('module_data.id', 'alias', 'data_id', 'order', 'name', 'data.type');
		$db->join('data', 'data_id = data.id');
		$db->orderBy('order', 'ASC');
		$data = $db->where('module_id', $id)->get('module_data');

		if (!empty($data)) {
			foreach ($data as $i => $d) {
				$d->types = $types;
				$d->alias = (isset($d->alias)) ? $d->alias : "";

				foreach ($d->types as $c => $t) {
					$type = clone $t;
					if ($d->type == $type->type) {
						$type->selected = true;
					}

					$d->types[$c] = $type;
				}
			}

			$module->data = $data;
		}

		if (!empty($module)) {
			if (empty($module->alias)) {
				$module->alias = $module->name;
			}

			return $module;
		}

		return null;
	}

	public function save($data, $id = null) {
		$db = new Database;

		$module_info = array(
			'alias'	=> $data['alias'],
			'icon'	=> $data['icon']
		);

		if (!empty($id)) {
			$module = reset($db->where('id', $id)->get('modules'));
			if (empty($module)) {
				return null;
			} else if ($module->essential != 0) {
				unset($data['data']);
			} else {
				$tmp = $db->where('module_id', $id)->get('module_data');
				foreach ($tmp as $d) {
					$module->data[$d->id] = true;
				}
			}

			$module_info['name'] = $module->name;
			$db->where('id', $id);
		} else {
			$module_info['name'] = Inflector::tableize($data['name']);
		}

		$result = $db->put('modules', $module_info);
		if (empty($id) && $result == 0) {
			return null;
		} else if (empty($id) && $result > 0) {
			$id = $result;
		}

		// Process data providers.
		if (!empty($data['data'])) {
			$data['data'] = array_values($data['data']);
			foreach ($data['data'] as $i => $type) {
				if (isset($type['id'])) {
					$db->where('id', $type['id'])->put('module_data', array(
						'alias'		 => $type['alias'],
						'order'		 => $i
					));

					unset($module->data[$type['id']]);
				} else {
					if (strlen($type['name']) === 0) {
						continue;
					}

					$type['id'] = $db->put('module_data', array(
						'module_id'	 => $id,
						'data_id'	 => $type['type'],
						'name'		 => Inflector::normalize($type['name'], '_'),
						'alias'		 => $type['alias'],
						'order'		 => $i
					));

					$clients = $db->select('client_id')->distinct()->where('module_id', $id)->get('client_modules');
					foreach ($clients as $c) {
						$client = reset($db->where('id', $c->client_id)->get('clients'));
						$dtype = reset($db->where('id', $type['type'])->get('data'));

						$dtype = Inflector::title($dtype->type).'Provider';
						if (!class_exists($dtype) && !include(APP_DIR.'model/providers/'.$dtype.'.php')) {
							Exceptions::log('Couldn\'t find type provider in `providers/'.$dtype.'.php`.');
							Exceptions::error();
						}

						$provider = new $dtype;

						foreach ($provider->modules as $module) {
							$name = Inflector::title($module);
							$provider->$name =& Sleepy::load($name, 'modules');
						}

						$user = new User($client->auth_id);
						$db_name = $user->options('database', 'database', 'name');
						$provider->create($db_name, $module_info['name'], Inflector::normalize($type['name'], '_'));
					}
				}

				if (!empty($type['options'])) {
					$db->where('data_id', $type['id'])->delete('module_options');
					foreach ($type['options'] as $option => $value) {
						$db->put('module_options', array(
							'data_id' => $type['id'],
							'option'  => $option,
							'value'   => $value
						));
					}
				}
			}
		}

		// Remove remaining data providers.
		if (!empty($module->data)) {
			foreach ($module->data as $i => $d) {
				$db->where('id', $i)->delete('module_data');
				$db->where('data_id', $i)->delete('module_options');
				$db->where('data_id', $i)->delete('client_module_data');
			}
		}

		return $id;
	}

	public function remove($id) {
		$db = new Database;
		$module = reset($db->where('id', $id)->get('modules'));
		if (empty($module) || $module->essential != 0) {
			return false;
		}

		$db->where('id', $id)->delete('modules');

		$data = $db->where('module_id', $id)->get('module_data');
		foreach ((array) $data as $d) {
			$db->where('data_id', $d->id)->delete('module_options');
		}

		$db->where('module_id', $id)->delete('module_data');

		return true;
	}

	public function validate($client, $user) {
		$params = Dispatcher::params(false);

		// Administrator is allowed access to Admin Panel.
		if ($client === 1 && $user === 1 && $params['controller'] === 'panel') {
			return true;
		}

		$modules = $this->getModules($client, $user);
		if (!empty($modules)) {
			foreach ($modules as $module) {
				if ($module->name == $params['controller']) {
					return true;
				}
			}
		}

		return false;
	}

	public function getModules($client = null, $user = null) {
		$db = new Database;
		$db_name = Sleepy::get('database', 'default');

		if ($client != null && $user != null) {
			$modules = $db->query('
				SELECT modules.id, name, icon, COALESCE(client_modules.alias, modules.alias) AS alias
				FROM '.$db_name.'.modules
					JOIN '.$db_name.'.client_modules
						ON client_modules.module_id = modules.id
						AND client_modules.client_id = ?
					JOIN '.$db_name.'.user_modules
						ON user_modules.client_module = client_modules.id
						AND user_modules.user_id = ?
					ORDER BY name ASC', array($client, $user)
			);
		} else if ($client != null && $user == null) {
			$modules = $db->query('
				SELECT modules.id, name, icon, COALESCE(client_modules.alias, modules.alias) AS alias
				FROM '.$db_name.'.modules
					JOIN '.$db_name.'.client_modules
						ON client_modules.module_id = modules.id
						AND client_modules.client_id = ?
					ORDER BY name ASC', array($client)
			);
		} else {
			$modules = $db->orderBy('name', 'ASC')->get('modules');
		}

		if (!empty($modules)) {
			foreach ($modules as $i => $module) {
				if (empty($modules[$i]->alias)) {
					$modules[$i]->alias = $module->name;
				}

				$modules[$i]->name = strtolower($module->name);
			}
		}

		return $modules;
	}

	public function getTypes() {
		$db = new Database;
		$types = $db->get('data');
		return $types;
	}

	public function getOptions($type, $data_id = null) {
		$db = new Database;
		$type = Inflector::title($type).'Provider';
		if (!class_exists($type) && !include(APP_DIR.'model/providers/'.$type.'.php')) {
			Exceptions::log('Couldn\'t find type provider in `providers/'.$type.'.php`.');
			Exceptions::error();
		}

		$provider = new $type;

		foreach ($provider->modules as $mod) {
			$name = Inflector::title($mod);
			$provider->$name =& Sleepy::load($name, 'modules');
		}

		$data = $provider->options();

		if ($data_id != null) {
			$tmp = $db->where('data_id', $data_id)->get('module_options');

			if (!empty($tmp)) {
				foreach ($tmp as $option) {
					$defaults[$option->option] = $option->value;
				}
			}
		}

		$i = 0;
		$options = array();
		foreach ($data as $type => $value) {
			switch (gettype($value['value'])) {
			case 'array':
				$options[$i] = array(
					'select' => true,
					'label'	 =>	$value['label'],
					'name'	 =>	$type
				);

				$c = 0;
				foreach ($value['value'] as $label => $option) {
					$options[$i]['options'][$c] = array(
						'value' => $option,
						'label'	=> (is_string($label)) ? $label : $option
					);

					if (isset($defaults[$type]) && $option == $defaults[$type]) {
						$options[$i]['options'][$c]['selected'] = 'selected';
					}

					$c++;
				}

				break;
			case 'integer':
			case 'double':
			case 'string':
				$options[$i] = array(
					'input'	 => true,
					'label'	 =>	$value['label'],
					'name'	 =>	$type
				);

				if (isset($defaults[$type])) {
					$options[$i]['value'] = $defaults[$type];
				} else {
					$options[$i]['value'] = $value['value'];
				}

				break;
			case 'boolean':
				$options[$i] = array(
					'checkbox'	=>	true,
					'label'		=>	$value['label'],
					'name'		=>	$type
				);

				if (isset($defaults[$type]) && $defaults[$type] == true) {
					$options[$i]['checked'] = 'checked';
				} else if ($value['value'] == true) {
					$options[$i]['checked'] = 'checked';
				}

				break;
			default:
				continue;
			}

			$i++;
		}

		return $options;
	}

	public function getIcons() {
		return array(
			'glass', 'music', 'search', 'envelope-o', 'heart', 'star', 'star-o', 'user', 'film', 'th-large', 'th',
			'th-list', 'check', 'times', 'search-plus', 'search-minus', 'power-off', 'signal', 'cog', 'trash-o',
			'home', 'file-o', 'clock-o', 'road', 'download', 'arrow-circle-o-down', 'arrow-circle-o-up', 'inbox',
			'play-circle-o', 'repeat', 'refresh', 'list-alt', 'lock', 'flag', 'headphones', 'volume-off',
			'volume-down', 'volume-up', 'qrcode', 'barcode', 'tag', 'tags', 'book', 'bookmark', 'print', 'camera',
			'font', 'bold', 'italic', 'text-height', 'text-width', 'align-left', 'align-center', 'align-right',
			'align-justify', 'list', 'outdent', 'indent', 'video-camera', 'picture-o', 'pencil', 'map-marker',
			'adjust', 'tint', 'pencil-square-o', 'share-square-o', 'check-square-o', 'arrows', 'step-backward',
			'fast-backward', 'backward', 'play', 'pause', 'stop', 'forward', 'fast-forward', 'step-forward',
			'eject', 'chevron-left', 'chevron-right', 'plus-circle', 'minus-circle', 'times-circle', 'check-circle',
			'question-circle', 'info-circle', 'crosshairs', 'times-circle-o', 'check-circle-o', 'ban', 'arrow-left',
			'arrow-right', 'arrow-up', 'arrow-down', 'share', 'expand', 'compress', 'plus', 'minus', 'asterisk',
			'exclamation-circle', 'gift', 'leaf', 'fire', 'eye', 'eye-slash', 'exclamation-triangle', 'plane',
			'calendar', 'random', 'comment', 'magnet', 'chevron-up', 'chevron-down', 'retweet', 'shopping-cart',
			'folder', 'folder-open', 'arrows-v', 'arrows-h', 'bar-chart-o', 'twitter-square', 'facebook-square',
			'camera-retro', 'key', 'cogs', 'comments', 'thumbs-o-up', 'thumbs-o-down', 'star-half', 'heart-o',
			'sign-out', 'linkedin-square', 'thumb-tack', 'external-link', 'sign-in', 'trophy', 'github-square',
			'upload', 'lemon-o', 'phone', 'square-o', 'bookmark-o', 'phone-square', 'twitter', 'facebook',
			'github', 'unlock', 'credit-card', 'rss', 'hdd-o', 'bullhorn', 'bell', 'certificate', 'hand-o-right',
			'hand-o-left', 'hand-o-up', 'hand-o-down', 'arrow-circle-left', 'arrow-circle-right', 'arrow-circle-up',
			'arrow-circle-down', 'globe', 'wrench', 'tasks', 'filter', 'briefcase', 'arrows-alt', 'users', 'link',
			'cloud', 'flask', 'scissors', 'files-o', 'paperclip', 'floppy-o', 'square', 'bars', 'list-ul', 'list-ol',
			'strikethrough', 'underline', 'table', 'magic', 'truck', 'pinterest', 'pinterest-square',
			'google-plus-square', 'google-plus', 'money', 'caret-down', 'caret-up', 'caret-left', 'caret-right',
			'columns', 'sort', 'sort-asc', 'sort-desc', 'envelope', 'linkedin', 'undo', 'gavel', 'tachometer',
			'comment-o', 'comments-o', 'bolt', 'sitemap', 'umbrella', 'clipboard', 'lightbulb-o', 'exchange',
			'cloud-download', 'cloud-upload', 'user-md', 'stethoscope', 'suitcase', 'bell-o', 'coffee', 'cutlery',
			'file-text-o', 'building-o', 'hospital-o', 'ambulance', 'medkit', 'fighter-jet', 'beer', 'h-square',
			'plus-square', 'angle-double-left', 'angle-double-right', 'angle-double-up', 'angle-double-down',
			'angle-left', 'angle-right', 'angle-up', 'angle-down', 'desktop', 'laptop', 'tablet', 'mobile',
			'circle-o', 'quote-left', 'quote-right', 'spinner', 'circle', 'reply', 'github-alt', 'folder-o',
			'folder-open-o', 'smile-o', 'frown-o', 'meh-o', 'gamepad', 'keyboard-o', 'flag-o', 'flag-checkered',
			'terminal', 'code', 'reply-all', 'mail-reply-all', 'star-half-o', 'location-arrow', 'crop',
			'code-fork', 'chain-broken', 'question', 'info', 'exclamation', 'superscript', 'subscript', 'eraser',
			'puzzle-piece', 'microphone', 'microphone-slash', 'shield', 'calendar-o', 'fire-extinguisher',
			'rocket', 'maxcdn', 'chevron-circle-left', 'chevron-circle-right', 'chevron-circle-up',
			'chevron-circle-down', 'html5', 'css3', 'anchor', 'unlock-alt', 'bullseye', 'ellipsis-h', 'ellipsis-v',
			'rss-square', 'play-circle', 'ticket', 'minus-square', 'minus-square-o', 'level-up', 'level-down',
			'check-square', 'pencil-square', 'external-link-square', 'share-square', 'compass', 'caret-square-o-down',
			'caret-square-o-up', 'caret-square-o-right', 'eur', 'gbp', 'usd', 'inr', 'jpy', 'rub', 'krw', 'btc',
			'file', 'file-text', 'sort-alpha-asc', 'sort-alpha-desc', 'sort-amount-asc', 'sort-amount-desc',
			'sort-numeric-asc', 'sort-numeric-desc', 'thumbs-up', 'thumbs-down', 'youtube-square', 'youtube',
			'xing', 'xing-square', 'youtube-play', 'dropbox', 'stack-overflow', 'instagram', 'flickr', 'adn',
			'bitbucket', 'bitbucket-square', 'tumblr', 'tumblr-square', 'long-arrow-down', 'long-arrow-up',
			'long-arrow-left', 'long-arrow-right', 'apple', 'windows', 'android', 'linux', 'dribbble', 'skype',
			'foursquare', 'trello', 'female', 'male', 'gittip', 'sun-o', 'moon-o', 'archive', 'bug', 'vk',
			'weibo', 'renren', 'pagelines', 'stack-exchange', 'arrow-circle-o-right', 'arrow-circle-o-left',
			'caret-square-o-left', 'dot-circle-o', 'wheelchair', 'vimeo-square', 'try', 'plus-square-o'
		);
	}

	public function getItem($module, $client, $id = null) {
		$db = new Database;
		$db_name = Sleepy::get('database', 'default');
		$meta = $db->query("
			SELECT module_data.id, module_data.name, COALESCE(client_module_data.alias, module_data.alias) AS alias, data.type, `order`
			FROM {$db_name}.module_data
				JOIN {$db_name}.data ON data_id = data.id
				LEFT JOIN {$db_name}.client_module_data 
					ON client_module_data.client_id = ? AND client_module_data.data_id = module_data.id
				WHERE {$db_name}.module_data.module_id = 
					(SELECT id FROM {$db_name}.modules WHERE name = ?)
				AND module_data.id NOT IN
					(SELECT data_id FROM {$db_name}.client_module_data WHERE client_id = ? AND status = true)
				ORDER BY data.type ASC, `order` ASC
			", array($client, $module, $client)
		);

		// Process provider options.
		foreach ($meta as $i => $column) {
			$db->where('data_id', $column->id)->or();
		}

		$options = $db->get("{$db_name}.module_options");
		foreach ($meta as $item) {
			foreach ($options as $opt) {
				if ($item->id == $opt->data_id) {
					$item->options[$opt->option] = $opt->value;
				}
			}

			if ($item->type == 'category') {
				$type = Inflector::title($item->type).'Provider';
				if (!class_exists($type) && !include(APP_DIR.'model/providers/'.$type.'.php')) {
					Exceptions::log('Couldn\'t find type provider in `providers/'.$type.'.php`.');
					Exceptions::error();
				}

				$provider = new $type;
				$item->categories = $provider->categories($module);
			}
		}

		// Process item data.
		if (!empty($id)) {
			$item = reset($db->select('id')->where('id', $id)->get($module));

			foreach ($meta as $column) {
				$type = Inflector::title($column->type).'Provider';
				if (!class_exists($type) && !include(APP_DIR.'model/providers/'.$type.'.php')) {
					Exceptions::log('Couldn\'t find type provider in `providers/'.$type.'.php`.');
					Exceptions::error();
				}

				$provider = new $type;

				foreach ($provider->modules as $mod) {
					$name = Inflector::title($mod);
					$provider->$name =& Sleepy::load($name, 'modules');
				}

				$data[$column->name] = $provider->get($module, $column->name, $id);
			}

			$data['id'] = $item->id;

			return array(
				'data'	=>	(object) $data,
				'meta'	=>	$meta
			);
		}

		return array('meta' => $meta);
	}

	public function saveItem($data, $module, $id = null) {
		$db = new Database;
		$db_name = Sleepy::get('database', 'default');
		$module_data = $db->query('
			SELECT module_data.name, data.type
			FROM '.$db_name.'.module_data
				JOIN '.$db_name.'.data ON data_id = data.id
				WHERE '.$db_name.'.module_data.module_id = 
					(SELECT id FROM '.$db_name.'.modules WHERE name = ?)
			', array($module)
		);

		foreach ($module_data as $type) {
			$types[$type->name] = $type->type;
		}

		if (!is_numeric($id)) {
			$id = $db->put($module, array('id' => 'DEFAULT'));
			$db->where('id', $id)->put($module, array('order' => $id));
		}

		foreach (array_keys($data) as $column) {
			$type = Inflector::title($types[$column]).'Provider';
			if (!class_exists($type) && !include(APP_DIR.'model/providers/'.$type.'.php')) {
				Exceptions::log('Couldn\'t find type provider in `providers/'.$type.'.php`.');
				Exceptions::error();
			}

			$provider = new $type;

			foreach ($provider->modules as $mod) {
				$name = Inflector::title($mod);
				$provider->$name =& Sleepy::load($name, 'modules');
			}

			$provider->save($module, $column, $id, $data[$column]);
		}

		return $id;
	}

	public function removeItem($module, $id) {
		$db = new Database;
		$db_name = Sleepy::get('database', 'default');
		$module_data = $db->query('
			SELECT module_data.name, data.type
			FROM '.$db_name.'.module_data
				JOIN '.$db_name.'.data ON data_id = data.id
				WHERE '.$db_name.'.module_data.module_id = 
					(SELECT id FROM '.$db_name.'.modules WHERE name = ?)
			', array($module)
		);

		foreach ($module_data as $type) {
			if ($type->type === 'file') {
				if (!class_exists('FileProvider') && !include(APP_DIR.'model/providers/FileProvider.php')) {
					Exceptions::log('Couldn\'t find type provider in `providers/FileProvider.php`.');
					Exceptions::error();
				}

				$provider = new FileProvider;
				foreach ($provider->modules as $mod) {
					$name = Inflector::title($mod);
					$provider->$name =& Sleepy::load($name, 'modules');
				}

				$provider->remove($module, $type->name, $id);
			}
		}

		$table = Inflector::tableize($module.'_categories');
		$db->where('item_id', $id)->delete($table);

		$result = $db->where('id', $id)->delete($module);
		if ($result > 0) {
			return true;
		}

		return false;
	} 

	public function listItems($module, $offset = 0, $search = '%') {
		$db = new Database;

		// Determine our required fields.
		$db_name = Sleepy::get('database', 'default');
		$query = "SELECT module_data.name FROM {$db_name}.module_data
					INNER JOIN {$db_name}.modules ON modules.name = ? AND module_data.module_id = modules.id
					INNER JOIN {$db_name}.data ON data_id = data.id AND type = ?
					ORDER BY module_data.`order` ASC
					LIMIT 1";

		$secondary = array();
		$title = $db->query($query, array($module, 'string'));
		if (!empty($title)) {
			$title = reset(reset($title));
			$secondary[] = "`{$title}` AS title";
		}

		$date = $db->query($query, array($module, 'date'));
		if (!empty($date)) {
			$date = reset(reset($date));
			$secondary[] = "DATE_FORMAT(`{$date}`, '%d/%m/%Y %H:%i') AS date";
		}

		$module = Inflector::tableize($module);
		$secondary = implode(', ', $secondary);
		$items = $db->query("
			SELECT `id`, `order`, {$secondary}
				FROM `{$module}`
				WHERE {$title} LIKE ?
				ORDER BY `order` DESC
				LIMIT {$offset}, 50", array($search));

		$count = (int) reset(reset($db->query("SELECT COUNT(*) FROM {$module} WHERE {$title} LIKE ?", $search)));

		return (object) array(
			'items'		=>	$items,
			'page'		=>	(object) array(
				'total'		=>	$count,
				'offset'	=>	$offset,
				'limit'		=>	50
			)
		);
	}

	public function orderItems($module, $items) {
		$db = new Database;

		$i = 0;
		$min = min($items);
		foreach (array_reverse($items, true) as $id => $order) {
			$db->where('id', $id)->put($module, array('order' => $min + $i));
			$i++;
		}

		return true;
	}
}

/* End of file Module.php */