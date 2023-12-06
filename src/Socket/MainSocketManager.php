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

use NetsvrBusiness\Contract\MainSocketInterface;
use NetsvrBusiness\Contract\MainSocketManagerInterface;
use NetsvrBusiness\Exception\DuplicateServerIdException;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Throwable;

/**
 *
 */
class MainSocketManager implements MainSocketManagerInterface
{
    /**
     * 持有所有与网关进行连接的mainSocket对象
     * @var array|MainSocketInterface[]
     */
    protected array $pool = [];

    /**
     * @var bool 是否已经调用过start方法
     */
    protected bool $status = false;

    /**
     * 返回所有与网关服务器连接的mainSocket对象
     * @return array|MainSocketInterface[]
     */
    public function getSockets(): array
    {
        return $this->status ? $this->pool : [];
    }

    /**
     * 返回与serverId对应的网关服务器连接的mainSocket对象
     * @param int $serverId
     * @return MainSocketInterface|null
     */
    public function getSocket(int $serverId): ?MainSocketInterface
    {
        return $this->status ? ($this->pool[$serverId] ?? null) : null;
    }

    /**
     * @param MainSocketInterface $socket
     * @return void
     * @throws DuplicateServerIdException
     */
    public function set(MainSocketInterface $socket): void
    {
        if (isset($this->pool[$socket->getServerId()])) {
            throw new DuplicateServerIdException('serverId option in file business.php is duplicate: ' . $socket->getServerId());
        }
        $this->pool[$socket->getServerId()] = $socket;
    }

    /**
     * @return bool
     */
    protected function connect(): bool
    {
        $wg = new WaitGroup();
        $ret = true;
        foreach ($this->pool as $socket) {
            $wg->add();
            Coroutine::create(function () use ($socket, $wg, &$ret) {
                try {
                    if (!$socket->connect()) {
                        $ret = false;
                    }
                } catch (Throwable) {
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
        return $ret;
    }

    /**
     * @return bool
     */
    protected function register(): bool
    {
        $wg = new WaitGroup();
        $ret = true;
        foreach ($this->pool as $socket) {
            $wg->add();
            Coroutine::create(function () use ($socket, $wg, &$ret) {
                try {
                    if ($socket->register()) {
                        $socket->loopSend();
                        $socket->loopReceive();
                        $socket->loopHeartbeat();
                    } else {
                        $ret = false;
                    }
                } catch (Throwable) {
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
        return $ret;
    }

    /**
     * 让所有的mainSocket开始与网关进行交互
     * @return bool
     */
    public function start(): bool
    {
        if ($this->status) {
            return true;
        }
        $this->status = $this->connect() && $this->register();
        return $this->status;
    }

    /**
     * @return void
     */
    protected function unregister(): void
    {
        $wg = new WaitGroup();
        foreach ($this->pool as $socket) {
            $wg->add();
            Coroutine::create(function () use ($socket, $wg) {
                try {
                    //尝试三次进行取消注册操作，无论是否失败，都会强制关闭连接
                    for ($i = 0; $i < 3; $i++) {
                        if ($socket->unregister()) {
                            break;
                        }
                    }
                } catch (Throwable) {
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
    }

    /**
     * @return void
     */
    public function close(): void
    {
        if (!$this->status) {
            return;
        }
        $this->status = false;
        $this->unregister();
        foreach ($this->pool as $socket) {
            $socket->close();
        }
    }
}