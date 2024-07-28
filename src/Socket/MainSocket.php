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

use Netsvr\Cmd;
use Netsvr\ConnClose;
use Netsvr\ConnOpen;
use Netsvr\RegisterReq;
use Netsvr\RegisterResp;
use Netsvr\Transfer;
use Netsvr\UnRegisterReq;
use Netsvr\UnRegisterResp;
use NetsvrBusiness\Contract\ChannelInterface;
use NetsvrBusiness\Contract\EventInterface;
use NetsvrBusiness\Contract\MainSocketInterface;
use NetsvrBusiness\Contract\SocketInterface;
use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use NetsvrBusiness\Exception\RegisterMainSocketException;
use NetsvrBusiness\Swo\Channel;
use NetsvrBusiness\Swo\Coroutine;
use NetsvrBusiness\Swo\WaitGroup;
use Psr\Log\LoggerInterface;
use Throwable;
use function NetsvrBusiness\workerAddrConvertToHex;
use function NetsvrBusiness\milliSleep;

/**
 * 主socket，用于：
 * 1. 接收网关单向发给business的指令，具体移步：https://github.com/buexplain/netsvr-protocol#网关单向转发给业务进程的指令
 * 2. business请求网关，无需网关响应的指令，具体移步：https://github.com/buexplain/netsvr-protocol#业务进程单向请求网关的指令
 */
class MainSocket implements MainSocketInterface
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
     * @var EventInterface 处理网关发送过来的事件的回调对象
     */
    protected EventInterface $event;
    /**
     * @var SocketInterface 与网关进行连接的socket对象
     */
    protected SocketInterface $socket;
    /**
     * @var string business进程向网关的worker服务器发送的心跳消息
     */
    protected string $workerHeartbeatMessage;
    /**
     * @var int 业务进程允许网关服务器转发的事件集合
     */
    protected int $events;
    /**
     * @var int 希望网关服务开启多少条协程来处理本连接的数据
     */
    protected int $processCmdGoroutineNum;
    /**
     * @var string 业务进程向网关发起注册后，网关返回的唯一id，取消注册的时候需要用到
     */
    protected string $connId = '';
    /**
     * @var ChannelInterface 向网关发送数据时，对socket对象提供保护的异步通道，避免多协程写一个socket对象
     */
    protected ChannelInterface $sendCh;
    /**
     * @var ChannelInterface 如果与网关断开，则需要重连，重连时的互斥锁
     */
    protected ChannelInterface $reconnectMux;
    /**
     * 心跳定时器
     * @var ChannelInterface
     */
    protected ChannelInterface $heartbeatTick;
    /**
     * @var int 与网关维持心跳的间隔毫秒数
     */
    protected int $heartbeatIntervalMillisecond;
    /**
     * @var bool 判断是否已经关闭自己
     */
    protected bool $closed = false;
    /**
     * @var WaitGroup 等待所有数据发送完毕
     */
    protected WaitGroup $wait;

    protected TaskSocketPoolMangerInterface $taskSocketPoolManger;

    /**
     * business进程向网关的worker服务器发送的心跳消息
     * @param string $logPrefix 日志前缀
     * @param LoggerInterface $logger
     * @param EventInterface $event 处理网关发送过来的事件的回调对象
     * @param SocketInterface $socket
     * @param string $workerHeartbeatMessage business进程向网关的worker服务器发送的心跳消息
     * @param int $events 业务进程允许网关服务器转发的事件集合
     * @param int $processCmdGoroutineNum 希望网关服务开启多少条协程来处理本连接的数据
     * @param int $heartbeatIntervalMillisecond
     * @param TaskSocketPoolMangerInterface $taskSocketPoolManger
     */
    public function __construct(
        string                        $logPrefix,
        LoggerInterface               $logger,
        EventInterface                $event,
        SocketInterface               $socket,
        string                        $workerHeartbeatMessage,
        int                           $events,
        int                           $processCmdGoroutineNum,
        int                           $heartbeatIntervalMillisecond,
        TaskSocketPoolMangerInterface $taskSocketPoolManger,
    )
    {
        $this->logPrefix = strlen($logPrefix) > 0 ? trim($logPrefix) . ' ' : '';
        $this->logger = $logger;
        $this->event = $event;
        $this->socket = $socket;
        $this->workerHeartbeatMessage = $workerHeartbeatMessage;
        $this->events = $events;
        $this->processCmdGoroutineNum = $processCmdGoroutineNum;
        $this->heartbeatIntervalMillisecond = $heartbeatIntervalMillisecond;
        $this->sendCh = new Channel(100);
        $this->reconnectMux = new Channel(1);
        $this->reconnectMux->push(time());//写入一个时间，接下来需要用它计算重连间隔
        $this->heartbeatTick = new Channel();
        $this->wait = new WaitGroup();
        $this->taskSocketPoolManger = $taskSocketPoolManger;
    }

    /**
     * 返回当前连接的netsvr网关的worker服务器监听的tcp地址
     * @return string
     */
    public function getWorkerAddr(): string
    {
        return $this->socket->getWorkerAddr();
    }

    /**
     * 定时心跳，保持连接的活跃
     * @return void
     */
    public function loopHeartbeat(): void
    {
        Coroutine::create(function () {
            try {
                while (!$this->heartbeatTick->pop($this->heartbeatIntervalMillisecond) && !$this->heartbeatTick->isClosed()) {
                    //必须先给计数器加一，否则因为push成功，协程切换到sendCh的消费者身上，导致消费者协程done失败，报错误：WaitGroup misuse: negative counter
                    $this->wait->add();
                    //再尝试发送心跳
                    if (!$this->sendCh->push($this->workerHeartbeatMessage, 20)) {
                        //待发送管道繁忙，则会导致发送心跳失败，计数器减1
                        $this->wait->done();
                    }
                }
            } finally {
                $this->logger->info(sprintf($this->logPrefix . 'loopHeartbeat %s quit.',
                    $this->socket->getWorkerAddr()
                ));
            }
        });
    }

    /**
     * 异步写入数据到网关服务
     * @return void
     */
    public function loopSend(): void
    {
        Coroutine::create(function () {
            try {
                while (true) {
                    try {
                        $data = $this->sendCh->pop();
                        if ($data === false) {
                            return;
                        }
                        try {
                            while (!$this->socket->send($data)) {
                                $this->reconnect();
                            }
                        } finally {
                            //无论是否发送成功，计数器必须减一
                            $this->wait->done();
                        }
                    } catch (Throwable $throwable) {
                        $this->logger->error(sprintf($this->logPrefix . 'loopSend %s failed.%s%s',
                            $this->socket->getWorkerAddr(),
                            PHP_EOL,
                            self::formatExp($throwable)
                        ));
                    }
                }
            } finally {
                $this->logger->info(sprintf($this->logPrefix . 'loopSend %s quit.',
                    $this->socket->getWorkerAddr(),
                ));
            }
        });
    }

    /**
     * 循环接收网关的数据
     * @return void
     */
    public function loopReceive(): void
    {
        $this->wait->add();
        Coroutine::create(function () {
            try {
                while (!$this->closed) {
                    try {
                        $data = $this->socket->receive();
                        if ($data === '') {
                            //读取超时
                            continue;
                        } elseif ($data === false) {
                            //读取失败，重连
                            $this->reconnect();
                        } else {
                            //得到网关传递的数据
                            $this->processEvent($data);
                        }
                    } catch (Throwable $throwable) {
                        $this->logger->error(sprintf($this->logPrefix . 'loopReceive %s failed.%s%s',
                            $this->socket->getWorkerAddr(),
                            PHP_EOL,
                            self::formatExp($throwable)
                        ));
                    }
                }
            } finally {
                $this->wait->done();
                $this->logger->info(sprintf($this->logPrefix . 'loopReceive %s quit.',
                    $this->socket->getWorkerAddr(),
                ));
            }
        });
    }

    /**
     * 连接到网关
     * @return bool
     */
    public function connect(): bool
    {
        return $this->socket->connect();
    }

    /**
     * 发送数据到网关
     * @param string $data
     * @return void
     */
    public function send(string $data): void
    {
        if ($this->sendCh->push($data)) {
            //写入待发送队列成功
            $this->wait->add();
        }
    }

    /**
     * @return bool
     */
    public function register(): bool
    {
        try {
            $req = new RegisterReq();
            $req->setEvents($this->events);
            $req->setProcessCmdGoroutineNum($this->processCmdGoroutineNum);
            if (!$this->socket->send(pack('N', Cmd::Register) . $req->serializeToString())) {
                return false;
            }
            $data = $this->socket->receive();
            if (!$data) {
                return false;
            }
            $resp = new RegisterResp();
            $resp->mergeFromString(substr($data, 4));
            if ($resp->getCode() == 0) {
                $this->connId = $resp->getConnId();
                $this->logger->info(sprintf($this->logPrefix . 'register to %s ok.', $this->socket->getWorkerAddr()));
                return true;
            }
            throw new RegisterMainSocketException($resp->getMessage(), $resp->getCode());
        } catch (Throwable $throwable) {
            $this->logger->error(sprintf($this->logPrefix . 'register to %s failed.%s%s',
                $this->socket->getWorkerAddr(),
                PHP_EOL,
                self::formatExp($throwable)
            ));
            return false;
        }
    }

    /**
     * 取消注册
     */
    public function unregister(): bool
    {
        if ($this->connId === '') {
            return true;
        }
        try {
            $req = new UnRegisterReq();
            $req->setConnId($this->connId);
            /**
             * @var null|TaskSocketInterface $socket
             */
            $socket = $this->taskSocketPoolManger->getSocket(workerAddrConvertToHex($this->socket->getWorkerAddr()));
            if (!$socket) {
                return false;
            }
            $data = pack('N', Cmd::Unregister) . $req->serializeToString();
            if (!$socket->send($data)) {
                return false;
            }
            for ($i = 0; $i < 3; $i++) {
                $ret = $socket->receive();
                if ($ret === false) {
                    return false;
                }
                if ($ret === '') {
                    continue;
                }
                break;
            }
            $resp = new UnRegisterResp();
            $resp->mergeFromString(substr($ret, 4));
            $this->connId = '';
            $this->logger->info(sprintf($this->logPrefix . 'unregister to %s ok.', $this->socket->getWorkerAddr()));
            return true;
        } catch (Throwable $throwable) {
            $this->logger->error(sprintf($this->logPrefix . 'unregister to %s failed.%s%s',
                $this->socket->getWorkerAddr(),
                PHP_EOL,
                self::formatExp($throwable)
            ));
            return false;
        } finally {
            if (isset($socket) && $socket instanceof TaskSocketInterface) {
                $socket->release();
            }
        }
    }

    /**
     * 关闭连接
     * @return void
     */
    public function close(): void
    {
        //判断是否已经执行过关闭函数
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        //先关闭心跳定时器
        $this->heartbeatTick->close();
        //等待所有数据处理成功
        $this->wait->wait();
        //关闭发送通道
        $this->sendCh->close();
        ///关闭底层的socket对象
        $this->socket->close();
        //关闭锁
        $this->reconnectMux->close();
    }

    /**
     * 重连到网关，并重新注册到网关
     */
    protected function reconnect(): void
    {
        $t = $this->reconnectMux->pop();
        if (is_int($t) && $t > 0) {
            try {
                if ($this->socket->isConnected()) {
                    return;
                }
                //距离上一次重连间隔小于三秒，则休眠三秒后再重连，避免疯狂重连消耗系统资源
                if (time() - $t < 3) {
                    milliSleep(3000);
                }
                if ($this->socket->connect() && !$this->closed) {
                    //如果执行过关闭动作，则说明进程即将结束，此时不必再注册，只需重连即可，目的是保证未发送出去的数据，有机会发送出去
                    $this->register();
                }
            } finally {
                $this->reconnectMux->push(time());
            }
        }
    }

    /**
     * 处理网关发送过来的消息
     * @param string $data
     * @return void
     */
    protected function processEvent(string $data): void
    {
        //客户端数据，开启一个协程去处理
        $this->wait->add();
        Coroutine::create(function () use ($data) {
            try {
                $cmd = unpack('N', substr($data, 0, 4));
                if (!is_array($cmd) || !isset($cmd[1])) {
                    return;
                }
                $cmd = $cmd[1];
                $protobuf = substr($data, 4);
                switch ($cmd) {
                    case Cmd::Transfer:
                        $tf = new Transfer();
                        $tf->mergeFromString($protobuf);
                        $this->event->onMessage($tf);
                        break;
                    case Cmd::ConnOpen:
                        $cp = new ConnOpen();
                        $cp->mergeFromString($protobuf);
                        $this->event->onOpen($cp);
                        break;
                    case Cmd::ConnClose:
                        $cc = new ConnClose();
                        $cc->mergeFromString($protobuf);
                        $this->event->onClose($cc);
                }
            } catch (Throwable $throwable) {
                $this->logger->error(sprintf($this->logPrefix . 'process netsvr event failed.%s%s', PHP_EOL, self::formatExp($throwable)));
            } finally {
                $this->wait->done();
            }
        });
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