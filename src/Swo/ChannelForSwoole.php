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

use NetsvrBusiness\Contract\ChannelInterface;
use Swoole\Coroutine\Channel;

class ChannelForSwoole implements ChannelInterface
{
    protected Channel $channel;

    public function __construct(int $size = 1)
    {
        $this->channel = new Channel($size);
    }

    public function pop(int $millisecond = -1): mixed
    {
        return $this->channel->pop($millisecond === -1 ? -1 : $millisecond / 1000);
    }

    public function push(mixed $data, int $millisecond = -1): bool
    {
        return $this->channel->push($data, $millisecond === -1 ? -1 : $millisecond / 1000);
    }

    public function close(): void
    {
        $this->channel->close();
    }

    public function isClosed(): bool
    {
        return $this->channel->errCode === SWOOLE_CHANNEL_CLOSED;
    }

    public function getCapacity(): int
    {
        return $this->channel->capacity;
    }

    public function getLength(): int
    {
        return $this->channel->length();
    }
}