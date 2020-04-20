<?php


namespace sinri\ark\sftp;


use Exception;

class ArkSFTP
{
    /**
     * @var ArkSSH2
     */
    protected $parentArkSSH2Instance;
    /**
     * @var resource
     */
    protected $sftpConnection;

    /**
     * @param ArkSSH2 $arkSS2Instance
     * @return $this
     * @throws Exception
     */
    public function connect($arkSS2Instance)
    {
        $this->parentArkSSH2Instance = $arkSS2Instance;
        $this->sftpConnection = ssh2_sftp($this->parentArkSSH2Instance->getConnection());
        if ($this->sftpConnection === false) {
            throw new Exception("Cannot establish SFTP connection via SSH2 connection");
        }
        return $this;
    }

    /**
     * @param string $localPath
     * @param string $remotePath
     * @return $this
     * @throws Exception
     */
    public function transcribeFileToSFTP($localPath, $remotePath)
    {
        $localFile = fopen($localPath, 'rb');
        if (!$localFile) {
            throw new Exception("Cannot open local file");
        }
        $sftpStream = fopen('ssh2.sftp://' . intval($this->sftpConnection) . $remotePath, 'wb');
        if (!$sftpStream) {
            fclose($localFile);
            throw new Exception("Cannot open remote file");
        }

        while (!feof($localFile)) {
            $contents = fread($localFile, 8192);
            $partialWritten = fwrite($sftpStream, $contents);
            if (!$partialWritten) {
                fclose($sftpStream);
                fclose($localFile);
                throw new Exception("Failed in writing partial data into remote path");
            }
        }

        fclose($sftpStream);
        fclose($localFile);

        return $this;
    }

    /**
     * @param string $remotePath
     * @param string $localPath
     * @return $this
     * @throws Exception
     */
    public function transcribeFileFromSFTP($remotePath, $localPath)
    {
        $sftpStream = fopen('ssh2.sftp://' . intval($this->sftpConnection) . $remotePath, 'rb');
        if (!$sftpStream) {
            throw new Exception("Cannot open remote file");
        }
        $localFile = fopen($localPath, 'wb');
        if (!$localFile) {
            fclose($sftpStream);
            throw new Exception("Cannot open local file");
        }

        while (!feof($sftpStream)) {
            $contents = fread($sftpStream, 8192);
            $partialWritten = fwrite($localFile, $contents);
            if (!$partialWritten) {
                fclose($sftpStream);
                fclose($localFile);
                throw new Exception("Failed in writing partial data into local path");
            }
        }

        fclose($sftpStream);
        fclose($localFile);

        return $this;
    }

    /**
     * @param string $remoteDir
     * @param callable $callback function(ArkSFTP $sftp,string $remoteParentDir,string $remoteTargetItem,bool $isDir):void throws \Exception
     * @throws Exception
     */
    public function traversalOnRemoteDirectory($remoteDir, $callback)
    {
        $handler = opendir('ssh2.sftp://' . intval($this->sftpConnection) . $remoteDir);
        if (!$handler) {
            throw new Exception("Cannot open remote directory");
        }

        while ((($file_name = readdir($handler)) !== false)) {
            if ($file_name === '.' || $file_name === '..') {
                continue;
            }

            // you should check if it is a dir or file in callback
            $isDir = is_dir('ssh2.sftp://' . intval($this->sftpConnection) . $remoteDir . DIRECTORY_SEPARATOR . $file_name);
            call_user_func_array($callback, [$this, $remoteDir, $file_name, $isDir]);

//            Another Design, not so flexible
//            if(is_dir('ssh2.sftp://' . intval($this->sftpConnection) . $remoteDir.DIRECTORY_SEPARATOR.$file_name)){
//                $this->traversalOnRemoteDirectory($remoteDir.DIRECTORY_SEPARATOR.$file_name,$callback);
//            }else {
//                call_user_func_array($callback, [$remoteDir, $file_name]);
//            }
        }

        closedir($handler);
    }

    /**
     * @param string $remotePath
     * @return array
     */
    public function getRemoteFileState($remotePath)
    {
        return ssh2_sftp_stat($this->sftpConnection, $remotePath);
    }

    /**
     * Stats a symbolic link on the remote filesystem without following the link.
     * @param string $remotePath Path to the remote symbolic link.
     * @return array
     */
    public function getRemoteSymlinkState($remotePath)
    {
        return ssh2_sftp_lstat($this->sftpConnection, $remotePath);
    }

    /**
     * @param string $remotePath
     * @param int $mode
     * @return bool
     */
    public function changeModeOfRemoteItem($remotePath, $mode)
    {
        return ssh2_sftp_chmod($this->sftpConnection, $remotePath, $mode);
    }

    /**
     * @param string $remotePath
     * @return bool
     */
    public function removeRemoteFile($remotePath)
    {
        return ssh2_sftp_unlink($this->sftpConnection, $remotePath);
    }

    /**
     * @param string $remotePath
     * @return bool
     */
    public function removeRemoteDir($remotePath)
    {
        return ssh2_sftp_rmdir($this->sftpConnection, $remotePath);
    }

    /**
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function renameRemoteItem($from, $to)
    {
        return ssh2_sftp_rename($this->sftpConnection, $from, $to);
    }

    /**
     * Resolve the realpath of a provided path string
     * @param string $remotePath
     * @return string
     */
    public function getRealPathOfRemoteFile($remotePath)
    {
        return ssh2_sftp_realpath($this->sftpConnection, $remotePath);
    }

    /**
     * @param string $remoteLink
     * @return string
     */
    public function getSymlinkTarget($remoteLink)
    {
        return ssh2_sftp_readlink($this->sftpConnection, $remoteLink);
    }

    /**
     * Creates a symbolic link named link on the remote filesystem pointing to target.
     * @param string $target Target of the symbolic link.
     * @param string $link
     * @return bool
     */
    public function createRemoteSymlink($target, $link)
    {
        return ssh2_sftp_symlink($this->sftpConnection, $target, $link);
    }

    /**
     * @param string $dirname
     * @param int $mode
     * @param bool $recursive
     * @return bool
     */
    public function createRemoteDirectory($dirname, $mode = 0777, $recursive = false)
    {
        return ssh2_sftp_mkdir($this->sftpConnection, $dirname, $mode, $recursive);
    }
}