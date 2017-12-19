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
use Ifsnop\Mysqldump\Mysqldump;
use Pimple\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;
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
            ->setName('save')
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

                        $dumpName = "{$site}_{$currentTimestamp}.sql.gz";
                        $dumpPath = self::TEMP_PATHS[ApplicationConfiguration::DATABASE] .$dumpName;

	                    try {
		                    $dump = new Mysqldump(
		                    	"mysql:host={$siteConfiguration[ApplicationConfiguration::DATABASE][ApplicationConfiguration::SERVER]};dbname={$siteConfiguration[ApplicationConfiguration::DATABASE][ApplicationConfiguration::NAME]}",
			                    $siteConfiguration[ApplicationConfiguration::DATABASE][ApplicationConfiguration::USER],
			                    $siteConfiguration[ApplicationConfiguration::DATABASE][ApplicationConfiguration::PASSWORD],
			                    [
			                    	// TODO add compression as an option per site dump
			                    	'compress' => Mysqldump::GZIP
			                    ]
		                    );

                            $output->write("   - Start database export and compression...");
		                    $dump->start($dumpPath);
                            $output->write("<info> DONE</info>" . PHP_EOL);
	                    } catch (\Exception $e) {
		                    echo 'mysqldump-php error: ' . $e->getMessage();
		                    $output->writeln("<error>   - mysqldump-php error : {$e->getMessage()}</error>");
	                    }
                    }
                    /*********************************************
                     * Files
                     */
                    if (isset($siteConfiguration['files'])) {
                        $output->writeln("<comment>  - Files</comment>");
                        if (is_dir($siteConfiguration['files']['root_dir'])) {
                            // Build and create temp site path. Remove it before if needed to be sure it's empty
                            $siteTempPath = self::TEMP_PATHS[self::FILES] . $site;
                            if(file_exists($siteTempPath)) {
                                // TODO find better
                                exec("rm -rf $siteTempPath");
                            }
                            mkdir($siteTempPath);

                            $finder = new Finder();
                            $fs = new Filesystem();

                            $excludedFiles = [];
                            foreach($siteConfiguration['files']['exclude'] as $relativeExcludedItem) {
                                $absoluteExcludedItem = "{$siteConfiguration['files']['root_dir']}{$relativeExcludedItem}";

                                // File case : directly add to exclude list
                                if(is_file($absoluteExcludedItem)) {
                                    $excludedFiles[] = $absoluteExcludedItem;
                                }
                                else {
                                    foreach($finder->files()->followLinks()->in($absoluteExcludedItem)->getIterator() as $excludedFile) {
                                        $excludedFiles[] = $excludedFile->getRealPath();
                                    }
                                }
                            }


                            // Copy all included folders recursively to temp path
                            foreach ($siteConfiguration['files']['include'] as $includedItem) {
                                $output->writeln("    - Start handling included {$siteConfiguration['files']['root_dir']}{$includedItem}");

                                $includedFiles = $finder
                                                    ->files()
                                                    ->followLinks()
                                                    ->in("{$siteConfiguration['files']['root_dir']}{$includedItem}")
                                ;
                                $output->writeln("      - ". count($includedFiles) . " files to copy");

                                $filesProgressBar = new ProgressBar($output, count($includedFiles));
                                $filesProgressBar->setFormat("very_verbose");
                                $filesProgressBar->start();

                                foreach ($includedFiles as $file) {
                                    if(!in_array($file->getRealPath(), $excludedFiles)) {
                                        $relativeFilePath = substr($file->getRealPath(), strlen($siteConfiguration['files']['root_dir']), strlen($file->getRealPath()));
                                        // Check file name length to avoid tar exception if name longer than 100 char
                                        if(strlen(pathinfo($relativeFilePath, PATHINFO_FILENAME)) < 100) {
                                            $fs->copy($file->getRealPath(), "$siteTempPath/$relativeFilePath");
                                        } else {
                                            // TODO log it + make a list to let user know he have to change those name if he wants them to be saved
                                        }
                                    }
                                    $filesProgressBar->advance();
                                }

                                $filesProgressBar->finish();
                                $output->writeln("");
                            }


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