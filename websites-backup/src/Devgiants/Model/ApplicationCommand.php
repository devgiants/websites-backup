<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 22/12/17
 * Time: 14:34
 */

namespace Devgiants\Model;


use Devgiants\Service\BackupTools;
use Monolog\Logger;
use Pimple\Container;
use Symfony\Component\Console\Command\Command;

abstract class ApplicationCommand extends Command {
	/**
	 * @var Container
	 */
	protected $container;

	/**
	 * @var Logger
	 */
	protected $log;

	/**
	 * @var BackupTools
	 */
	protected $tools;

	/**
	 * ApplicationCommand constructor.
	 *
	 * @param null|string $name
	 * @param Container $container
	 */
	public function __construct( $name, Container $container ) {
		$this->container = $container;
		parent::__construct( $name );

		// Initiates logging
		$this->log = $this->container['main_logger'];
		if ( ! $this->log instanceof Logger ) {
			throw new \InvalidArgumentException( "Container main_logger entry must be Logger type" );
		}

		$this->tools = $this->container['tools'];
		if ( ! $this->tools instanceof BackupTools ) {
			throw new \InvalidArgumentException( "Container tools entry must be BackupTool type" );
		}
	}
}