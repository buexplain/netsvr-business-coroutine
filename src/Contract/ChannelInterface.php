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

interface ChannelInterface
{
    public function __construct(int $size = 1);

    /**
     * 返回值可以是任意类型的 PHP 变量，包括匿名函数和资源，通道被关闭时，执行失败返回 false
     * @param int $millisecond 毫秒，-1【表示永不超时】
     * @return mixed
     */
    public function pop(int $millisecond = -1): mixed;

    /**
     * push 数据 【可以是任意类型的 PHP 变量，包括匿名函数和资源】
     * @param mixed $data
     * @param int $millisecond 毫秒，-1【表示永不超时】
     * @return bool
     */
    public function push(mixed $data, int $millisecond = -1): bool;

    /**
     * 关闭通道。并唤醒所有等待读写的协程。
     * @return void
     */
    public function close(): void;

    /**
     * 判断通道是否关闭
     * @return bool
     */
    public function isClosed(): bool;

    /**
     * 通道缓冲区容量。
     * @return int
     */
    public function getCapacity(): int;

    /**
     * 获取通道中的元素数量。
     * @return int
     */
    public function getLength(): int;
}