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

use Netsvr\Constant;
use NetsvrBusiness\Contract\TaskSocketFactoryInterface;
use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolInterface;
use NetsvrBusiness\Swo\Channel;
use NetsvrBusiness\Swo\Coroutine;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 *
 */
class TaskSocketPool implements TaskSocketPoolInterface
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
     * @var Channel 连接池
     */
    protected Channel $pool;
    /**
     * @var int 当前的连接数
     */
    protected int $num = 0;
    /**
     * @var TaskSocketFactoryInterface taskSocket对象的构造工厂
     */
    protected TaskSocketFactoryInterface $factory;
    /**
     * @var int 网关的编号，必须与网关服务的netsvr.toml配置文件的配置一致
     */
    protected int $serverId;
    /**
     * @var int 获取连接超时
     */
    protected int $waitTimeoutMillisecond;

    /**
     * @var int 与网关维持心跳的间隔毫秒数
     */
    protected int $heartbeatIntervalMillisecond;

    /**
     * 心跳定时器
     * @var Channel
     */
    protected Channel $heartbeatTick;

    /**
     * @param string $logPrefix
     * @param LoggerInterface $logger
     * @param int $size
     * @param TaskSocketFactoryInterface $factory
     * @param int $serverId
     * @param int $waitTimeoutMillisecond
     * @param int $heartbeatIntervalMillisecond
     */
    public function __construct(
        string                     $logPrefix,
        LoggerInterface            $logger,
        int                        $size,
        TaskSocketFactoryInterface $factory,
        int                        $serverId,
        int                        $waitTimeoutMillisecond,
        int                        $heartbeatIntervalMillisecond,
    )
    {
        $this->logPrefix = strlen($logPrefix) > 0 ? trim($logPrefix) . ' ' : '';
        $this->logger = $logger;
        $this->factory = $factory;
        $this->pool = new Channel($size);
        $this->serverId = $serverId;
        $this->waitTimeoutMillisecond = $waitTimeoutMillisecond;
        $this->heartbeatIntervalMillisecond = $heartbeatIntervalMillisecond;
        $this->heartbeatTick = new Channel();
        $this->loopHeartbeat();
    }

    /**
     * @return int 返回网关的唯一id
     */
    public function getServerId(): int
    {
        return $this->serverId;
    }

    /**
     * 开启心跳
     * @return void
     */
    protected function loopHeartbeat(): void
    {
        Coroutine::create(function () {
            try {
                while (!$this->heartbeatTick->pop($this->heartbeatIntervalMillisecond) && !$this->heartbeatTick->isClosed()) {
                    $ret = [];
                    for ($i = 0; $i < $this->pool->getCapacity(); $i++) {
                        $socket = $this->pool->pop(20);
                        if (!$socket instanceof TaskSocketInterface) {
                            continue;
                        }
                        try {
                            $id = spl_object_id($socket);
                            if (!isset($ret[$id])) {
                                $ret[$id] = true;
                                $socket->send(Constant::PING_MESSAGE);
                                $socket->receive();
                            }
                        } catch (Throwable) {
                        } finally {
                            $socket->release();
                        }
                    }
                }
            } finally {
                $socket = $this->pool->pop(20);
                if ($socket instanceof TaskSocketInterface) {
                    $host = $socket->getHost();
                    $port = $socket->getPort();
                    $socket->release();
                    $this->logger->info(sprintf($this->logPrefix . 'loopHeartbeat %s:%s quit.',
                        $host,
                        $port
                    ));
                } else {
                    $this->logger->info($this->logPrefix . 'loopHeartbeat quit.');
                }
            }
        });
    }

    /**
     * @throws Throwable
     */
    public function get(): TaskSocketInterface
    {
        //代码的cpu执行权力从这里开始
        if ($this->pool->getLength() === 0 && $this->num < $this->pool->getCapacity()) {
            try {
                //到下面一行为止，不能发生cpu执行权力让渡，否则会导致连接创建溢出
                ++$this->num;
                return $this->factory->make($this);
            } catch (Throwable $throwable) {
                --$this->num;
                throw $throwable;
            }
        }
        $connection = $this->pool->pop($this->waitTimeoutMillisecond);
        if (!$connection instanceof TaskSocketInterface) {
            throw new RuntimeException('TaskSocketPool pool exhausted. Cannot establish new connection before wait_timeout.');
        }
        return $connection;
    }

    /**
     * 将连接归还给连接池
     * @param TaskSocketInterface|null $socket
     * @return void
     */
    public function release(?TaskSocketInterface $socket): void
    {
        if ($socket instanceof TaskSocketInterface) {
            $this->pool->push($socket);
        } else {
            --$this->num;
        }
    }

    /**
     * 关闭整个连接池
     * @return void
     */
    public function close(): void
    {
        //如果是swoole的onWorkerExit触发了本方法，则可能会被持续触发，所以个加个判断，如果触发过，则不必再次触发
        if ($this->heartbeatTick->isClosed()) {
            return;
        }
        //先关闭心跳定时器
        $this->heartbeatTick->close();
        //再关闭底层的socket
        while ($this->pool->getLength() > 0) {
            /**
             * @var TaskSocketInterface $socket
             */
            $socket = $this->pool->pop();
            $socket->close();
            $this->release(null);
        }
    }
}