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

use Closure;
use NetsvrBusiness\Contract\CoroutineInterface;
use Swoole\Coroutine;

class CoroutineForSwoole implements CoroutineInterface
{
    /**
     * @var Closure
     */
    protected Closure $callable;

    protected ?int $id = null;

    public function __construct(Closure $callable)
    {
        $this->callable = $callable;
    }

    public function execute(...$param): static
    {
        $this->id = Coroutine::create($this->callable, ...$param);
        return $this;
    }

    public static function create(callable $callable, ...$param): static
    {
        $coroutine = new self($callable);
        $coroutine->execute(...$param);
        return $coroutine;
    }
}