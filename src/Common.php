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

namespace NetsvrBusiness;

use Psr\Container\ContainerInterface;

/**
 * 这个是存放公共的静态数据的类
 * 目的是为了共享Psr容器无法注入的数据
 */
class Common
{
    /**
     * 容器对象实例
     * @var ContainerInterface|null
     */
    public static ContainerInterface|null $container = null;

    /**
     * @var int 当前进程的编号，不是进程的pid
     */
    public static int $workerProcessId = 0;
}