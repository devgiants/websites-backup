<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 14/02/17
 * Time: 19:41
 */

namespace Devgiants\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class RetrieveBackupCommand extends Command
{
    const STORAGE_OPTION = 'storage';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('retrieve')
            ->setDescription('Retrieve sites backup accordingly to the parameters provided or YML configuration file and storage key chosen in backup configuration')
            ->setHelp("This command allows you to retrieve sites backup")
            ->addOption(BackupCommand::FILE_OPTION, "f", InputOption::VALUE_REQUIRED, "The YML configuration file")
            ->addOption(self::STORAGE_OPTION, "s", InputOption::VALUE_OPTIONAL, "The storage key in configuration file to retrieve backups from")
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get conf file
        $ymlFile = $input->getOption(BackupCommand::FILE_OPTION);

        if($ymlFile !== null && is_file($ymlFile)) {
            try {
                $configuration = Yaml::parse(file_get_contents($ymlFile));
                // TODO add structure check
            } catch (\Exception $e) {
                $output->writeln("<error>Error happened : {$e->getMessage()}</error>");
            }
        }
        else {
            $output->writeln("<error>Filename is not correct : {$ymlFile}</error>");
        }
    }
}