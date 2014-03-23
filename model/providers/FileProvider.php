<?php
/**
 * File data type provider for Cecil administration module.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 *
 * @copyright	Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 * @link		http://thoughtmonster.org/cecil
 * @package		Cecil.Core
 * @since		Cecil 0.1.0
 */

class FileProvider {
	public $modules = array('Database', 'File', 'Image');

	public function get($module, $column, $id) {
		$db = new Database;

		$table = Inflector::tableize($module.'_'.$column);
		$db->select('files.*')->where("{$table}.item_id", $id);
		$db->join($table, "{$table}.file_id = files.id", 'right');
		$result = $db->orderBy('files.order', 'ASC')->get('files');

		foreach ((array) $result as $i => $image) {
			$result[$i]->extension = pathinfo($result[$i]->filename, PATHINFO_EXTENSION);
			$result[$i]->url = (string) new File($image->checksum);

			switch ($result[$i]->extension) {
			case 'jpg': case 'jpeg': case 'png':
				$img = new Image($result[$i]->url);
				$result[$i]->thumb = (string) $img->resize(200, 200);
				break;
			}
			
			unset($result[$i]->checksum);
		}

		return $result;
	}

	public function save($module, $column, $id, $data) {
		$db = new Database;

		$counts = $db->query('SELECT COUNT(*) AS c, checksum FROM files GROUP BY checksum');
		foreach ((array) $counts as $count) {
			$dups[$count->checksum] = ($count->c > 1) ? true : false;
		}

		$order = 0;
		$files = $this->get($module, $column, $id);

		// Process existing files.
		foreach ((array) $files as $file) {
			$removed = true;
			if (isset($data['old'])) {
				foreach ($data['old'] as $order => $old_id) {
					if ($file->id == $old_id) {
						$db->where('id', $old_id)->put('files', array(
							'order' => $order
						));

						$removed = false;
						break;
					}
				}
			}

			if ($removed == true) {
				$db->where('id', $file->id);
				$t = reset($db->get('files'));

				if ($dups[$t->checksum] == false) {
					$f = new File($t->checksum);
					$f->delete();
				}

				$table = Inflector::tableize($module.'_'.$column);
				$db->where('item_id', $id, 'file_id', $file->id)->delete($table);

				$db->where('id', $file->id);
				$db->delete('files');

			}
		}

		// Add new files.
		foreach ($data['error'] as $i => $error) {
			if ($error != 0) {
				continue;
			}

			move_uploaded_file($data['tmp_name'][$i], APP_DIR.'/tmp/'.$data['name'][$i]);
			$result = new File(APP_DIR.'/tmp/'.$data['name'][$i]);
			if ($result == null) {
				unlink(APP_DIR.'/tmp/'.$data['name'][$i]);
				return;
			}

			$order++;

			$file_id = $db->put('files', array(
				'checksum'	 =>	sha1_file(APP_DIR.'/tmp/'.$data['name'][$i]),
				'filename'	 =>	$data['name'][$i],
				'order'		 =>	$order
			));

			$db->put(Inflector::tableize($module.'_'.$column), array(
				'item_id'	=>	$id,
				'file_id'	=>	$file_id
			));

			unlink(APP_DIR.'/tmp/'.$data['name'][$i]);
		}
	}

	public function remove($module, $column, $id) {
		$db = new Database;

		$table = Inflector::tableize($module.'_'.$column);
		$db->select('files.*')->join('files', "files.id = {$table}.file_id", 'left');
		$files = $db->where('item_id', $id)->get($table);

		// Delete entries from junction table.
		$db->where('item_id', $id)->delete($table);

		// Delete file entries from database and the filesystem, if no duplicates exist.
		if (!empty($files)) {
			$counts = $db->query('SELECT COUNT(*) AS count, checksum FROM files GROUP BY checksum');
			foreach ($counts as $c) {
				$dups[$c->checksum] = ($c->count > 1) ? true : false;
			}

			foreach ($files as $file) {
				if ($dups[$file->checksum] == false) {
					$f = new File($file->checksum);
					$f->delete();
				}

				$db->where('id', $file->id)->or();
			}

			$db->delete('files');
		}
	}

	public function create($database, $table, $field) {
		$db = new Database;

		$db->query(
			'CREATE TABLE IF NOT EXISTS '.$database.'.files (
				`id` INT PRIMARY KEY AUTO_INCREMENT,
				`checksum` CHAR(40) NOT NULL,
				`filename` VARCHAR(255) NOT NULL,
				`title` VARCHAR(255),
				`order` SMALLINT UNSIGNED
			)'
		);

		$filetable = Inflector::tableize($table.'_'.$field);
		$db->query(
			'CREATE TABLE IF NOT EXISTS '.$database.'.'.$filetable.' (
				`item_id` INT NOT NULL,
				`file_id` INT NOT NULL,
				FOREIGN KEY (`item_id`) REFERENCES '.$database.'.'.$table.'(id),
				FOREIGN KEY (`file_id`) REFERENCES '.$database.'.files(id),
				PRIMARY KEY (`item_id`, `file_id`)
			)'
		);
	}


	public function options() {
		return array(
			'type'	=>	array(
				'label'	=>	'File type',
				'value'	=>	array('Generic' => 'generic', 'Image' => 'image')
			),
			'required'	=>	array(
				'label'		=>	'Required',
				'value'		=>	false
			)
		);
	}
}

// End of file FileProvider.php
