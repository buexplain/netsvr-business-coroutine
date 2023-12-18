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

use NetsvrBusiness\Swo\WaitGroup;
use PHPUnit\Framework\TestCase;

class WaitGroupTest extends TestCase
{
    /**
     * composer test -- --filter=testWaitGroup
     * @return void
     */
    public function testWaitGroup(): void
    {
        $wg = new WaitGroup();
        $this->assertTrue($wg->wait());
        $wg = new WaitGroup(1);
        $this->assertFalse($wg->wait(20));
        $wg->done();
        $this->assertTrue($wg->wait());
    }
}