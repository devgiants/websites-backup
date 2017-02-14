<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 09/02/17
 * Time: 23:06
 */

namespace Devgiants\Protocol;


use Devgiants\Model\Protocol;

class Ftp implements Protocol
{
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
     * @var int $remanence
     */
    protected $remanence;

    /**
     * FtpManager constructor.
     * @param string $server
     * @param string $username
     * @param string $password
     * @param bool $passive
     * @param bool $ssl
     * @param int $transferMode
     * @param int $remanence
     */
    public function __construct($server, $username, $password, $passive = true, $ssl = false, $transferMode = FTP_BINARY, $remanence = self::REMANENCE) {
        $this->server = $server;
        $this->username = $username;
        $this->password = $password;
        $this->passive = $passive;
        $this->ssl = $ssl;
        $this->transferMode = $transferMode;
        $this->remanence = $remanence;
    }

    /**
     * @return resource
     */
    public function getConnectionResource()
    {
        return $this->connectionResource;
    }

    /**
     * @return string
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return boolean
     */
    public function isPassive()
    {
        return $this->passive;
    }

    /**
     * @return boolean
     */
    public function isSsl()
    {
        return $this->ssl;
    }

    /**
     * @return int
     */
    public function getTransferMode()
    {
        return $this->transferMode;
    }

    /**
     * Establish FTP connection
     * @return bool
     */
    public function connect() {

        // Handle SSL connection
        if($this->ssl === true) {
            $this->connectionResource = ftp_ssl_connect($this->server);
        }
        else {
            $this->connectionResource = ftp_connect($this->server);
        }

        // Try to login
        $loginResult = ftp_login($this->connectionResource, $this->username, $this->password);

        // If login successful
        if($loginResult) {
            // Set passive mode according to configuration
            if ($this->passive === true) {
                ftp_pasv($this->connectionResource, true);
            }
        }
        return $loginResult;
    }

    public function put($localPath, $remotePath) {
        return ftp_put($this->connectionResource, $remotePath, $localPath, $this->transferMode);
    }

    public function get() {
        // TODO : implements get
    }

    /**
     * @inheritdoc
     * // TODO handle recursive
     */
    public function delete($path, $recursive = true) {
        if (@ftp_delete ($this->connectionResource, $path) === false) {
            if ($children = @ftp_nlist ($this->connectionResource, $path)) {
                foreach ($children as $p)
                    $this->delete($this->connectionResource, $p);
            }

            @ftp_rmdir($this->connectionResource, $path);
        }
    }

    /**
     * @inheritdoc
     * // TODO handle recursive
     */
    public function makeDir($path, $recursive = true) {
        $parts = explode('/', $path);

        foreach ($parts as $part) {
            if (!empty($part) && !@ftp_chdir($this->connectionResource, $path)) {
                // TODO find how to correctly specify folder to not trigger warning
                @ftp_mkdir($this->connectionResource, $part);
                ftp_chdir($this->connectionResource, $part);
            }
        }
    }

    public function handleRetention()
    {
        // TODO find best way for configurable architecture
        // Goes back to timestamps folders list
        ftp_chdir($this->connectionResource, '../');
        $timestampDirs = ftp_nlist($this->connectionResource, ".");
        if(count($timestampDirs) > $this->remanence) {

            sort($timestampDirs);
            $folderNumberToRemove = count($timestampDirs) - $this->remanence;

            // Prune older directories
            for($i=0;$i<$folderNumberToRemove;$i++) {
                $this->delete($timestampDirs[$i]);
            }
        }
    }

    public function close() {
        ftp_close($this->connectionResource);
    }
}