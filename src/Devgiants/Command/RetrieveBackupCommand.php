<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 14/02/17
 * Time: 19:41
 */

namespace Devgiants\Command;

use Devgiants\Configuration\ApplicationConfiguration;
use Devgiants\Configuration\ConfigurationManager;
use Pimple\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class RetrieveBackupCommand extends Command
{
    const STORAGE_OPTION = 'storage';

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
            ->setName('retrieve')
            ->setDescription('Retrieve sites backup accordingly to the YML configuration file and storage key chosen in backup configuration')
            ->setHelp("This command allows you to retrieve sites backup on /tmp folder")
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
        $storageKey = $input->getOption(static::STORAGE_OPTION);
        

        if($ymlFile !== null && is_file($ymlFile)) {
            try {

                // Structures check and configuration loading
                $configurationManager = new ConfigurationManager($ymlFile);
                $configuration = $configurationManager->load();

                // If storageKey not provided, start question
                if(null === $storageKey) {
                    $storageKeysAvailable = [];

                    foreach($configuration[ApplicationConfiguration::BACKUP_STORAGES] as $storageKeyAvailable => $storage) {
                        $storageKeysAvailable[] = $storageKeyAvailable;
                    }
                    $helper = $this->getHelper('question');
                    $question = new ChoiceQuestion(
                        'Please select the storage retrieve backup from',
                        $storageKeysAvailable,
                        0
                    );
                    $question->setErrorMessage('Storage %s is invalid.');
                    $storageKey = $helper->ask($input, $output, $question);
                }
                
                // Try to retrieve storage key
                if(isset($configuration[ApplicationConfiguration::BACKUP_STORAGES][$storageKey]) && $currentStorage = $configuration[ApplicationConfiguration::BACKUP_STORAGES][$storageKey]) {
                    $currentProtocol = $this->container['tools']->getProtocolByType($currentStorage);

                    $currentProtocol->connect();
                    $sitesBackuped = $currentProtocol->getItemsList($currentStorage[ApplicationConfiguration::ROOT_DIR]);

                    if(is_array($sitesBackuped) && count($sitesBackuped) > 0) {
                        $helper = $this->getHelper('question');
                        $question = new ChoiceQuestion(
                            'Please select the site you want retrieve backup from',
                            $sitesBackuped,
                            0
                        );
                        $question->setErrorMessage('Site %s is invalid.');
                        $siteChosen = $helper->ask($input, $output, $question);

                        $backups = $currentProtocol->getItemsList($siteChosen);

                        $helper = $this->getHelper('question');
                        $question = new ChoiceQuestion(
                            'Please select the backup you want to retrieve',
                            $backups,
                            0
                        );
                        $question->setErrorMessage('Site %s is invalid.');
                        $backupChosen = $helper->ask($input, $output, $question);

                        // TODO add retrieve folder option
                        $fs = new Filesystem();
                        $tempRootDir = "/tmp{$backupChosen}";
                        if($fs->exists($tempRootDir)) {
                            $fs->remove($tempRootDir);
                        }
                        $fs->mkdir($tempRootDir);

                        foreach($currentProtocol->getItemsList($backupChosen) as $remoteFile) {
                            $name = pathinfo($remoteFile, PATHINFO_FILENAME);
                            $output->write("Start retrieving for <info>{$name}</info> file, storing it at <comment>{$tempRootDir}/{$name}</comment>...");
                            $currentProtocol->get($remoteFile, "{$tempRootDir}/{$name}");
                            $output->write("<info> DONE</info>" . PHP_EOL);
                        }
                        $output->writeln("");
                        $output->writeln("<info>Backup is retrieved.</info>");
                    }
                }

            } catch (\Exception $e) {
                $output->writeln("<error>Error happened : {$e->getMessage()}</error>");
            }
        }
        else {
            $output->writeln("<error>Filename is not correct : {$ymlFile}</error>");
        }
    }
}