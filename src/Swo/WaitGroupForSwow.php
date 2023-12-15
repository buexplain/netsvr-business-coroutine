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

namespace NetsvrBusiness\Swo;

use NetsvrBusiness\Contract\WaitGroupInterface;
use Swow\Sync\WaitGroup;
use Throwable;

class WaitGroupForSwow implements WaitGroupInterface
{
    protected WaitGroup $waitGroup;

    public function __construct(int $delta = 0)
    {
        $this->waitGroup = new WaitGroup();
        if ($delta > 0) {
            $this->waitGroup->add($delta);
        }
    }

    public function add(int $delta = 1): void
    {
        $this->waitGroup->add($delta);
    }

    public function done(): void
    {
        $this->waitGroup->done();
    }

    /**
     * 等待所有任务完成恢复当前协程的执行
     * @param int $millisecond 毫秒，-1【表示永不超时】
     * @return bool
     */
    public function wait(int $millisecond = -1): bool
    {
        try {
            $this->waitGroup->wait($millisecond);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}