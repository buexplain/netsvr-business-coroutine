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

use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use Throwable;

/**
 *
 */
class TaskSocketPoolManger implements TaskSocketPoolMangerInterface
{
    /**
     * @var array|TaskSocketPoolInterface[]
     */
    protected array $pools = [];

    /**
     * 关闭所有连接池
     * @return void
     */
    public function close(): void
    {
        $pools = $this->pools;
        $this->pools = [];
        foreach ($pools as $pool) {
            $pool->close();
        }
    }

    /**
     * 设置一个连接池
     * @param TaskSocketPoolInterface $pool
     * @return void
     */
    public function set(TaskSocketPoolInterface $pool): void
    {
        $this->pools[$pool->getServerId()] = $pool;
    }

    /**
     * 返回连接池的数量
     * @return int
     */
    public function count(): int
    {
        return count($this->pools);
    }

    /**
     * @throws Throwable
     */
    public function getSockets(): array
    {
        /**
         * @var TaskSocketInterface[] $ret
         */
        $ret = [];
        foreach ($this->pools as $pool) {
            try {
                $ret[] = $pool->get();
            } catch (Throwable $throwable) {
                //有一个池子没拿成功，则将已经拿出来的归还掉，并抛出异常
                foreach ($ret as $item) {
                    $item->release();
                }
                throw $throwable;
            }
        }
        return $ret;
    }

    /**
     * 根据网关的serverId获取具体网关的连接
     * @param int $serverId
     * @return TaskSocketInterface|null
     */
    public function getSocket(int $serverId): ?TaskSocketInterface
    {
        if (isset($this->pools[$serverId])) {
            return $this->pools[$serverId]->get();
        }
        return null;
    }
}