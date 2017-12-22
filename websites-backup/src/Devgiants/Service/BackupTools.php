<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 26/03/17
 * Time: 15:31
 */

namespace Devgiants\Service;


use Devgiants\Configuration\ApplicationConfiguration;
use Devgiants\Model\StorageInterface;
use hanneskod\classtools\Iterator\ClassIterator;
use Monolog\Logger;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class BackupTools
{
    /**
     * Return all protocols available, with key type and value full class path
     * @return array $availableProtocols all protocols available, with key type and value full class path
     */
    public function getAvailableStorages() {
        $availableStorages = [];

        $finder = new Finder();
        $iterator = new ClassIterator($finder->in( __DIR__ . "/../Storage" ));

        foreach ($iterator->getClassMap() as $classname => $splFileInfo) {
            // Check all protocols implements StorageInterface
            if(!in_array(StorageInterface::class, class_implements($classname))) {
                throw new Exception("All protocols must implements StorageInterface : $classname is not.");
            }
            $availableStorages[call_user_func("$classname::getType")] = $classname;
        }

        return $availableStorages;
    }

    /**
     * @param array $storageParams
     *
     * @return StorageInterface
     */
    public function getStorageByType(array $storageParams) {
        $availableStorages = $this->getAvailableStorages();
        // use the required protocol, and raise exception if inexistant
        if(!isset($availableStorages[$storageParams[ApplicationConfiguration::STORAGE_TYPE]])) {
            throw new Exception("Storage \"{$storageParams[ApplicationConfiguration::STORAGE_TYPE]}\" unavailable");
        }

        /**
         * @var StorageInterface
         */
        return new $availableStorages[$storageParams[ApplicationConfiguration::STORAGE_TYPE]]($storageParams);

    }

	/**
	 * @param OutputInterface $output
	 * @param Logger $log
	 * @param \Exception $e
	 */
    public function maximumDetailsErrorHandling(OutputInterface $output, Logger $log, \Exception $e) {
	    $output->writeln( "<error>Error happened : {$e->getMessage()}</error>" );
	    $log->addError( "Error", [
		    'message' => $e->getMessage(),
		    'code'    => $e->getCode(),
		    'file'    => $e->getFile(),
		    'line'    => $e->getLine(),
		    'stack'   => $e->getTraceAsString()
	    ] );
    }
}