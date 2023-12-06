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

namespace NetsvrBusiness\Contract;

/**
 * 连接池管理类
 */
interface TaskSocketPoolMangerInterface
{
    /**
     * 关闭所有连接池
     * @return void
     */
    public function close(): void;

    /**
     * 设置一个连接池
     * @param TaskSocketPoolInterface $pool
     * @return void
     */
    public function set(TaskSocketPoolInterface $pool): void;

    /**
     * 返回连接池的数量
     * @return int
     */
    public function count(): int;

    /**
     * 获取所有网关的连接
     * @return array|TaskSocketInterface[]
     */
    public function getSockets(): array;

    /**
     * 根据网关的serverId获取具体网关的连接
     * @param int $serverId
     * @return TaskSocketInterface|null
     */
    public function getSocket(int $serverId): ?TaskSocketInterface;
}