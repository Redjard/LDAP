<?php
/**
 * This file is part of the FreeDSx package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Ldap\Tcp;

use FreeDSx\Ldap\Exception\ConnectionException;

/**
 * Represents a TCP socket.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Socket
{
    /**
     * @var bool
     */
    protected $isEncrypted = false;

    /**
     * @var resource|null
     */
    protected $socket;

    /**
     * @var resource|null
     */
    protected $context;

    /**
     * @var int
     */
    protected $bufferSize = 8192;

    /**
     * @var string
     */
    protected $errorMessage;

    /**
     * @var int
     */
    protected $errorNumber;

    /**
     * @var array
     */
    protected $sslOpts = [
        'allow_self_signed' => false,
        'verify_peer' => true,
        'verify_peer_name' => true,
        'capture_peer_cert' => true,
        'capture_peer_cert_chain' => true,
    ];

    /**
     * @var array
     */
    protected $options = [
        'port' => 389,
        'use_ssl' => false,
        'ssl_validate_cert' => true,
        'ssl_allow_self_signed' => null,
        'ssl_ca_cert' => null,
        'ssl_peer_name' => null,
        'timeout_connect' => 3,
        'timeout_read' => 15,
        'crypto_type' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT,
    ];

    /**
     * @param null $resource
     * @param array $options
     */
    public function __construct($resource = null, array $options = [])
    {
        $this->socket = $resource;
        $this->options = array_merge($this->options, $options);
    }

    /**
     * @param bool $block
     * @return string|false
     */
    public function read(bool $block = true)
    {
        $data = false;

        stream_set_blocking($this->socket, $block);
        while (strlen($buffer = fread($this->socket, $this->bufferSize)) > 0) {
            $data .= $buffer;
            if ($block) {
                $block = false;
                stream_set_blocking($this->socket, false);
            }
        }

        return $data;
    }

    /**
     * @param string $data
     * @return $this
     */
    public function write(string $data)
    {
        @fwrite($this->socket, $data);

        return $this;
    }

    /**
     * @param bool $block
     * @return $this
     */
    public function block(bool $block)
    {
        stream_set_blocking($this->socket, $block);

        return $this;
    }

    /**
     * @return bool
     */
    public function isConnected() : bool
    {
        return is_resource($this->socket);
    }

    /**
     * @return bool
     */
    public function isEncrypted() : bool
    {
        return $this->isEncrypted;
    }

    /**
     * @return $this
     */
    public function close()
    {
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        $this->isEncrypted = false;
        $this->context = null;

        return $this;
    }

    /**
     * Enable/Disable encryption on the TCP connection stream.
     *
     * @param bool $encrypt
     * @return $this
     * @throws ConnectionException
     */
    public function encrypt(bool $encrypt)
    {
        stream_set_blocking($this->socket, true);
        $result = stream_socket_enable_crypto($this->socket, $encrypt, $this->options['crypto_type']);
        stream_set_blocking($this->socket, false);

        if ($result === false) {
            throw new ConnectionException(sprintf(
                'Unable to %s encryption on TCP connection. %s',
                $encrypt ? 'enable' : 'disable',
                $this->errorMessage
            ));
        }
        $this->isEncrypted = $encrypt;

        return $this;
    }

    /**
     * @param string $host
     * @return $this
     * @throws ConnectionException
     */
    public function connect(string $host)
    {
        $uri = ($this->options['use_ssl'] ? 'ssl' : 'tcp').'://'.$host.':'.$this->options['port'];

        $this->socket = @stream_socket_client(
            $uri,
            $this->errorNumber,
            $this->errorMessage,
            $this->options['timeout_connect'],
            STREAM_CLIENT_CONNECT,
            $this->createSocketContext()
        );
        if ($this->socket === false) {
            throw new ConnectionException(sprintf(
                'Unable to connect to %s: %s',
                $host,
                $this->errorMessage
            ));
        }
        $this->isEncrypted = $this->options['use_ssl'];

        return $this;
    }

    /**
     * Create a socket by connecting to a specific host.
     *
     * @param string $host
     * @param array $options
     * @return Socket
     */
    public static function create(string $host, array $options = [])
    {
        return (new self(null, $options))->connect($host);
    }

    /**
     * @return resource
     */
    protected function createSocketContext()
    {
        $sslOpts = $this->sslOpts;

        if (isset($this->options['ssl_allow_self_signed'])) {
            $sslOpts['allow_self_signed'] = $this->options['ssl_allow_self_signed'];
        }
        if (isset($this->options['ssl_ca_cert'])) {
            $sslOpts['ca_file'] = $this->options['ssl_ca_cert'];
        }
        if (isset($this->options['ssl_peer_name'])) {
            $sslOpts['peer_name'] = $this->options['ssl_peer_name'];
        }

        $sslOpts['crypto_type'] = $this->options['crypto_type'];
        if ($this->options['ssl_validate_cert'] === false) {
            $sslOpts = array_merge($sslOpts, [
                'allow_self_signed' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]);
        }
        $this->context = stream_context_create([
            'ssl' => $sslOpts,
        ]);

        return $this->context;
    }
}