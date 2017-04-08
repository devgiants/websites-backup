<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 05/02/17
 * Time: 14:32
 */
namespace Devgiants\Command;

use Devgiants\Configuration\ConfigurationManager;
use Devgiants\Configuration\ApplicationConfiguration;
use Pimple\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;

class BackupCommand extends Command
{
    const FILE_OPTION = "file";
    const ROOT_TEMP_PATH = "/tmp/website-backups/";
    const FILES = 'files';
    const TEMP_PATHS = [
        ApplicationConfiguration::DATABASE => self::ROOT_TEMP_PATH . "databases/",
        self::FILES => self::ROOT_TEMP_PATH . "files/"
    ];

    /**
     * @var Container
     */
    private $container;

    public function __construct($name, Container $container)
    {
        $this->container = $container;
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('backup')
            ->setDescription('Backup sites accordingly to the YML configuration file provided')
            ->setHelp("This command allows you to save sites, maily by saving databases and files you chose")
            ->addOption(self::FILE_OPTION, "f", InputOption::VALUE_REQUIRED, "The YML configuration file")
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get conf file
        $ymlFile = $input->getOption(self::FILE_OPTION);

        //TODO add logs

        if($ymlFile !== null && is_file($ymlFile)) {
            try {

                // Structures check and configuration loading
                $configurationManager = new ConfigurationManager($ymlFile);
                $configuration = $configurationManager->load();


                /*********************************************
                 * Temp paths
                 */
                foreach (self::TEMP_PATHS as $tempPath) {
                    if (!file_exists($tempPath)) {
                        $output->write("Create temp folders...");
                        // TODO Use Filesystem component
                        mkdir($tempPath, 0777, true);
                        $output->write("<info> DONE</info>" . PHP_EOL);
                    }
                }

                /*********************************************
                 * Backup
                 */
                $startTime = microtime();

                $output->writeln("Start sites backup");
                foreach ($configuration['sites'] as $site => $siteConfiguration) {
                    $output->writeln("<fg=black;bg=yellow> - Site {$site}</>");

                    $currentTimestamp = date('YmdHis');
                    /*********************************************
                     * Pre-save commands
                     */
                    if (count($siteConfiguration[ApplicationConfiguration::PRE_SAVE_COMMANDS]) > 0) {
                        $output->writeln("  - Start pre-save commands");
                        foreach ($siteConfiguration['pre_save_commands'] as $command) {
                            $output->writeln("   - Run \"{$command}\"");
                            exec($command);
                        }
                        $output->writeln("  - End pre-save commands");
                    }

                    /*********************************************
                     * Databases
                     */
                    if (isset($siteConfiguration['database'])) {
                        $output->writeln("<comment>  - Databases</comment>");
                        $output->write("   - Connection to database server...");
                        $mysqli = new \mysqli(
                                        $siteConfiguration[ApplicationConfiguration::DATABASE][ApplicationConfiguration::SERVER],
                                        $siteConfiguration[ApplicationConfiguration::DATABASE][ApplicationConfiguration::USER],
                                        $siteConfiguration[ApplicationConfiguration::DATABASE][ApplicationConfiguration::PASSWORD]);

                        // Handle connection failure
                        if ($mysqli->connect_errno) {
                            $output->writeln("<error>   - Connection failure : {$mysqli->connect_error}</error>");
                            // TODO logs + mails
                            exit();
                        }
                        $output->write("<info> DONE</info>" . PHP_EOL);
                        $dumpName = "{$site}_{$currentTimestamp}.sql.gz";
                        $dumpPath = self::TEMP_PATHS[ApplicationConfiguration::DATABASE] .$dumpName;

                        $output->write("   - Start database export and compression...");
                        exec("mysqldump --user={$siteConfiguration[ApplicationConfiguration::DATABASE][ApplicationConfiguration::USER]} --password='{$siteConfiguration[ApplicationConfiguration::DATABASE][ApplicationConfiguration::PASSWORD]}' --single-transaction {$siteConfiguration[ApplicationConfiguration::DATABASE][ApplicationConfiguration::NAME]} | gzip > $dumpPath");
                        $output->write("<info> DONE</info>" . PHP_EOL);
                    }
                    /*********************************************
                     * Files
                     */
                    if (isset($siteConfiguration['files'])) {
                        $output->writeln("<comment>  - Files</comment>");
                        if (is_dir($siteConfiguration['files']['root_dir'])) {
                            // Build and create temp site path. REmove it besfore if needed to be sure it's empty
                            $siteTempPath = self::TEMP_PATHS[self::FILES] . $site;
                            if(file_exists($siteTempPath)) {
                                // TODO find better
                                exec("rm -rf $siteTempPath");
                            }
                            mkdir($siteTempPath);

                            // Copy all included folders recursively to temp path
                            foreach ($siteConfiguration['files']['include'] as $includedItem) {
                                // Build target path to copy
                                $fullIncludedItemPath = "{$siteConfiguration['files']['root_dir']}{$includedItem}";

                                if (file_exists($fullIncludedItemPath)) {
                                    $output->write("   - Copying \"{$fullIncludedItemPath}\"...");
                                    // Normal files
                                    exec("cp -r $fullIncludedItemPath " . $siteTempPath);
                                    $output->write("<info> DONE</info>" . PHP_EOL);
                                } else {
                                    $output->writeln("<error>   - Error : \"{$fullIncludedItemPath}\" doesn't exist. Skip.</error>");
                                }
                            }
                            // TODO Remove all excluded files/folders
                            // Compress files
                            $archiveName = "{$site}_{$currentTimestamp}.tar.gz";
                            $archivePath = "{$siteTempPath}/{$archiveName}";
                            $archive = new \PharData($archivePath);
                            $archive->buildFromDirectory($siteTempPath);
                        } else {
                            $output->writeln("<error>   - Error : \"{$siteConfiguration['files']['root_dir']}\" is not a valid directory path. Skip.</error>");
                        }
                    }

                    /*********************************************
                     * Store on external storages
                     */
                    $output->writeln("<comment>  - Storage</comment>");

                    

                    foreach($configuration[ApplicationConfiguration::BACKUP_STORAGES] as $storage) {

                        $currentProtocol = $this->container['tools']->getProtocolByType($storage);
                        
                        // Connection
                        $output->write("   - Trying connection...");
                        $currentProtocol->connect();
                        $output->write("<info> CONNECTED</info>" . PHP_EOL);

                        $remoteDir = "{$storage['root_dir']}/{$site}/{$currentTimestamp}/";
                        $currentProtocol->makeDir($remoteDir);

                        // Dump
                        if(isset($dumpName) && isset($dumpPath)) {
                            $output->write("   - Start dump move...");
                            if($currentProtocol->put($dumpPath, $remoteDir . $dumpName)) {
                                $output->write("<info> DONE</info>" . PHP_EOL);
                            }
                            // TODO handle else
                        }

                        // Archive
                        if(isset($archiveName) && isset($archivePath)) {
                            $output->write("   - Start archive move...");
                            if($currentProtocol->put($archivePath, $remoteDir . $archiveName)) {
                                $output->write("<info> DONE</info>" . PHP_EOL);
                            }
                            // TODO handle else
                        }

                        /*********************************************
                         * Retention
                         */
                        $currentProtocol->handleRetention($configuration[ApplicationConfiguration::REMANENCE_NODE[ApplicationConfiguration::NODE_NAME]]);
                    }

                    $output->writeln("   - End storage");

                    /*********************************************
                     * Post-save commands
                     */
                    if (isset($siteConfiguration['post_save_commands'])) {
                        $output->writeln("  - Start post-save commands");
                        foreach ($siteConfiguration['post_save_commands'] as $command) {
                            $output->writeln("   - Run \"{$command}\"");
                            exec($command);
                        }
                        $output->writeln("  - End post-save commands");
                    }
                }
                $output->writeln("End sites backup");

                /*********************************************
                 * Empty temp paths
                 */
                $output->write("Clear temp folders...");
                foreach(self::TEMP_PATHS as $tempPath) {
                    exec("rm -rf $tempPath/*");
                }
                $output->write("<info> DONE</info>" . PHP_EOL);

            } catch (ParseException $e) {
                $output->writeln("<error>Unable to parse the YAML string : {$e->getMessage()}</error>");
            } catch (\Exception $e) {
                $output->writeln("<error>Error happened : {$e->getMessage()}</error>");
            }
        }
        else {
            $output->writeln("<error>Filename is not correct : {$ymlFile}</error>");
        }
    }
}