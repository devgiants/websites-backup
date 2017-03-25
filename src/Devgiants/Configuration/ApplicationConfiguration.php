<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 18/02/17
 * Time: 07:26
 */

namespace Devgiants\Configuration;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ApplicationConfiguration implements ConfigurationInterface
{
    const ROOT_NODE = 'configuration';
    const NODE_NAME = 'name';
    const NODE_DEFAULT_VALUE = 'default_value';

    const REMANENCE_NODE = [
        self::NODE_NAME => 'remanence',
        self::NODE_DEFAULT_VALUE => 5
    ];

    const SITES = 'sites';
    const BACKUP_STORAGES = 'backup_storages';

    const PRE_SAVE_COMMANDS = 'pre_save_commands';
    const POST_SAVE_COMMANDS = 'post_save_commands';

    const DATABASE = 'database';
    const NAME = 'name';

    const SERVER = 'server';
    const USER = 'user';
    const PASSWORD = "password";
    
    const DATABASE_SERVER = [
        self::NODE_NAME => self::SERVER,
        self::NODE_DEFAULT_VALUE => 'localhost'
    ];
    

    const FILES = 'files';

    const ROOT_DIR = 'root_dir';
    const FOLDERS_TO_INCLUDE = 'include';
    const FOLDERS_TO_EXCLUDE = 'exclude';

    const FTP = 'FTP';
    const SSH = 'SSH';
    const AUTHORIZED_STORAGE = [
        self::FTP,
        self::SSH
    ];

    const STORAGE_TYPE = 'type';

    /* FTP specific */
    const SSL = 'ssl';
    const PASSIVE = 'passive';
    const TRANSFER = 'transfer';


    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root(static::ROOT_NODE);
        
        // add node definitions to the root of the tree
        $rootNode
            ->children()
                ->scalarNode(static::REMANENCE_NODE[static::NODE_NAME])
                    ->defaultValue(static::REMANENCE_NODE[static::NODE_DEFAULT_VALUE])
                    ->info('Contains the backup folders max value to keep on defined storages')
                ->end()
                ->arrayNode(static::SITES)
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->arrayNode(static::PRE_SAVE_COMMANDS)
                                ->requiresAtLeastOneElement()
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode(static::DATABASE)
                                ->children()
                                    ->scalarNode(static::DATABASE_SERVER[static::NODE_NAME])
                                        ->defaultValue(static::DATABASE_SERVER[static::NODE_DEFAULT_VALUE])
                                        ->info('Contains the MySQL database server IP or domain name. default is localhost')
                                    ->end()
                                    ->scalarNode(static::USER)
                                        ->info('Contains the MySQL user to connect to database with')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode(static::PASSWORD)
                                        ->info('Contains password for MySQL user')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode(static::NAME)
                                        ->info('Contains database name')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode(static::FILES)
                                ->children()
                                    ->scalarNode(static::ROOT_DIR)
                                        ->info('Contains the root directory for backup')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->arrayNode(static::FOLDERS_TO_INCLUDE)
                                        ->requiresAtLeastOneElement()
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->arrayNode(static::FOLDERS_TO_EXCLUDE)
                                        ->requiresAtLeastOneElement()
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode(static::POST_SAVE_COMMANDS)
                                ->requiresAtLeastOneElement()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode(static::BACKUP_STORAGES)
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            // TODO add checks if true to contextualize options with storage type
                            ->enumNode(static::STORAGE_TYPE)
                                ->info('Contains the storage type for backup')
                                ->values(static::AUTHORIZED_STORAGE)
                            ->end()
                            ->booleanNode(static::SSL)
                                ->info('For FTP connections, decides if connection is STP or regular FTP. Default regular.')
                                ->defaultFalse()
                            ->end()
                            ->booleanNode(static::PASSIVE)
                                ->info('For FTP connections, decides if transfer mode is passive or not. Default passive')
                                ->defaultTrue()
                            ->end()
                            ->integerNode(static::TRANSFER)
                                ->info('For FTP connections, decides transfer type. Default FTP_BINARY')
                                ->defaultValue(FTP_BINARY)
                            ->end()
                            ->scalarNode(static::SERVER)
                                ->info('Contains the server to connect with')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode(static::USER)
                                ->info('Contains the user to connect server with')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode(static::PASSWORD)
                                ->info('Contains the password to connect server with')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode(static::ROOT_DIR)
                                ->info('Contains the root dir on remote server to start with')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                ->end()
        ;

        return $treeBuilder;
    }
}