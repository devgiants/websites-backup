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

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('database')
            ->setDescription('Backup databases accordingly to the parameters provided or YML configuration file')
            ->setHelp("This command allows you to save databases")
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
                // TODO add treatment
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