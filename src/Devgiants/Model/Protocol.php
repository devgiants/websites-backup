<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 14/02/17
 * Time: 03:05
 */

namespace Devgiants\Model;


interface Protocol
{
    /**
     * @return bool
     */
    public function connect();

    /**
     * @param string $localPath
     * @param string $remotePath
     * @return bool
     */
    public function put($localPath, $remotePath);
    
    public function get();

    /**
     * @param $path
     * @param bool $recursive
     * @return mixed
     */
    public function delete($path, $recursive = true);

    /**
     * @param $path string the path to create
     * @param $recursive bool
     * @return bool
     */
    public function makeDir($path, $recursive = true);

    public function close();
}