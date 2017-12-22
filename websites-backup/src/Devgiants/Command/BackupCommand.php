<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 05/02/17
 * Time: 14:32
 */

namespace Devgiants\Command;

use Devgiants\Configuration\ConfigurationManager;
use Devgiants\Configuration\ApplicationConfiguration as AppConf;
use Devgiants\Exception\FailedStorageUploadException;
use Devgiants\Service\BackupTools;
use Ifsnop\Mysqldump\Mysqldump;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Pimple\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;

class BackupCommand extends Command {
	const FILE_OPTION = "file";
	const ROOT_TEMP_PATH = "/tmp/websites-backups/";
	const FILES = 'files';
	const TEMP_PATHS = [
		AppConf::DATABASE => self::ROOT_TEMP_PATH . "databases/",
		self::FILES       => self::ROOT_TEMP_PATH . "files/"
	];

	/**
	 * @var Container
	 */
	private $container;

	/**
	 * @var Logger
	 */
	private $log;

	/**
	 * @var BackupTools
	 */
	private $tools;

	public function __construct( $name, Container $container ) {
		$this->container = $container;
		parent::__construct( $name );

		// Initiates logging
		$this->log = new Logger( 'main' );

		$this->tools = $this->container['tools'];
		if ( ! $this->tools instanceof BackupTools ) {
			throw new InvalidArgumentException( "Container tools entry must be BackupTool type" );
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function configure() {
		$this
			->setName( 'save' )
			->setDescription( 'Backup sites accordingly to the YML configuration file provided' )
			->setHelp( "This command allows you to save sites, maily by saving databases and files you chose" )
			->addOption( self::FILE_OPTION, "f", InputOption::VALUE_REQUIRED, "The YML configuration file" );
	}

	/**
	 * @inheritdoc
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		// Get conf file
		$ymlFile = $input->getOption( self::FILE_OPTION );

		if ( $ymlFile !== null && is_file( $ymlFile ) ) {
			try {

				// Structures check and configuration loading
				$configurationManager = new ConfigurationManager( $ymlFile );
				$configuration        = $configurationManager->load();

				$fs = new Filesystem();

				// Defines 1 handler
				$this->log
					->pushHandler( new RotatingFileHandler(
							"{$configuration[AppConf::LOG_NODE[AppConf::NODE_NAME]]}/main.log",
							Logger::DEBUG )
					);

				$this->log->addDebug( "" );
				$this->log->addDebug( "----- START BACKUP SESSION -----" );

				/*********************************************
				 * Temp paths
				 */
				foreach ( self::TEMP_PATHS as $tempPath ) {
					if ( ! file_exists( $tempPath ) ) {
						$this->log->addDebug( "Create temp folder : {$tempPath}" );
						$output->write( "Create temp folders..." );
						$fs->mkdir( $tempPath );
						$output->write( "<info> DONE</info>" . PHP_EOL );
					}
				}

				/*********************************************
				 * Backup
				 */
				$startTime = microtime();

				$output->writeln( "Start sites backup" );
				foreach ( $configuration['sites'] as $site => $siteConfiguration ) {

					// Create one log file per site
					$siteLog = new Logger( $site );
					$siteLog
						->pushHandler( new RotatingFileHandler(
								"{$configuration[AppConf::LOG_NODE[AppConf::NODE_NAME]]}/{$site}.log",
								Logger::DEBUG )
						);

					$siteLog->addDebug( "" );
					$siteLog->addDebug( "----- START BACKUP SESSION -----" );

					$output->writeln( "<fg=black;bg=yellow> - Site {$site}</>" );
					$currentTimestamp = date( 'YmdHis' );
					$siteLog->addDebug( "Start backup for site {$site}. Current timestamp: {$currentTimestamp}" );

					/*********************************************
					 * Pre-save commands
					 */
					if ( count( $siteConfiguration[ AppConf::PRE_SAVE_COMMANDS ] ) > 0 ) {
						$output->writeln( "  - Start pre-save commands" );
						$siteLog->addDebug( "Pre save commands found." );
						foreach ( $siteConfiguration['pre_save_commands'] as $command ) {
							$output->writeln( "   - Run \"{$command}\"" );
							$siteLog->addDebug( "Run \"{$command}\"" );
							// TODO handle exec return
							exec( $command );
						}
						$output->writeln( "  - End pre-save commands" );
					}

					/*********************************************
					 * Databases
					 */
					if ( isset( $siteConfiguration['database'] ) ) {
						$output->writeln( "<comment>  - Databases</comment>" );

						$dumpName = "{$site}_{$currentTimestamp}.sql.gz";
						$dumpPath = self::TEMP_PATHS[ AppConf::DATABASE ] . $dumpName;
						$siteLog->addDebug( "Start database dump", [ 'dump_path' => $dumpPath ] );

						try {
							$dump = new Mysqldump(
								"mysql:host={$siteConfiguration[AppConf::DATABASE][AppConf::SERVER]};dbname={$siteConfiguration[AppConf::DATABASE][AppConf::NAME]}",
								$siteConfiguration[ AppConf::DATABASE ][ AppConf::USER ],
								$siteConfiguration[ AppConf::DATABASE ][ AppConf::PASSWORD ],
								[
									// TODO add compression as an option per site dump
									'compress' => Mysqldump::GZIP
								]
							);

							$output->write( "   - Start database export and compression..." );
							$siteLog->addDebug( "Start dump process" );
							$dump->start( $dumpPath );
							$output->write( "<info> DONE</info>" . PHP_EOL );
							$siteLog->addDebug( "End dump process" );
						} catch ( \Exception $e ) {
							echo 'mysqldump-php error: ' . $e->getMessage();
							$output->writeln( "<error>   - mysqldump-php error : {$e->getMessage()}</error>" );
							$siteLog->addError( "MysqlDump PHP error :", [
								'message' => $e->getMessage(),
								'code'    => $e->getCode(),
								'file'    => $e->getFile(),
								'line'    => $e->getLine()
							] );
						}
					}

					/*********************************************
					 * Files
					 */
					if ( isset( $siteConfiguration['files'] ) ) {
						$output->writeln( "<comment>  - Files</comment>" );
						$siteLog->addDebug( "Start files saving process" );

						if ( is_dir( $siteConfiguration['files']['root_dir'] ) ) {
							try {
								// Build and create temp site path. Remove it before if needed to be sure it's empty
								$siteTempPath = self::TEMP_PATHS[ self::FILES ] . $site;
								if ( file_exists( $siteTempPath ) ) {
									$siteLog->addDebug( "Site temp path exists. Kills it", [ 'path' => $siteTempPath ] );
									$fs->remove( $siteTempPath );
								}
								$siteLog->addDebug( "Creates site temp path", [ 'path' => $siteTempPath ] );
								$fs->mkdir( $siteTempPath );

								$finder = new Finder();

								$excludedFiles = [];
								foreach ( $siteConfiguration['files']['exclude'] as $relativeExcludedItem ) {
									$absoluteExcludedItem = "{$siteConfiguration['files']['root_dir']}{$relativeExcludedItem}";

									// File case : directly add to exclude list
									if ( is_file( $absoluteExcludedItem ) ) {
										$siteLog->addDebug( "Add excluded file : {$absoluteExcludedItem}" );
										$excludedFiles[] = $absoluteExcludedItem;
									} else {
										$siteLog->addDebug( "Handle excluded folder : {$absoluteExcludedItem}" );
										foreach ( $finder->files()->followLinks()->in( $absoluteExcludedItem )->getIterator() as $excludedFile ) {
											$excludedFiles[] = $excludedFile->getRealPath();
										}
									}
								}
								$siteLog->addDebug( "Final excluded files count : " . count( $excludedFiles ) );

								// Copy all included folders recursively to temp path
								foreach ( $siteConfiguration['files']['include'] as $includedItem ) {
									$output->writeln( "    - Start handling included {$siteConfiguration['files']['root_dir']}{$includedItem}" );
									$siteLog->addDebug( "Start moving included files from {$siteConfiguration['files']['root_dir']}{$includedItem} to temp location" );
									$includedFiles = $finder
										->files()
										->followLinks()
										->in( "{$siteConfiguration['files']['root_dir']}{$includedItem}" );
									$output->writeln( "      - " . count( $includedFiles ) . " files to copy" );

									$filesProgressBar = new ProgressBar( $output, count( $includedFiles ) );
									$filesProgressBar->setFormat( "very_verbose" );
									$filesProgressBar->start();

									foreach ( $includedFiles as $file ) {
										if ( ! in_array( $file->getRealPath(), $excludedFiles ) ) {
											$relativeFilePath = substr( $file->getRealPath(), strlen( $siteConfiguration['files']['root_dir'] ), strlen( $file->getRealPath() ) );
											// Check file name length to avoid tar exception if name longer than 100 char
											if ( strlen( pathinfo( $relativeFilePath, PATHINFO_FILENAME ) ) < 100 ) {
												$fs->copy( $file->getRealPath(), "$siteTempPath/$relativeFilePath" );
											} else {
												// TODO log it + make a list to let user know he have to change those name if he wants them to be saved
											}
										}
										$filesProgressBar->advance();
									}

									$siteLog->addDebug( "Done" );

									$filesProgressBar->finish();
									$output->writeln( "" );
								}


								// Compress files
								$archiveName = "{$site}_{$currentTimestamp}.tar.gz";
								$archivePath = "{$siteTempPath}/{$archiveName}";
								$archive     = new \PharData( $archivePath );
								$siteLog->addDebug( "Start compressing files to {$archivePath}" );
								$archive->buildFromDirectory( $siteTempPath );
								$siteLog->addDebug( "Done" );
							} catch ( \Exception $e ) {
								$this->tools->maximumDetailsErrorHandling( $output, $siteLog, $e );
							}
						} else {
							$output->writeln( "<error>   - Error : \"{$siteConfiguration['files']['root_dir']}\" is not a valid directory path. Skip.</error>" );
							$siteLog->addError( "\"{$siteConfiguration['files']['root_dir']}\" is not a valid directory path. Skip." );
						}
					}

					/*********************************************
					 * Store on external storages
					 */
					try {
						$output->writeln( "<comment>  - Storage</comment>" );
						$siteLog->addDebug( "Start storage task" );

						foreach ( $configuration[ AppConf::BACKUP_STORAGES ] as $storageKey => $storage ) {

							$currentStorage = $this->tools->getStorageByType( $storage );
							$siteLog->addDebug( "Start storage on {$storageKey} ({$storage[AppConf::STORAGE_TYPE]})" );

							// Connection
							$output->write( "   - Trying connection..." );
							$currentStorage->connect();
							$siteLog->addDebug( "Connected to storage" );
							$output->write( "<info> CONNECTED</info>" . PHP_EOL );

							$remoteDir = "{$storage['root_dir']}/{$site}/{$currentTimestamp}/";
							$siteLog->addDebug( "Creates target remote directory : {$remoteDir}" );
							$currentStorage->makeDir( $remoteDir );

							// Dump
							if ( isset( $dumpName ) && isset( $dumpPath ) ) {
								$output->write( "   - Start dump move..." );
								if ( $currentStorage->put( $dumpPath, $remoteDir . $dumpName ) ) {
									$siteLog->addDebug( "Database dump moved to {$remoteDir}{$dumpName}" );
									$output->write( "<info> DONE</info>" . PHP_EOL );
								} else {
									throw new FailedStorageUploadException("Dump {$dumpPath} failed to be uploaded to {$storageKey} ({$storage[AppConf::STORAGE_TYPE]}) to path {$remoteDir}{$dumpName}");
								}
							}

							// Archive
							if ( isset( $archiveName ) && isset( $archivePath ) ) {
								$output->write( "   - Start archive move..." );
								if ( $currentStorage->put( $archivePath, $remoteDir . $archiveName ) ) {
									$siteLog->addDebug( "Archive  moved to {$remoteDir}{$archiveName}" );
									$output->write( "<info> DONE</info>" . PHP_EOL );
								}
								else {
									throw new FailedStorageUploadException("Archive {$archivePath} failed to be uploaded to {$storageKey} ({$storage[AppConf::STORAGE_TYPE]}) to path {$remoteDir}{$dumpName}");
								}
							}

							/*********************************************
							 * Retention
							 */
							$siteLog->addDebug( "Handle retention" );
							$currentStorage->handleRetention( $configuration[ AppConf::REMANENCE_NODE[ AppConf::NODE_NAME ] ] );
						}

						$siteLog->addDebug( "End storage task" );
						$output->writeln( "   - End storage" );

					} catch ( \Exception $e ) {
						$this->tools->maximumDetailsErrorHandling( $output, $siteLog, $e );
					}

					/*********************************************
					 * Post-save commands
					 */
					if ( isset( $siteConfiguration['post_save_commands'] ) ) {
						$output->writeln( "  - Start post-save commands" );
						$siteLog->addDebug( "Post save commands found." );
						foreach ( $siteConfiguration['post_save_commands'] as $command ) {
							$output->writeln( "   - Run \"{$command}\"" );
							$siteLog->addDebug( "Run \"{$command}\"" );
							exec( $command );
						}
						$output->writeln( "  - End post-save commands" );
					}
				}
				$output->writeln( "End sites backup" );

				/*********************************************
				 * Empty temp paths
				 */
				$output->write( "Clear temp folders..." );
				foreach ( self::TEMP_PATHS as $tempPath ) {
					$this->log->addDebug( "Clear temp path {$tempPath}" );
					exec( "rm -rf $tempPath/*" );
				}
				$output->write( "<info> DONE</info>" . PHP_EOL );

			} catch ( ParseException $e ) {
				$output->writeln( "<error>Unable to parse the YAML string : {$e->getMessage()}</error>" );
				$this->log->addError("Unable to parse the YAML string : {$e->getMessage()}");
			} catch ( \Exception $e ) {
				$this->tools->maximumDetailsErrorHandling( $output, $this->log, $e );
			}
		} else {
			$output->writeln( "<error>Filename is not correct : {$ymlFile}</error>" );
			$this->log->addError( "Filename is not correct : {$ymlFile}" );
		}
		$this->log->addDebug( "----- END BACKUP SESSION -----" );
	}
}