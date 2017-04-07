<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 07/04/17
 * Time: 16:55
 */

$files = scandir(dirname(__FILE__) . "/downloads", 1);
$lastVersionFile = array_shift($files);

$data = explode('-', pathinfo($lastVersionFile, PATHINFO_FILENAME));
echo $data[count($data)-1];
