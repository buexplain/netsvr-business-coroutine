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

namespace NetsvrBusinessTest\Cases;

use NetsvrBusiness\Swo\Channel;
use PHPUnit\Framework\TestCase;

class ChannelTest extends TestCase
{
    /**
     * composer test -- --filter=testChannelConstruct
     * @return void
     */
    public function testChannelConstruct(): void
    {
        $capacity = 10;
        $ch = new Channel($capacity);
        $this->assertTrue($ch->getCapacity() === $capacity);
        $ch = new Channel(0);
        $this->assertTrue($ch->getCapacity() === 0);
    }

    /**
     * composer test -- --filter=testChannelPush
     * @return void
     */
    public function testChannelPush(): void
    {
        $ch = new Channel(1);
        $this->assertTrue($ch->getLength() === 0);
        $this->assertTrue($ch->push(1));
        $this->assertTrue($ch->getLength() === 1);
        $this->assertFalse($ch->push(1, 20));
        $ch = new Channel(0);
        $this->assertFalse($ch->push(1, 20));
    }

    /**
     * composer test -- --filter=testChannelPop
     * @return void
     */
    public function testChannelPop(): void
    {
        //有缓存
        $ch = new Channel(1);
        $this->assertFalse($ch->pop(20));
        $this->assertTrue($ch->push(1));
        $this->assertTrue($ch->pop(20) === 1);
        $this->assertFalse($ch->pop(20));
        //无缓冲
        $ch = new Channel(0);
        $this->assertFalse($ch->pop(20));
        $this->assertFalse($ch->push(1, 20));
        $this->assertFalse($ch->pop(20));
    }

    /**
     * composer test -- --filter=testChannelClose
     * @return void
     */
    public function testChannelClose(): void
    {
        $ch = new Channel();
        $this->assertFalse($ch->isClosed());
        $ch->close();
        $this->assertTrue($ch->isClosed());
    }
}