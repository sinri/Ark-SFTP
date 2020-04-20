<?php


namespace sinri\ark\sftp;


use Exception;

class ArkSSH2
{
    /**
     * List of key exchange methods to advertise, comma separated in order of preference.
     * `diffie-hellman-group1-sha1`,
     * `diffie-hellman-group14-sha1`,
     * and `diffie-hellman-group-exchange-sha1`
     */
    const METHOD_KEY_KEX = "kex";
    /**
     * List of hostkey methods to advertise, comma separated in order of preference.
     * `ssh-rsa` and `ssh-dss`
     */
    const METHOD_KEY_HOST_KEY = "hostkey";
    /**
     * Associative array containing crypt, compression,
     * and message authentication code (MAC) method preferences for messages sent from client to server.
     */
    const METHOD_KEY_CLIENT_TO_SERVER = "client_to_server";
    /**
     * Associative array containing crypt, compression,
     * and message authentication code (MAC) method preferences for messages sent from server to client.
     */
    const METHOD_KEY_SERVER_TO_CLIENT = "server_to_client";

    /**
     * List of crypto methods to advertise, comma separated in order of preference.
     * `rijndael-cbc@lysator.liu.se`,
     * `aes256-cbc`, `aes192-cbc`, `aes128-cbc`, `3des-cbc`, `blowfish-cbc`, `cast128-cbc`, `arcfour`,
     * and `none` (maybe disabled by libssh2 config)
     */
    const METHOD_VALUE_FOR_CS_CRYPT = "crypt";
    /**
     * List of compression methods to advertise, comma separated in order of preference.
     * `zlib`
     * and `none`
     */
    const METHOD_VALUE_FOR_CS_COMP = "comp";
    /**
     * List of MAC methods to advertise, comma separated in order of preference.
     * `hmac-sha1`, `hmac-sha1-96`, `hmac-ripemd160`, `hmac-ripemd160@openssh.com`,
     * and `none` (maybe disabled by libssh2 config)
     */
    const METHOD_VALUE_FOR_CS_MAC = "mac";

    /**
     * Name of function to call when an SSH2_MSG_IGNORE packet is received
     * void ignore_cb($message)
     */
    const CONNECTION_CALLBACK_KEY_IGNORE = 'ignore';
    /**
     * Name of function to call when an SSH2_MSG_DEBUG packet is received
     * void debug_cb($message, $language, $always_display)
     */
    const CONNECTION_CALLBACK_KEY_DEBUG = 'debug';
    /**
     * Name of function to call when a packet is received but the message authentication code failed.
     * If the callback returns TRUE, the mismatch will be ignored, otherwise the connection will be terminated.
     * bool macerror_cb($packet)
     */
    const CONNECTION_CALLBACK_KEY_MAC_ERROR = 'macerror';
    /**
     * Name of function to call when an SSH2_MSG_DISCONNECT packet is received
     * void disconnect_cb($reason, $message, $language)
     */
    const CONNECTION_CALLBACK_KEY_DISCONNECT = 'disconnect';

    /**
     * @var resource
     */
    protected $connection;

    /**
     * @param string $username
     * @param string $password
     * @param string $host
     * @param int $port
     * @return ArkSSH2
     * @throws Exception
     */
    public static function createConnectionWithPassword($username, $password, $host, $port = 22)
    {
        return (new ArkSSH2())->connect($host, $port)->authWithPassword($username, $password);
    }

    /**
     * ArkSSH2 constructor.
     * If connection given, use it directly.
     * @param null|resource $connection
     */
    public function __construct($connection = null)
    {
        $this->connection = $connection;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param string $host
     * @param int $port
     * @param null|array $methods
     * @param null|array $callbacks [CONNECTION_CALLBACK_KEY=>CALLBACK]
     * @return ArkSSH2
     * @throws Exception
     */
    public function connect($host, $port = 22, $methods = null, $callbacks = null)
    {
        $this->connection = ssh2_connect($host, $port, $methods, $callbacks);
        if (!$this->connection) {
            throw new Exception("Cannot connect to server");
        }
        return $this;
    }

    /**
     * @param string $username
     * @return $this
     * @throws Exception
     */
    public function authWithAgent(string $username)
    {
        $passed = ssh2_auth_agent($this->connection, $username);
        if (!$passed) {
            throw new Exception("Cannot auth with agent");
        }
        return $this;
    }

    /**
     * @param string $username
     * @param string $hostname
     * @param string $publicKeyFile
     * @param string $privateKeyFile
     * @param string|null $passPhrase If $privateKeyFile is encrypted (which it should be), the passphrase must be provided.
     * @param string|null $localUsername If $localUsername is omitted, then the value for $username will be used for it.
     * @return $this
     * @throws Exception
     * NOTE: ssh2_auth_hostbased_file() requires libssh2 >= 0.7 and PHP/SSH2 >= 0.7
     */
    public function authWithHostBasedFile($username,
                                          $hostname,
                                          $publicKeyFile,
                                          $privateKeyFile,
                                          $passPhrase = null,
                                          $localUsername = null)
    {
        $passed = ssh2_auth_hostbased_file(
            $this->connection,
            $username,
            $hostname,
            $publicKeyFile,
            $privateKeyFile,
            $passPhrase,
            $localUsername
        );
        if (!$passed) {
            throw new Exception("Cannot auth with host based file");
        }
        return $this;
    }

    /**
     * @param string $username
     * @param string[]|true $availableAuthMethods TRUE for connection established, or available methods fetched
     * @return $this
     */
    public function authWithNone($username, &$availableAuthMethods = null)
    {
        $availableAuthMethods = ssh2_auth_none($this->connection, $username);
        return $this;
    }

    /**
     * @param string $username
     * @param string $password
     * @return $this
     * @throws Exception
     */
    public function authWithPassword($username, $password)
    {
        $passed = ssh2_auth_password($this->connection, $username, $password);
        if (!$passed) {
            throw new Exception("Cannot auth with password");
        }
        return $this;
    }

    /**
     * @param string $username
     * @param string $publicKeyFile The public key file needs to be in OpenSSH's format. It should look something like: `ssh-rsa AAAAB3NzaC1yc2EAAA....NX6sqSnHA8= rsa-key-20121110`
     * @param string $privateKeyFile
     * @param null|string $passPhrase
     * @return $this
     * @throws Exception
     */
    public function authWithPublicKeyFile($username, $publicKeyFile, $privateKeyFile, $passPhrase = null)
    {
        $passed = ssh2_auth_pubkey_file($this->connection, $username, $publicKeyFile, $privateKeyFile, $passPhrase);
        if (!$passed) {
            throw new Exception("Cannot auth with public key");
        }
        return $this;
    }

    /**
     * Execute a command at the remote end and allocate a channel for it.
     * @param string $command
     * @param null|string $pty You should pass a pty emulation name ("vt102", "ansi", etc...) if you want to emulate a pty, or NULL if you don't.
     * @param null|array $env may be passed as an associative array of name/value pairs to set in the target environment.
     * @param int $width Width of the virtual terminal.
     * @param int $height Height of the virtual terminal.
     * @param int $width_height_type SSH2_TERM_UNIT_CHARS or SSH2_TERM_UNIT_PIXELS.
     * @return false|resource Returns a stream on success or FALSE on failure.
     */
    public function executeCommand($command, $pty = null, $env = null, $width = 80, $height = 25, $width_height_type = SSH2_TERM_UNIT_CHARS)
    {
        return ssh2_exec($this->connection, $command, $pty, $env, $width, $height, $width_height_type);
    }

    /**
     * Open a tunnel through a remote server
     * Open a socket stream to an arbitrary host/port by way of the currently connected SSH server.
     * @param string $host
     * @param int $port
     * @return resource
     */
    public function createTunnel($host, $port)
    {
        return ssh2_tunnel($this->connection, $host, $port);
    }

    /**
     * @param string $term_type should correspond to one of the entries in the target system's /etc/termcap file.
     * @param array|null $env may be passed as an associative array of name/value pairs to set in the target environment.
     * @param int $width Width of the virtual terminal.
     * @param int $height Height of the virtual terminal.
     * @param int $width_height_type SSH2_TERM_UNIT_CHARS or SSH2_TERM_UNIT_PIXELS
     * @return resource
     */
    public function createShell($term_type = 'vanilla', $env = null, $width = 80, $height = 25, $width_height_type = SSH2_TERM_UNIT_CHARS)
    {
        return ssh2_shell($this->connection, $term_type, $env, $width, $height, $width_height_type);
    }

    /**
     * Copy a file from the local filesystem to the remote server using the SCP protocol.
     * @param string $localPath
     * @param string $remotePath
     * @param int $createMode
     * @return bool
     */
    public function scpSend($localPath, $remotePath, $createMode = 0644)
    {
        return ssh2_scp_send($this->connection, $localPath, $remotePath, $createMode);
    }

    /**
     * Copy a file from the remote server to the local filesystem using the SCP protocol.
     * @param string $remotePath
     * @param string $localPath
     * @return bool
     */
    public function scpReceive($remotePath, $localPath)
    {
        return ssh2_scp_recv($this->connection, $remotePath, $localPath);
    }

    /**
     * Fetches an alternate substream associated with an SSH2 channel stream.
     * The SSH2 protocol currently defines only one substream, STDERR,
     * which has a substream ID of SSH2_STREAM_STDERR (defined as 1).
     *
     * @param int $streamId
     * @return resource
     */
    public function fetchStream($streamId)
    {
        return ssh2_fetch_stream($this->connection, $streamId);
    }

    /**
     * Sample Usage:
     *
     * $stdio_stream = ssh2_shell($connection);
     * $stderr_stream = ssh2_fetch_stream($stdio_stream, SSH2_STREAM_STDERR);
     *
     * @return resource
     */
    public function fetchStandardErrorStream()
    {
        return $this->fetchStream(SSH2_STREAM_STDERR);
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function disconnect()
    {
        $closed = ssh2_disconnect($this->connection);
        if (!$closed) {
            throw new Exception("Cannot disconnect from server");
        }
        return $this;
    }

    /**
     * @return ArkSFTP
     * @throws Exception
     */
    public function createSFTPInstance()
    {
        return (new ArkSFTP())->connect($this);
    }
}