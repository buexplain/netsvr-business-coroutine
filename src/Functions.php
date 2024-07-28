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

use RuntimeException;
use Swoole\Coroutine;

/**
 * 毫秒级休眠
 * @param int $millisecond
 * @return void
 */
function milliSleep(int $millisecond): void
{
    if (extension_loaded('swoole')) {
        Coroutine::sleep((float)($millisecond / 1000));
        return;
    }
    if (extension_loaded('swow')) {
        msleep($millisecond);
        return;
    }
    usleep($millisecond * 1000);
}

/**
 * 获取cpu核心数
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

/**
 * 将网关的worker服务器监听的地址转为16进制字符串
 * @param string $workerAddr
 * @return string
 */
function workerAddrConvertToHex(string $workerAddr) :string
{
    //将网关地址转为16进制字符串
    $addr = explode(':', $workerAddr, 2);
    //如果不是ipv4地址，则转换为ipv4地址
    if (!preg_match('/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $addr[0])) {
        $ipv4 = gethostbyname($addr[0]);
        if ($ipv4 === $addr[0]) {
            throw new RuntimeException('gethostbyname failed: ' . $addr[0]);
        }
        $addr[0] = $ipv4;
    }
    return bin2hex(pack('Nn', ip2long($addr[0]), $addr[1]));
}

/**
 * 将uniqId转为网关的worker服务器监听的地址的16进制字符串
 * @param string $uniqId 网关分配给每个连接的uniqId
 * @return string
 */
function uniqIdConvertToWorkerAddrAsHex(string $uniqId): string
{
    return substr($uniqId, 0, 12);
}