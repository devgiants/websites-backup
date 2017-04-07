<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 26/03/17
 * Time: 15:31
 */

namespace Devgiants\Service;


use Devgiants\Configuration\ApplicationConfiguration;
use Devgiants\Model\ProtocolInterface;
use hanneskod\classtools\Iterator\ClassIterator;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Finder\Finder;

class BackupTools
{
    /**
     * Return all protocols available, with key type and value full class path
     * @return array $availableProtocols all protocols available, with key type and value full class path
     */
    public function getAvailableProtocols() {
        $availableProtocols = [];

        $finder = new Finder();
        $iterator = new ClassIterator($finder->in(__DIR__ . "/../Protocol"));

        foreach ($iterator->getClassMap() as $classname => $splFileInfo) {
            // Check all protocols implements ProtocolInterface
            if(!in_array(ProtocolInterface::class, class_implements($classname))) {
                throw new Exception("All protocols must implements ProtocolInterface : $classname is not.");
            }
            $availableProtocols[call_user_func("$classname::getType")] = $classname;
        }

        return $availableProtocols;
    }

    /**
     * @param array $storageParams
     * @return ProtocolInterface
     */
    public function getProtocolByType(array $storageParams) {
        $availableProtocols = $this->getAvailableProtocols();
        // use the required protocol, and raise exception if inexistant
        if(!isset($availableProtocols[$storageParams[ApplicationConfiguration::STORAGE_TYPE]])) {
            throw new Exception("Protocol \"{$storageParams[ApplicationConfiguration::STORAGE_TYPE]}\" unavailable");
        }

        /**
         * @var ProtocolInterface
         */
        return new $availableProtocols[$storageParams[ApplicationConfiguration::STORAGE_TYPE]]($storageParams);

    }
}