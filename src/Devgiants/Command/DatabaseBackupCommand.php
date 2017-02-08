<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 05/02/17
 * Time: 14:32
 */
namespace Devgiants\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class DatabaseBackupCommand extends Command
{
    const FILE_OPTION = "file";
    const ROOT_TEMP_PATH = "/tmp/website-backups/";
    const DATABASE = "database";
    const FILES = 'files';
    const TEMP_PATHS = [
        self::DATABASE => self::ROOT_TEMP_PATH . "databases/",
        self::FILES => self::ROOT_TEMP_PATH . "files/"
    ];

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('save')
            ->setDescription('Backup sites accordingly to the parameters provided or YML configuration file')
            ->setHelp("This command allows you to save sites")
            ->addOption(self::FILE_OPTION, "f", InputOption::VALUE_OPTIONAL, "The YML configuration file")
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get conf file
        $ymlFile = $input->getOption(self::FILE_OPTION);

        if($ymlFile !== null && is_file($ymlFile)) {
            try {
                $configuration = Yaml::parse(file_get_contents($ymlFile));
                // TODO add structure check

                if(isset($configuration['backup_storages'])) {

                    /*********************************************
                     * Temp paths
                     */
                    foreach (self::TEMP_PATHS as $tempPath) {
                        if (!file_exists($tempPath)) {
                            $output->write("Create temp folders...");
                            mkdir($tempPath, 0777, true);
                            $output->write("<info> DONE</info>" . PHP_EOL);
                        }
                    }


                    /*********************************************
                     * Backup
                     */
                    $output->writeln("Start sites backup");
                    foreach ($configuration['sites'] as $site => $siteConfiguration) {
                        $output->writeln("<fg=black;bg=yellow> - Site {$site}</>");

                        $currentTimestamp = date('YmdHis');
                        /*********************************************
                         * Pre-save commands
                         */
                        if (isset($siteConfiguration['pre_save_commands'])) {
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
                            $mysqli = new \mysqli($siteConfiguration['database']['server'], $siteConfiguration['database']['user'], $siteConfiguration['database']['password']);

                            // Handle connection failure
                            if ($mysqli->connect_errno) {
                                $output->writeln("<error>   - Connection failure : {$mysqli->connect_error}</error>");
                                // TODO logs + mails
                                exit();
                            }
                            $output->write("<info> DONE</info>" . PHP_EOL);
                            $dumpName = "{$site}_{$currentTimestamp}.sql.gz";
                            $dumpPath = self::TEMP_PATHS[self::DATABASE] . "{$dumpName}";

                            $output->write("   - Start database export and compression...");
                            exec("mysqldump --user={$siteConfiguration['database']['user']} --password='{$siteConfiguration['database']['password']}' --single-transaction {$siteConfiguration['database']['name']} | gzip > $dumpPath");
                            $output->write("<info> DONE</info>" . PHP_EOL);
                        }
                        /*********************************************
                         * Files
                         */
                        if (isset($siteConfiguration['files'])) {
                            $output->writeln("<comment>  - Files</comment>");
                            if (is_dir($siteConfiguration['files']['root_dir'])) {
                                // Build and create temp site path
                                $siteTempPath = self::TEMP_PATHS[self::FILES] . $site;
                                mkdir($siteTempPath);

                                // Copy all included folders recursively to temp path
                                foreach ($siteConfiguration['files']['include'] as $includedItem) {
                                    // Build target path to copy
                                    $fullIncludedItemPath = "{$siteConfiguration['files']['root_dir']}{$includedItem}";

                                    if (file_exists($fullIncludedItemPath)) {
                                        $output->write("   - Copying \"{$fullIncludedItemPath}\"...");
                                        // Normal files
                                        exec("cp -r $fullIncludedItemPath " . $siteTempPath);
                                        // Hidden files
//                                    exec("cp -RL $fullIncludedItemPath/.* " . $siteTempPath);
                                        $output->write("<info> DONE</info>" . PHP_EOL);
                                    } else {
                                        $output->writeln("<error>   - Error : \"{$fullIncludedItemPath}\" doesn't exist. Skip.</error>");
                                    }
                                }
                                // TODO Remove all excluded files/folders
                                // Compress files
                                $archivePath = "{$siteTempPath}/{$site}_{$currentTimestamp}.tar.gz";
                                $archive = new \PharData($archivePath);
                                $archive->buildFromDirectory($siteTempPath);
                            } else {
                                $output->writeln("<error>   - Error : \"{$siteConfiguration['files']['root_dir']}\" is not a valid directory path. Skip.</error>");
                            }

                        }
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
                     * Store on external storages
                     */
                    foreach($configuration['backup_storages'] as $storage) {
                        switch($storage['type']) {
                            case "FTP":
                                // FTP connection
//                                $connectionId = ftp_ssl_connect($ftpServer);
//                                $loginResult = ftp_login($connectionId, $ftpUser, $ftpPassword);
//
//                                // Close connexion
//                                ftp_close($connectionId);

                                break;
                        }
                    }

                    /*********************************************
                     * Empty temp paths
                     */
//                $output->write("Clear temp folders...");
//                foreach(self::TEMP_PATHS as $tempPath) {
//                    exec("rm -rf $tempPath/*");
//                }
//                $output->write("<info> DONE</info>" . PHP_EOL);
                }
                else {
                    $output->writeln("<error>Error : no backup storage defined.</error>");
                }

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