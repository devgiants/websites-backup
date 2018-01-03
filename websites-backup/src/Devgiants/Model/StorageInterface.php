<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 14/02/17
 * Time: 03:05
 */

namespace Devgiants\Model;


use Symfony\Component\Config\Definition\Exception\Exception;

interface StorageInterface
{
    const REMANENCE = 5;
    /**
     * @throws Exception
     */
    public function connect();

    /**
     * @param string $localPath
     * @param string $remotePath relative remote path to store file to
     * @param array $params params contains additional informations, such as root_dir, in case Storage works by absolute path
     * @return bool
     */
    public function put($localPath, $remotePath, array $params = null);

    /**
     * @param string $remotePath relative remote path to get files from
     * @param array $params params contains additional informations, such as root_dir, in case Storage works by absolute path
     * @return array
     */
    public function getItemsList($remotePath, array $params = null);

    /**
     * @param string $remotePath relative remote path to get file from
     * @param string $localPath
     * @param array $params params contains additional informations, such as root_dir, in case Storage works by absolute path
     * @return bool
     */
    public function get($remotePath, $localPath, array $params = null);

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

    /**
     * @param int $retention
     * @param array $params params contains additional informations, such as root_dir, in case Storage works by absolute path
     * @return mixed
     */
    public function handleRetention(int $retention, array $params = null);

    /**
     * @return mixed
     */
    public function close();

    /**
     * @return string the protocol type
     */
    public static function getType();
}