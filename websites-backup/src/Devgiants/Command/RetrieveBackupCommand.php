<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 14/02/17
 * Time: 19:41
 */

namespace Devgiants\Command;

use Devgiants\Configuration\ApplicationConfiguration as AppConf;
use Devgiants\Configuration\ConfigurationManager;
use Devgiants\Model\ApplicationCommand;
use Devgiants\Service\BackupTools;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Pimple\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class RetrieveBackupCommand extends ApplicationCommand {
	const STORAGE_OPTION = 'storage';


	/**
	 * RetrieveBackupCommand constructor.
	 *
	 * @param null|string $name
	 * @param Container $container
	 */
	public function __construct( $name, Container $container ) {
		parent::__construct( $name, $container );
	}

	/**
	 * @inheritdoc
	 */
	protected function configure() {
		$this
			->setName( 'retrieve' )
			->setDescription( 'Retrieve sites backup accordingly to the YML configuration file and storage key chosen in backup configuration' )
			->setHelp( "This command allows you to retrieve sites backup on /tmp folder" )
			->addOption( BackupCommand::FILE_OPTION, "f", InputOption::VALUE_REQUIRED, "The YML configuration file" )
			->addOption( self::STORAGE_OPTION, "s", InputOption::VALUE_OPTIONAL, "The storage key in configuration file to retrieve backups from" );
	}

	/**
	 * @inheritdoc
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		// Get conf file
		$ymlFile    = $input->getOption( BackupCommand::FILE_OPTION );
		$storageKey = $input->getOption( static::STORAGE_OPTION );


		if ( $ymlFile !== null && is_file( $ymlFile ) ) {
			try {

				// Structures check and configuration loading
				$configurationManager = new ConfigurationManager( $ymlFile );
				$configuration        = $configurationManager->load();


				// Defines 1 handler
				$this->log
					->pushHandler( new RotatingFileHandler(
							"{$configuration[AppConf::LOG_NODE[AppConf::NODE_NAME]]}/main.log",
							Logger::DEBUG )
					);

				$this->log->addDebug( "" );
				$this->log->addDebug( "----- START RETRIEVE SESSION -----" );

				// If storageKey not provided, start question
				if ( null === $storageKey ) {
					$this->log->addDebug( "No storage key provided, start question" );
					$storageKeysAvailable = [];

					foreach ( $configuration[ AppConf::BACKUP_STORAGES ] as $storageKeyAvailable => $storage ) {
						$storageKeysAvailable[] = $storageKeyAvailable;
					}
					$this->log->addDebug( "Storages keys available", [ 'storages' => $storageKeysAvailable ] );
					$helper   = $this->getHelper( 'question' );
					$question = new ChoiceQuestion(
						'Please select the storage retrieve backup from',
						$storageKeysAvailable,
						0
					);
					$question->setErrorMessage( 'Storage %s is invalid.' );
					$storageKey = $helper->ask( $input, $output, $question );
				} else {
					// TODO handle
				}

				// Try to retrieve storage key
				if ( isset( $configuration[ AppConf::BACKUP_STORAGES ][ $storageKey ] ) && $currentStorageData = $configuration[ AppConf::BACKUP_STORAGES ][ $storageKey ] ) {
					$currentStorage = $this->tools->getStorageByType( $currentStorageData );
					$this->log->addDebug( "Storage populated" );

					$currentStorage->connect();

					// Get sites list from root dir. No params in this case
					$sitesBackuped = $currentStorage->getItemsList( $currentStorageData[ AppConf::ROOT_DIR ] );
					$this->log->addDebug( "Backuped sites list", [ 'sites' => $sitesBackuped ] );

					if ( is_array( $sitesBackuped ) && count( $sitesBackuped ) > 0 ) {
						$helper   = $this->getHelper( 'question' );
						$question = new ChoiceQuestion(
							'Please select the site you want retrieve backup from',
							$sitesBackuped,
							0
						);
						$question->setErrorMessage( 'Site %s is invalid.' );
						$siteChosen = $helper->ask( $input, $output, $question );
						$this->log->addDebug( "Site chosen : {$siteChosen}" );

						// Get backups. pass root dir for complete path
						$backups = $currentStorage->getItemsList( $siteChosen, [ 'root_dir' => $currentStorageData[ AppConf::ROOT_DIR ] ] );
						$this->log->addDebug( "Backups available", [ 'backup' => $backups ] );

						$helper   = $this->getHelper( 'question' );
						$question = new ChoiceQuestion(
							'Please select the backup you want to retrieve',
							$backups,
							0
						);
						$question->setErrorMessage( 'Site %s is invalid.' );
						$backupChosen = $helper->ask( $input, $output, $question );
						$this->log->addDebug( "Backup chosen : {$backupChosen}" );

						// TODO add retrieve folder option
						$fs = new Filesystem();

						if ( strpos( $backupChosen, $currentStorageData[ AppConf::ROOT_DIR ] ) !== false ) {
							$tempRootDir = "/tmp{$backupChosen}";
						} else {
							$tempRootDir = "/tmp/{$siteChosen}/{$backupChosen}";
						}
						if ( $fs->exists( $tempRootDir ) ) {
							$this->log->addDebug( "Temp dir {$tempRootDir} exists, kill it." );
							$fs->remove( $tempRootDir );
						}
						$fs->mkdir( $tempRootDir );

						// Get items. pass root dir + site chosen for complete path
						foreach ( $currentStorage->getItemsList( $backupChosen, [ 'root_dir' => "{$currentStorageData[ AppConf::ROOT_DIR ]}/{$siteChosen}" ] ) as $remoteFile ) {
							$name = pathinfo( $remoteFile, PATHINFO_BASENAME );
							$this->log->addDebug( "Start retrieving {$name}, storing it at <comment>{$tempRootDir}/{$name}" );
							$output->write( "Start retrieving for <info>{$name}</info> file, storing it at <comment>{$tempRootDir}/{$name}</comment>..." );
							$currentStorage->get( $remoteFile, "{$tempRootDir}/{$name}", [ 'root_dir' => "{$currentStorageData[ AppConf::ROOT_DIR ]}/{$siteChosen}/{$backupChosen}" ] );
							$this->log->addDebug( "Done" );
							$output->write( "<info> DONE</info>" . PHP_EOL );
						}
						$output->writeln( "" );
						$output->writeln( "<info>Backup is retrieved.</info>" );
					}
				} else {
					$this->log->addDebug( "Storage empty, no sites. abort." );
				}

			} catch ( \Exception $e ) {
				$output->writeln( "<error>Error happened : {$e->getMessage()}</error>" );
				$this->tools->maximumDetailsErrorHandling( $output, $this->log, $e );
			}
		} else {
			$output->writeln( "<error>Filename is not correct : {$ymlFile}</error>" );
			$this->log->addError( "Filename is not correct : {$ymlFile}" );
		}

		$this->log->addDebug( "----- END RETRIEVE SESSION -----" );
	}
}