<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 03/01/18
 * Time: 15:24
 */

namespace Devgiants\Model;


abstract class Storage implements StorageInterface {

	/**
	 * @param string $path
	 *
	 * @return string
	 */
	public function sanitizePath($path) {
		return preg_replace('#/+#','/',$path);
	}
}