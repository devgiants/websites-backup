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

    const DATABASE_SERVER = [
        self::NODE_NAME => 'server',
        self::NODE_DEFAULT_VALUE => 'localhost'
    ];

    const DATABASE_USER = 'user';
    const DATABASE_PASSWORD = "password";

    const FILES = 'files';

    const ROOT_DIR = 'root_dir';
    const FOLDERS_TO_INCLUDE = 'include';
    const FOLDERS_TO_EXCLUDE = 'exclude';


    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root(self::ROOT_NODE);
        
        // add node definitions to the root of the tree
        $rootNode
            ->children()
                ->scalarNode(self::REMANENCE_NODE[self::NODE_NAME])
                    ->defaultValue(self::REMANENCE_NODE[self::NODE_DEFAULT_VALUE])
                    ->info('Contains the backup folders max value to keep on defined storages')
                ->end()
                ->arrayNode(self::SITES)
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->arrayNode(self::PRE_SAVE_COMMANDS)
                                ->requiresAtLeastOneElement()
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode(self::DATABASE)
                                ->children()
                                    ->scalarNode(self::DATABASE_SERVER[self::NODE_NAME])
                                        ->defaultValue(self::DATABASE_SERVER[self::NODE_DEFAULT_VALUE])
                                        ->info('Contains the MySQL database server IP or domain name. default is localhost')
                                    ->end()
                                    ->scalarNode(self::DATABASE_USER)
                                        ->info('Contains the MySQL user to connect to database with')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode(self::DATABASE_PASSWORD)
                                        ->info('Contains password for MySQL user')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode(self::FILES)
                                ->children()
                                    ->scalarNode(self::ROOT_DIR)
                                        ->info('Contains the root directory for backup')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->arrayNode(self::FOLDERS_TO_INCLUDE)
                                        ->requiresAtLeastOneElement()
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->arrayNode(self::FOLDERS_TO_EXCLUDE)
                                        ->requiresAtLeastOneElement()
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode(self::POST_SAVE_COMMANDS)
                                ->requiresAtLeastOneElement()
                                ->prototype('scalar')->end()
                            ->end()
                    ->end()
                ->end()
                ->arrayNode(self::BACKUP_STORAGES)
                    ->isRequired()
//                    ->requiresAtLeastOneElement()
//                    ->prototype('array')
                    ->children()
//                        ->arrayNode(self::PRE_SAVE_COMMANDS)
                ->end()
        ;

        return $treeBuilder;
    }
}