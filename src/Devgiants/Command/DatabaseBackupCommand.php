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
        self::DATABASE => self::ROOT_TEMP_PATH . "databases",
        self::FILES => self::ROOT_TEMP_PATH . "files"
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

                // Create temp path to store dumps+files temporarily
                $output->write("Create temp folders...");
                foreach(self::TEMP_PATHS as $tempPath) {
                    if(!file_exists($tempPath)) {
                        mkdir($tempPath, 0777, true);
                    }
                }
                $output->write("<info>DONE</info>" . PHP_EOL);

                $output->writeln("Start sites backup");
                foreach($configuration['sites'] as $site => $siteConfiguration) {
                    $output->writeln("<comment> - Site {$site}</comment>");

                    $output->write("  - Connection to database server...");
                    $mysqli = new \mysqli($siteConfiguration['database']['server'], $siteConfiguration['database']['user'], $siteConfiguration['database']['password']);

                    // Handle connection failure
                    if ($mysqli->connect_errno) {
                        $output->writeln("<error>  - Connection failure : {$mysqli->connect_error}</error>");
                        // TODO logs + mails
                        exit();
                    }
                    $output->write("<info>DONE</info>" . PHP_EOL);
                    $dumpName = "{$site}_" . date('YmdHis') . ".sql.gz";

                    $output->write("  - Start database export and compression...");
                    exec("mysqldump --user={$siteConfiguration['database']['user']} --password='{$siteConfiguration['database']['password']}' --single-transaction {$siteConfiguration['database']['name']} | gzip > " . self::TEMP_PATHS[self::DATABASE] . "/{$dumpName}");
                    $output->write("<info>DONE</info>" . PHP_EOL);
                }
                $output->writeln("End sites backup");

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