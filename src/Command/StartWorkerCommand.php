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

namespace NetsvrBusiness\Command;

use NetsvrBusiness\Common;
use NetsvrBusiness\Contract\MainSocketManagerInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use Swoole\Coroutine;
use Swoole\Process\Pool;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class StartWorkerCommand extends WorkerCommand
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();
        $this->ignoreValidationErrors();
        $this->setName('business:start')
            ->setDefinition([
                new InputOption('workers', 'w', InputOption::VALUE_REQUIRED, 'Specify the number of process.', swoole_cpu_num()),
            ])
            ->setDescription('Start business service.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);
        if ($this->isRun()) {
            $this->logger->info('The business service is running.');
            return 0;
        }
        if (!class_exists('\Google\Protobuf\Internal\Message')) {
            $this->logger->error('Class "Google\Protobuf\Internal\Message" not found, you can run command: composer require google/protobuf');
            return 1;
        }
        //开始启动多进程
        $workers = intval($input->getOption('workers'));
        $pool = new Pool($workers > 0 ? $workers : swoole_cpu_num());
        $pool->set(['enable_coroutine' => true]);
        $pool->on('WorkerStart', function (Pool $pool, $workerProcessId) {
            //记录主进程pid
            if ($workerProcessId === 0) {
                file_put_contents($this->pidFile, (string)$pool->master_pid);
            }
            //记住当前进程的编号
            Common::$workerProcessId = $workerProcessId;
            //启动mainSocket
            $manager = $this->container->get(MainSocketManagerInterface::class);
            if (!$manager || !$manager->start()) {
                $pool->shutdown();
                return;
            }
            //监听进程关闭信号
            $stop = new Coroutine\Channel();
            Coroutine::create(function () use ($pool, $stop) {
                while (true) {
                    if (Coroutine\System::waitSignal(SIGTERM) === true) {
                        break;
                    }
                    //Coroutine::sleep(3);break;
                }
                //关闭mainSocket
                $this->container->get(MainSocketManagerInterface::class)->close();
                //关闭taskSocket
                $this->container->get(TaskSocketPoolMangerInterface::class)->close();
                $stop->close();
            });
            $stop->pop();
        });
        $pool->start();
        //删除记录的主进程pid
        is_file($this->pidFile) && unlink($this->pidFile);
        return 0;
    }
}
