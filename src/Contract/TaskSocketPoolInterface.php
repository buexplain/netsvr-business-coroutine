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
 *
 */
interface TaskSocketPoolInterface
{
    /**
     * @return int 网关的唯一编号
     */
    public function getServerId(): int;

    /**
     * 从连接池得到一个连接
     * @return TaskSocketInterface
     */
    public function get(): TaskSocketInterface;

    /**
     * 将连接归还给连接池
     * @param TaskSocketInterface|null $socket
     * @return void
     */
    public function release(TaskSocketInterface|null $socket): void;

    /**
     * 关闭整个连接池
     * @return void
     */
    public function close(): void;
}