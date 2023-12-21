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

/**
 * @param int $millisecond
 * @return void
 */
function milliSleep(int $millisecond): void
{
    if (extension_loaded('swoole')) {
        \Swoole\Coroutine::sleep((float)($millisecond / 1000));
        return;
    }
    if (extension_loaded('swow')) {
        msleep($millisecond);
        return;
    }
    usleep($millisecond * 1000);
}

/**
 * @return int
 */
function cpuNum(): int
{
    if (function_exists('swoole_cpu_num')) {
        return swoole_cpu_num();
    }
    if (DIRECTORY_SEPARATOR === '\\') {
        return 1;
    }
    $count = 4;
    if (is_callable('shell_exec')) {
        if (strtolower(PHP_OS) === 'darwin') {
            $count = (int)shell_exec('sysctl -n machdep.cpu.core_count');
        } else {
            $count = (int)shell_exec('nproc');
        }
    }
    return $count > 0 ? $count : 4;
}