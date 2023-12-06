<?php
/**
 * Copyright 2023 buexplain@qq.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace NetsvrBusiness\Socket;

use NetsvrBusiness\Contract\SocketInterface;
use NetsvrBusiness\Exception\SocketConnectException;
use NetsvrBusiness\Exception\SocketReceiveException;
use NetsvrBusiness\Exception\SocketSendException;
use Psr\Log\LoggerInterface;
use Throwable;
use Swoole\Coroutine\Socket as BaseSocket;

/**
 *
 */
class Socket implements SocketInterface
{
    /**
     * @var string 日志前缀
     */
    protected string $logPrefix = '';

    /**
     * @var LoggerInterface 日志对象
     */
    protected LoggerInterface $logger;

    /**
     * netsvr网关的worker服务器监听的主机
     * @var string
     */
    protected string $host;
    /**
     * netsvr网关的worker服务器监听的端口
     * @var int
     */
    protected int $port;
    /***
     * 发送数据超时，单位秒
     * @var float
     */
    protected float $sendTimeout;
    /**
     * 接收数据超时，单位秒
     * @var float
     */
    protected float $receiveTimeout;

    /**
     * socket对象
     * @var BaseSocket|null
     */
    protected ?BaseSocket $socket = null;

    /**
     * @var bool 连接状态
     */
    protected bool $connected = false;

    /**
     * @param string $logPrefix
     * @param LoggerInterface $logger
     * @param string $host netsvr网关的worker服务器监听的主机
     * @param int $port netsvr网关的worker服务器监听的端口
     * @param float $sendTimeout 发送数据超时，单位秒
     * @param float $receiveTimeout 接收数据超时，单位秒
     */
    public function __construct(
        string          $logPrefix,
        LoggerInterface $logger,
        string          $host,
        int             $port,
        float           $sendTimeout,
        float           $receiveTimeout,
    )
    {
        $this->logPrefix = strlen($logPrefix) > 0 ? trim($logPrefix) . ' ' : '';
        $this->logger = $logger;
        $this->host = $host;
        $this->port = $port;
        $this->sendTimeout = $sendTimeout;
        $this->receiveTimeout = $receiveTimeout;
    }

    /**
     *
     */
    public function __destruct()
    {
        if ($this->socket instanceof BaseSocket) {
            try {
                $this->socket->close();
            } catch (Throwable) {
            }
            $this->socket = null;
        }
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * 判断连接是否正常
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * 关闭连接
     * @return void
     */
    public function close(): void
    {
        if ($this->connected && $this->socket instanceof BaseSocket) {
            $this->connected = false;
            if (!$this->socket->isClosed()) {
                $this->socket->close();
                $this->logger->info(sprintf($this->logPrefix . 'close connection %s:%s ok.',
                    $this->host,
                    $this->port,
                ));
            }
        }
    }

    /**
     * 发起连接
     * @return bool
     */
    public function connect(): bool
    {
        try {
            $socket = new BaseSocket(2, 1, 0);
            $socket->setProtocol([
                'open_length_check' => true,
                //大端序，详情请看：https://github.com/buexplain/netsvr/blob/main/internal/worker/manager/connProcessor.go#L127
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                /**
                 * 因为网关的包头包体协议的包头描述的长度是不含包头的，所以偏移4个字节
                 * @see https://github.com/buexplain/netsvr/blob/main/README.md#业务进程与网关之间的tcp数据包边界处理
                 */
                'package_body_offset' => 4,
                'package_max_length' => 1024 * 1024 * 2,
            ]);
            $socket->connect($this->host, $this->port, 3);
            if ($socket->errCode !== 0) {
                throw new SocketConnectException($socket->errMsg, $socket->errCode);
            }
            //先关闭旧的socket对象
            $this->close();
            //存储到本对象的属性
            $this->socket = $socket;
            $this->connected = true;
            $this->logger->info(sprintf($this->logPrefix . 'connect to %s:%s ok.',
                $this->host,
                $this->port,
            ));
            return true;
        } catch (Throwable $throwable) {
            $this->logger->error(sprintf($this->logPrefix . 'connect to %s:%s failed.%s%s',
                $this->host,
                $this->port,
                "\n",
                self::formatExp($throwable)
            ));
            return false;
        }
    }

    /**
     * 发送数据
     * @param string $data
     * @return bool
     */
    public function send(string $data): bool
    {
        try {
            $data = pack('N', strlen($data)) . $data;
            $ret = $this->socket->sendAll($data, $this->sendTimeout);
            if ($ret === false) {
                throw new SocketSendException($this->socket->errMsg, $this->socket->errCode);
            }
            if ($ret != strlen($data)) {
                throw new SocketSendException('short write', 0);
            }
            return true;
        } catch (Throwable $throwable) {
            $this->connected = false;
            $this->logger->error(sprintf($this->logPrefix . 'send to %s:%s failed.%s%s',
                $this->host,
                $this->port,
                "\n",
                self::formatExp($throwable)
            ));
            return false;
        }
    }

    /**
     * 接收数据
     * @return string|false
     */
    public function receive(): string|false
    {
        try {
            $ret = $this->socket->recvPacket($this->receiveTimeout);
            if ($ret === false) {
                if ($this->socket->errCode === 110 || ($this->socket->errCode === 116 && stripos(PHP_OS, 'win') !== false)) {
                    //SOCKET_ETIMEDOUT 110 Operation timed out
                    //116 Connection timed out
                    //超时，返回空字符串
                    return '';
                }
                throw new SocketReceiveException($this->socket->errMsg, $this->socket->errCode);
            }
            //对端关闭了连接
            if ($ret === '' && !$this->socket->checkLiveness()) {
                throw new SocketReceiveException('connection closed by peer', 0);
            }
            //截取掉包头部分
            return substr($ret, 4);
        } catch (Throwable $throwable) {
            $this->connected = false;
            $this->logger->error(sprintf($this->logPrefix . 'receive from %s:%s failed.%s%s',
                $this->host,
                $this->port,
                "\n",
                self::formatExp($throwable)
            ));
            return false;
        }
    }

    /**
     * @param Throwable $throwable
     * @return string
     */
    protected static function formatExp(Throwable $throwable): string
    {
        $message = $throwable->getMessage();
        $message = trim($message);
        if (strlen($message) == 0) {
            $message = get_class($throwable);
        }
        return sprintf(
            "%d --> %s in %s on line %d\nThrowable: %s\nStack trace:\n%s",
            $throwable->getCode(),
            $message,
            $throwable->getFile(),
            $throwable->getLine(),
            get_class($throwable),
            $throwable->getTraceAsString()
        );
    }
}
