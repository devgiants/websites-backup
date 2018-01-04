<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 09/02/17
 * Time: 23:06
 */

namespace Devgiants\Storage;


use Devgiants\Configuration\ApplicationConfiguration as AppConf;
use Devgiants\Model\Storage;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\Dropbox as DropboxService;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\Models\FolderMetadata;
use Kunnu\Dropbox\Models\FileMetadata;

class Dropbox extends Storage {

	const AUTORENAME = true;

	/**
	 * @var DropboxApp
	 */
	protected $app;

	/**
	 * @var DropboxService
	 */
	protected $dropbox;

	/**
	 * @var string
	 */
	protected $clientId;
	/**
	 * @var string
	 */
	protected $clientSecret;

	/**
	 * @var string
	 */
	protected $accessToken;

	/**
	 * Dropbox constructor.
	 *
	 * @param array $options
	 */
	public function __construct( $options ) {
		$this->clientId     = $options[ AppConf::CLIENT_ID ];
		$this->clientSecret = $options[ AppConf::CLIENT_SECRET ];
		$this->accessToken  = $options[ AppConf::ACCESS_TOKEN ];
	}

	public static function getType() {
		return 'Dropbox';
	}

	/**
	 * @return DropboxApp
	 */
	public function getApp(): DropboxApp {
		return $this->app;
	}

	/**
	 * @param DropboxApp $app
	 *
	 * @return Dropbox
	 */
	public function setApp( DropboxApp $app ): Dropbox {
		$this->app = $app;

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function connect() {
		//Configure Dropbox Application
		$this->app     = new DropboxApp( $this->clientId, $this->clientSecret, $this->accessToken );
		$this->dropbox = new DropboxService( $this->app );
	}

	/**
	 * @inheritdoc
	 */
	public function put( $localPath, $remotePath, array $params = null ) {

		$remotePath = $this->buildPath( $remotePath, $params );

		$dropboxFile = new DropboxFile( $localPath );
		$this->dropbox->upload( $dropboxFile, $remotePath, [ 'autorename' => static::AUTORENAME ] );

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function getItemsList( $remotePath, array $params = null ) {

		$remotePath = $this->buildPath( $remotePath, $params );

		return array_map( function ( $item ) {
			if ( ! ( $item instanceof FolderMetadata ) && ! ( $item instanceof FileMetadata ) ) {
				throw new \InvalidArgumentException();
			}

			return $item->getName();
		}, $this->dropbox->listFolder( $remotePath )->getItems()->all() );

	}

	/**
	 * @inheritdoc
	 */
	public function get( $remotePath, $localPath, array $params = null ) {

		$remotePath = $this->buildPath( $remotePath, $params );

		$remotePath     = $this->sanitizePath( $remotePath );
		$downloadedFile = $this->dropbox->download( $remotePath, $localPath );

//		//Downloaded File Metadata
//		$metadata = $downloadedFile->getMetadata();
//		$metadata->getName();

	}

	/**
	 * Unnecessary here as Dropbox API handle path creation
	 */
	public function makeDir( $path, $recursive = true ) {
	}

	/**
	 * @inheritdoc
	 */
	public function handleRetention( int $retention, array $params = null ) {
		// TODO find best way for configurable architecture
		if ( isset( $params[ AppConf::ROOT_DIR ] ) ) {
			$metadata = $this->dropbox->listFolder( $params[ AppConf::ROOT_DIR ] );

			$folders = $metadata->getItems()->all();
			if ( ( $foldersNumber = count( $folders ) ) > $retention ) {
				$folderNumberToRemove = $foldersNumber - $retention;
//
				// Prune older directories
				for ( $i = 0; $i < $folderNumberToRemove; $i ++ ) {
					$this->delete( $folders[ $i ]->getPathLower() );
				}
			}
		}
	}

	/**
	 * @inheritdoc
	 * // TODO handle recursive
	 */
	public function delete( $path, $recursive = true ) {
		$this->dropbox->delete( $path );
	}

	/**
	 * @param $remotePath
	 * @param array|null $params
	 *
	 * @return string
	 */
	protected function buildPath( $remotePath, array $params = null ) {
		// Set path as absolute if param is given
		if ( isset( $params[ AppConf::ROOT_DIR ] ) ) {
			return $this->sanitizePath( "{$params[AppConf::ROOT_DIR]}/{$remotePath}" );
		} else {
			return $this->sanitizePath( $remotePath );
		}
	}

	/**
	 * Unused here
	 */
	public function close() {
	}
}