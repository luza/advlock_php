<?php

namespace Advlock;

use Advlock\Exception\ServiceException;
use Advlock\Exception\TransportException;

class Advlock
{
    const CODE_OK               = 0;
    const CODE_ALREADY_ACQUIRED = 1;
    const CODE_EMPTY_KEY        = 2;
    const CODE_UNKNOWN_COMMAND  = 3;
    const CODE_NOT_ACQUIRED     = 4;

    /**
     * @var string
     */
    protected $protocolVersion = '0.1';

    /**
     * @var string
     */
    protected $dsn;

    /**
     * @var resource
     */
    protected $connection;

    /**
     * @var int
     */
    protected $connectionTimeout;

    /**
     * @var int
     */
    protected $readTimeout;


    /**
     * @param string $dsn ex: tcp://127.0.0.1:49915
     * @param int $connectionTimeout In seconds
     * @param int $readTimeout In seconds
     */
    public function __construct($dsn, $connectionTimeout = 60, $readTimeout = 4)
    {
        $this->dsn = $dsn;
        $this->connectionTimeout = $connectionTimeout;
        $this->readTimeout = $readTimeout;
    }

    public function __destruct()
    {
        if ($this->connection) {
            $this->close();
        }
    }

    /**
     * Do the initial exchange
     *
     * @throws TransportException
     */
    protected function handshake()
    {
        $command = sprintf("%s\n", $this->protocolVersion);
        $this->write($command);
    }

    /**
     * Establishes connection to the server
     *
     * @return resource
     * @throws TransportException
     */
    protected function getConnection()
    {
        if (!$this->connection || feof($this->connection)) {
            $this->connection = @fsockopen($this->dsn, -1, $errno, $errstr, $this->connectionTimeout);
            if (!$this->connection) {
                throw new TransportException("Could not establish connection ($errstr)", $errno);
            }
            @stream_set_timeout($this->connection, $this->readTimeout);
            $this->handshake();
        }
        return $this->connection;
    }

    /**
     * Process response from the server
     *
     * @param string $response
     * @return array
     * @throws TransportException
     */
    protected function processResponse($response)
    {
        list($code, $message) = sscanf($response, "%03d,%[^\n]s");
        if (is_null($code) || is_null($message)) {
            throw new TransportException("Malformed response received ($response)");
        }
        return [$code, $message];
    }

    /**
     * Wrapper for send operation with error handling
     *
     * @param string $data
     * @throws TransportException
     */
    protected function write($data)
    {
        if (fwrite($this->getConnection(), $data) === false) {
            throw new TransportException('Could not write to socket');
        }
    }

    /**
     * Wrapper for getting a line with error handling
     *
     * @return string
     * @throws TransportException
     */
    protected function gets()
    {
        $data = @fgets($this->getConnection());
        if ($data === false) {
            throw new TransportException('Could not read from socket');
        }
        return $data;
    }

    /**
     * Acquires lock for a resource with specified key
     *
     * @param string $key
     * @return bool
     */
    public function set($key)
    {
        $this->write(sprintf("set %s\n", $key));
        list($code,) = $this->processResponse($this->gets());
        return ($code == self::CODE_OK);
    }

    /**
     * Releases the previously acquired lock
     *
     * @param string $key
     * @return bool
     */
    public function del($key)
    {
        $this->write(sprintf("del %s\n", $key));
        list($code,) = $this->processResponse($this->gets());
        return ($code == self::CODE_OK);
    }

    /**
     * Explicitly close the connection
     */
    public function close()
    {
        if ($this->connection) {
            @fclose($this->connection);
            $this->connection = null;
        }
    }
}
