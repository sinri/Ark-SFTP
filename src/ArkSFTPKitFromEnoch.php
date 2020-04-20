<?php


namespace sinri\ark\sftp;


use Exception;

/**
 * Class ArkSFTPKit
 * @package sinri\ark\sftp
 * This class is migrated from Enoch
 * @deprecated Just as a sample
 */
class ArkSFTPKitFromEnoch
{
    protected $strServer = "";
    protected $strServerPort = "22";
    protected $strServerUsername = "";
    protected $strServerPassword = "";

    public function __construct($serverAddress, $serverPort, $serverUsername, $serverPassword)
    {
        $this->strServer = $serverAddress;
        $this->strServerPort = $serverPort;
        $this->strServerUsername = $serverUsername;
        $this->strServerPassword = $serverPassword;
    }

    public function sendFileToSFtp($filename, $remoteDir, $localDir, &$error = '')
    {
        try {
            $ssh2 = ArkSSH2::createConnectionWithPassword($this->strServerUsername, $this->strServerPassword, $this->strServer, $this->strServerPort);
//            $ssh2 = (ArkSSH2::buildSSH2($this->strServer, $this->strServerPort))
//                ->authWithPassword($this->strServerUsername, $this->strServerPassword);
            $sftp = $ssh2->createSFTPInstance();

            $remote_path = $remoteDir . '/' . $filename;
            $local_path = $localDir . '/' . $filename;
            $sftp->transcribeFileToSFTP($local_path, $remote_path);

            $ssh2->disconnect();
            return true;
        } catch (Exception $e) {
            $error = $e->getMessage();
            return false;
        }

        //////

//        $sftpStream = null;
//        try {
//            $resConnection = ssh2_connect($this->strServer, $this->strServerPort);
//
//            if (!$resConnection) {
//                throw new Exception(
//                    "Failed to link to SSH2 server: " .
//                    $this->strServer . ":" . $this->strServerPort . "!"
//                );
//            }
//
//            $auth_passed = ssh2_auth_password($resConnection, $this->strServerUsername, $this->strServerPassword);
//            if (!$auth_passed) {
//                throw new Exception("Auth Failed");
//            }
//            //初始化SFTP子系统
//            //请求从一个已经连接子系统SFTP服务器SSH2安全性会更高。
//            $resSFTP = ssh2_sftp($resConnection);
//
//            $remote_path = $remoteDir . '/' . $filename;
//            $local_path = $localDir . '/' . $filename;
//
//            //added intval process 20170802 by Sinri
//            //https://stackoverflow.com/questions/41118475/segmentation-fault-on-fopen-using-sftp-and-ssh2
//            $sftpStream = fopen('ssh2.sftp://' . intval($resSFTP) . $remote_path, 'w');
//
//            if (!$sftpStream) {
//                throw new Exception("Could not open remote file: " . $remote_path);
//            }
//
//            $data_to_send = file_get_contents($local_path);
//
//            if ($data_to_send === false) {
//                throw new Exception("Could not open local file: " . $local_path);
//            }
//
//            if (fwrite($sftpStream, $data_to_send) === false) {
//                throw new Exception("Could not send data from file: ");
//            }
//            $done = true;
//        } catch (Exception $e) {
//            $error = __METHOD__ . ' filename:' . $filename . ' Exception: ' . $e->getMessage();
//            $done = false;
//        } finally {
//            //this keyword is not available until PHP 5.5
//            fclose($sftpStream);
//        }
//        return $done;
    }

    public function downloadAndRemoveDir($remoteDir, $localPath, &$doneFiles = [], &$error = '')
    {
        try {
            $ssh2 = ArkSSH2::createConnectionWithPassword($this->strServerUsername, $this->strServerPassword, $this->strServer, $this->strServerPort);
//            $ssh2 = (ArkSSH2::buildSSH2($this->strServer, $this->strServerPort))
//                ->authWithPassword($this->strServerUsername, $this->strServerPassword);
            $sftp = $ssh2->createSFTPInstance();

            $files = [];
            $doneFiles = [];

            $sftp->traversalOnRemoteDirectory(
                $remoteDir,
                function (
                    ArkSFTP $sftpInstance,
                    string $remoteParentDir,
                    string $remoteTargetItem,
                    bool $isDir)
                use ($localPath, $files, $doneFiles) {
                    if ($remoteTargetItem == 'archive') {
                        return;
                    }

                    $files[] = $remoteTargetItem;

                    $remote_file = $remoteParentDir . '/' . $remoteTargetItem;
                    $local_file = $localPath . '/' . $remoteTargetItem;

                    $sftpInstance->transcribeFileFromSFTP($remote_file, $local_file);

                    $content = file_get_contents($local_file);
                    $content = iconv('gbk', 'utf-8', $content);
                    file_put_contents($local_file, $content);

                    $remove_file = $remoteParentDir . '/archive/' . $remoteTargetItem;
                    $sftpInstance->renameRemoteItem($remote_file, $remove_file);

                    $doneFiles[] = $remoteTargetItem;

//                    //将远程文件保存到本地
//                    $content = file_get_contents('ssh2.sftp://' . intval($resSFTP) . $remoteDir . $file_name, 'rw');
//                    //$result2 = '';
//                    $get_content = iconv('gbk', 'utf-8', $content);
//
//                    $data_to_write = fopen($local_file, 'w+');
//                    fwrite($data_to_write, $get_content);
//
//                    fclose($data_to_write);
//
//                    //将远程文件删除
//                    // sh2_sftp_unlink($resSFTP, $remote_file);
//                    //移动远程文件
//                    $remove_file = $remoteDir . 'archive/' . $file_name;
//                    //$res =
//                    ssh2_sftp_rename($resSFTP, $remote_file, $remove_file);
//
//                    $doneFiles[] = $file_name;

                }
            );

            $ssh2->disconnect();
            return true;
        } catch (Exception $e) {
            $error = $e->getMessage();
            return false;
        }

//        $handler = null;
//        try {
//            $resConnection = ssh2_connect($this->strServer, $this->strServerPort);
//            if (!$resConnection) {
//                throw new Exception("Could not connect: " . $this->strServer . ":" . $this->strServerPort);
//            }
//            if (ssh2_auth_password($resConnection, $this->strServerUsername, $this->strServerPassword)) {
//                $resSFTP = ssh2_sftp($resConnection);
//            } else {
//                throw new Exception("ssh2_auth_password false");
//            }
//
//            $handler = opendir('ssh2.sftp://' . intval($resSFTP) . $remoteDir);
//
//            $i = 0;
//            $files = array();
//            while (($i < 5) && (($file_name = readdir($handler)) !== false)) {//务必使用!==，防止目录下出现类似文件名“0”等情况
//                if ($file_name != "." && $file_name != ".." && $file_name != "archive") {
//                    $files[] = $file_name;
//
//                    $remote_file = $remoteDir . $file_name;
//                    $local_file = $localPath . $file_name;
//
//                    //将远程文件保存到本地
//                    $content = file_get_contents('ssh2.sftp://' . intval($resSFTP) . $remoteDir . $file_name, 'rw');
//                    //$result2 = '';
//                    $get_content = iconv('gbk', 'utf-8', $content);
//
//                    $data_to_write = fopen($local_file, 'w+');
//                    fwrite($data_to_write, $get_content);
//
//                    fclose($data_to_write);
//
//                    //将远程文件删除
//                    // sh2_sftp_unlink($resSFTP, $remote_file);
//                    //移动远程文件
//                    $remove_file = $remoteDir . 'archive/' . $file_name;
//                    //$res =
//                    ssh2_sftp_rename($resSFTP, $remote_file, $remove_file);
//
//                    $doneFiles[] = $file_name;
//
//                    $i++;
//                }
//            }
//            $done = true;
//        } catch (Exception $e) {
//            $error = 'Method ' . __METHOD__ . ' Exception: ' . $e->getMessage();
//            $done = false;
//        }
//        closedir($handler);
//        return $done;
    }

    public function renameSftpFiles($remoteDir, $oldName, $newName, &$error = '')
    {
        try {
            $ssh2 = ArkSSH2::createConnectionWithPassword($this->strServerUsername, $this->strServerPassword, $this->strServer, $this->strServerPort);
//            $ssh2 = (ArkSSH2::buildSSH2($this->strServer, $this->strServerPort))
//                ->authWithPassword($this->strServerUsername, $this->strServerPassword);
            $sftp = $ssh2->createSFTPInstance();

            $old_path = $remoteDir . '/' . $oldName;
            $new_path = $remoteDir . '/' . $newName;

            $stat_info = $sftp->getRemoteFileState($old_path);
            if (empty($stat_info['size'])) {
                throw new Exception('文件已经不存在');
            }

            $done = $sftp->renameRemoteItem($old_path, $new_path);
            if (!$done) {
                throw new Exception("Cannot rename!");
            }

            $ssh2->disconnect();
            return true;
        } catch (Exception $e) {
            $error = $e->getMessage();
            return false;
        }

//        try {
//            $resConnection = ssh2_connect($this->strServer, $this->strServerPort);
//            if (!$resConnection) {
//                throw new Exception("Could not connect: " . $this->strServer . ":" . $this->strServerPort);
//            }
//            if (ssh2_auth_password($resConnection, $this->strServerUsername, $this->strServerPassword)) {
//                $resSFTP = ssh2_sftp($resConnection);
//            } else {
//                throw new Exception("ssh2_auth_password false");
//            }
//
//            $oldpath = $remoteDir . '/' . $oldName;
//            $newpath = $remoteDir . '/' . $newName;
//            $statinfo = ssh2_sftp_stat($resSFTP, $oldpath);
//            if (!empty($statinfo['size'])) {
//                var_dump($statinfo['size']);
//            } else {
//                $error = '文件已经不存在';
//            }
//
//            $done = ssh2_sftp_rename($resSFTP, $oldpath, $newpath);
//        } catch (Exception $e) {
//            $error = 'Method ' . __METHOD__ . ' Exception: ' . $e->getMessage();
//            var_dump($error);
//
//            $done = false;
//        }
//        return $done;
    }
}