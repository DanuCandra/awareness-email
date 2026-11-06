<?php

namespace Tests\Feature;

use App\Console\Commands\WorkSimulations;
use Closure;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WorkSimulationsTest extends TestCase
{
    public function test_rejects_unsupported_runtime_without_starting_children(): void
    {
        $command = new WorkSimulationsHarness;
        $command->executable = null;
        $command->setLaravel($this->app);
        $output = new BufferedOutput;

        $result = $command->run(new ArrayInput([]), $output);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('composer run dev', $output->fetch());
        $this->assertSame([], $command->commands);
    }

    /** @return array<string, array{bool}> */
    public static function stoppedChildren(): array
    {
        return ['worker exits' => [false], 'scheduler exits' => [true]];
    }

    #[DataProvider('stoppedChildren')]
    public function test_child_exit_stops_both_groups_and_returns_failure(bool $workerSurvives): void
    {
        $worker = $this->process(101, $workerSurvives);
        $scheduler = $this->process(102, false);
        $command = new WorkSimulationsHarness;
        $command->processes = [$worker, $scheduler];
        $command->setLaravel($this->app);
        $output = new BufferedOutput;

        $result = $command->run(new ArrayInput([]), $output);

        $this->assertSame(1, $result);
        $this->assertSame([[101, 15], [102, 15]], $command->signalsSent);
        $this->assertStringContainsString($workerSurvives ? 'scheduler berhenti' : 'simulations berhenti', $output->fetch());
        $this->assertSame([
            ['setsid', PHP_BINARY, base_path('artisan'), 'queue:work', 'database', '--queue=simulations', '--sleep=1', '--timeout=60', '--no-interaction', '--no-ansi'],
            ['setsid', PHP_BINARY, base_path('artisan'), 'schedule:work', '--no-interaction', '--no-ansi'],
        ], $command->commands);
    }

    public function test_shutdown_signal_stops_both_groups_and_returns_success(): void
    {
        $command = new WorkSimulationsHarness;
        $command->processes = [$this->process(101, false), $this->process(102, false)];
        $command->stopAfterStart = true;
        $command->setLaravel($this->app);

        $result = $command->run(new ArrayInput([]), new BufferedOutput);

        $this->assertSame(0, $result);
        $this->assertSame([[101, 15], [102, 15]], $command->signalsSent);
    }

    public function test_scheduler_start_failure_cleans_up_started_worker(): void
    {
        $scheduler = Mockery::mock(Process::class);
        $scheduler->shouldReceive('start')->once()->andThrow(new RuntimeException('start failed'));
        $command = new WorkSimulationsHarness;
        $command->processes = [$this->process(101, false), $scheduler];
        $command->setLaravel($this->app);

        try {
            $command->run(new ArrayInput([]), new BufferedOutput);
            $this->fail('Expected startup failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('start failed', $exception->getMessage());
        }

        $this->assertSame([[101, 15]], $command->signalsSent);
    }

    public function test_unresponsive_groups_are_killed_after_shutdown_deadline(): void
    {
        $command = new WorkSimulationsHarness;
        $command->processes = [$this->process(101, false), $this->process(102, false)];
        $command->groupsRemain = true;
        $command->setLaravel($this->app);

        $result = $command->run(new ArrayInput([]), new BufferedOutput);

        $this->assertSame(1, $result);
        $this->assertSame([[101, 15], [102, 15], [101, 9], [102, 9]], $command->signalsSent);
    }

    public function test_linux_child_exit_terminates_real_sibling_process_group(): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || ! extension_loaded('pcntl') || ! extension_loaded('posix')) {
            $this->markTestSkipped('Process group integration requires Linux with pcntl and posix.');
        }

        $command = new class extends WorkSimulations
        {
            /** @var list<int> */
            public array $childPids = [];

            protected function makeProcess(array $command): Process
            {
                $script = in_array('queue:work', $command, true) ? 'usleep(300000); exit(7);' : 'sleep(30);';

                return new Process([$command[0], PHP_BINARY, '-r', $script], timeout: null);
            }

            protected function awaitSession(Process $process, int $pid): void
            {
                $this->childPids[] = $pid;
                parent::awaitSession($process, $pid);
            }
        };
        $command->setLaravel($this->app);

        $result = $command->run(new ArrayInput([]), new BufferedOutput);

        $this->assertSame(1, $result);
        $this->assertCount(2, $command->childPids);
        foreach ($command->childPids as $pid) {
            $this->assertFalse(posix_kill($pid, 0));
            $this->assertFalse(posix_kill(-$pid, 0));
        }
    }

    private function process(int $pid, bool $initiallyRunning): Process
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('start')->once();
        $process->shouldReceive('getPid')->once()->andReturn($pid);
        $process->shouldReceive('isRunning')->andReturn($initiallyRunning, false);
        $process->shouldReceive('getExitCode')->andReturn(0);
        $process->shouldReceive('clearOutput', 'clearErrorOutput')->andReturnSelf();
        $process->shouldReceive('stop')->with(0)->once();

        return $process;
    }
}

class WorkSimulationsHarness extends WorkSimulations
{
    public ?string $executable = 'setsid';

    /** @var list<Process> */
    public array $processes = [];

    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<array{int, int}> */
    public array $signalsSent = [];

    public bool $stopAfterStart = false;

    public bool $groupsRemain = false;

    private ?Closure $onStop = null;

    public function trap($signals, $callback): void
    {
        $this->onStop = Closure::fromCallable($callback);
    }

    protected function sessionExecutable(): ?string
    {
        return $this->executable;
    }

    protected function makeProcess(array $command): Process
    {
        $this->commands[] = $command;

        return array_shift($this->processes);
    }

    protected function awaitSession(Process $process, int $pid): void
    {
        if ($this->stopAfterStart && count($this->commands) === 2) {
            ($this->onStop)();
        }
    }

    protected function signalGroup(int $pid, int $signal): void
    {
        $this->signalsSent[] = [$pid, $signal];
    }

    protected function groupExists(int $pid): bool
    {
        return $this->groupsRemain;
    }

    protected function shutdownTimeout(): float
    {
        return 0;
    }
}
