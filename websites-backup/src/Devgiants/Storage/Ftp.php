<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 09/02/17
 * Time: 23:06
 */

namespace Devgiants\Storage;


use Devgiants\Configuration\ApplicationConfiguration;
use Devgiants\Configuration\ConfigurationManager;
use Devgiants\Model\Storage;
use Devgiants\Model\StorageInterface;
use Symfony\Component\Config\Definition\Exception\Exception;

class Ftp extends Storage {
	/**
	 * @var resource the FTP connection resource
	 */
	protected $connectionResource;
	/**
	 * @var string $server
	 */
	protected $server;
	/**
	 * @var string $username
	 */
	protected $username;
	/**
	 * @var string $password
	 */
	protected $password;
	/**
	 * @var bool $passive
	 */

	protected $passive;
	/**
	 * @var bool $ssl
	 */
	protected $ssl;
	/**
	 * @var int $transferMode
	 */
	protected $transferMode;

	/**
	 * FtpManager constructor.
	 *
	 * @param array $options
	 */
	public function __construct( $options ) {
		$this->server       = $options[ ApplicationConfiguration::SERVER ];
		$this->username     = $options[ ApplicationConfiguration::USER ];
		$this->password     = $options[ ApplicationConfiguration::PASSWORD ];
		$this->passive      = $options[ ApplicationConfiguration::PASSIVE ];
		$this->ssl          = $options[ ApplicationConfiguration::SSL ];
		$this->transferMode = $options[ ApplicationConfiguration::TRANSFER ];
	}

	/**
	 * @return resource
	 */
	public function getConnectionResource() {
		return $this->connectionResource;
	}

	/**
	 * @return string
	 */
	public function getServer() {
		return $this->server;
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return $this->username;
	}

	/**
	 * @return boolean
	 */
	public function isPassive() {
		return $this->passive;
	}

	/**
	 * @return boolean
	 */
	public function isSsl() {
		return $this->ssl;
	}

	/**
	 * @return int
	 */
	public function getTransferMode() {
		return $this->transferMode;
	}

	/**
	 * Establish FTP connection
	 * @throws Exception
	 */
	public function connect() {

		// Handle SSL connection
		if ( $this->ssl === true ) {
			$this->connectionResource = ftp_ssl_connect( $this->server );
		} else {
			$this->connectionResource = ftp_connect( $this->server );
		}

		if ( ! $this->connectionResource ) {
			throw new Exception( "Impossible to establish connection on FTP server {$this->server} with username {$this->username} (SSL = {$this->ssl})" );
		}

		// Try to login
		$loginResult = ftp_login( $this->connectionResource, $this->username, $this->password );

		// If login successful
		if ( $loginResult ) {
			// Set passive mode according to configuration
			if ( $this->passive === true ) {
				ftp_pasv( $this->connectionResource, true );
			}
		}
		if ( ! $loginResult ) {
			throw new Exception( "Impossible to authenticate on FTP server {$this->server} with username {$this->username} (SSL = {$this->ssl})" );
		}
	}

	/**
	 * @inheritdoc
	 */
	public function put( $localPath, $remotePath, array $params = null ) {
		return ftp_put( $this->connectionResource, $remotePath, $localPath, $this->transferMode );
	}

	/**
	 * @inheritdoc
	 */
	public function getItemsList( $remotePath, array $params = null) {
		return ftp_nlist( $this->connectionResource, $remotePath );
	}

	/**
	 * @inheritdoc
	 */
	public function get( $remotePath, $localPath, array $params = null ) {
		return ftp_get( $this->connectionResource, $localPath, $remotePath, FTP_BINARY );
	}

	/**
	 * @inheritdoc
	 * // TODO handle recursive
	 */
	public function delete( $path, $recursive = true ) {
		if ( @ftp_delete( $this->connectionResource, $path ) === false ) {
			if ( $children = @ftp_nlist( $this->connectionResource, $path ) ) {
				foreach ( $children as $p ) {
					$this->delete( $p, $recursive );
				}
			}
			@ftp_rmdir( $this->connectionResource, $path );
		}
	}

	/**
	 * @inheritdoc
	 * // TODO handle recursive
	 */
	public function makeDir( $path, $recursive = true ) {
		$parts = explode( '/', $path );

		foreach ( $parts as $part ) {
			if ( ! empty( $part ) && ! @ftp_chdir( $this->connectionResource, $path ) ) {
				// TODO find how to correctly specify folder to not trigger warning
				@ftp_mkdir( $this->connectionResource, $part );
				ftp_chdir( $this->connectionResource, $part );
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function handleRetention( int $retention, array $params = null ) {
		// TODO find best way for configurable architecture
		// Goes back to timestamps folders list
		ftp_chdir( $this->connectionResource, '../' );
		$timestampDirs = ftp_nlist( $this->connectionResource, "." );
		if ( count( $timestampDirs ) > $retention ) {

			sort( $timestampDirs );
			$folderNumberToRemove = count( $timestampDirs ) - $retention;

			// Prune older directories
			for ( $i = 0; $i < $folderNumberToRemove; $i ++ ) {
				$this->delete( $timestampDirs[ $i ] );
			}
		}
	}

	public function close() {
		ftp_close( $this->connectionResource );
	}

	public static function getType() {
		return 'FTP';
	}
}