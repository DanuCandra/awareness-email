<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class WorkSimulations extends Command
{
    protected $signature = 'simulations:work';

    protected $description = 'Jalankan worker Simulasi dan scheduler bersama untuk Supervisor Linux';

    private bool $stopping = false;

    /** @var array<string, array{process: Process, pid: int}> */
    private array $children = [];

    public function handle(): int
    {
        $sessionExecutable = $this->sessionExecutable();

        if ($sessionExecutable === null) {
            $this->error('Command ini memerlukan Linux, ekstensi PHP CLI pcntl dan posix, serta executable setsid. Untuk lokal gunakan composer run dev.');

            return self::FAILURE;
        }

        $this->stopping = false;
        $this->children = [];
        $this->trap([15, 2, 3], function (): void {
            $this->stopping = true;
        });

        try {
            foreach ([
                'simulations' => ['queue:work', 'database', '--queue=simulations', '--sleep=1', '--timeout=60'],
                'scheduler' => ['schedule:work'],
            ] as $name => $arguments) {
                if ($this->stopping) {
                    break;
                }

                $process = $this->makeProcess([$sessionExecutable, PHP_BINARY, base_path('artisan'), ...$arguments, '--no-interaction', '--no-ansi']);
                $process->start(function (string $type, string $output) use ($name): void {
                    $this->output->write('['.$name.($type === Process::ERR ? ':stderr' : '').'] '.$output, false, OutputInterface::OUTPUT_RAW);
                });
                $pid = $process->getPid();

                if ($pid === null) {
                    throw new RuntimeException('Tidak dapat memulai proses '.$name.'.');
                }

                $this->children[$name] = ['process' => $process, 'pid' => $pid];
                $this->awaitSession($process, $pid);
            }

            $this->info('Worker Simulasi dan scheduler dimulai. Batas penghentian bersama: 70 detik.');

            while (! $this->stopping) {
                foreach ($this->children as $name => $child) {
                    if (! $child['process']->isRunning()) {
                        $this->error($name.' berhenti (exit '.($child['process']->getExitCode() ?? 'unknown').'). Menghentikan seluruh proses.');

                        return self::FAILURE;
                    }

                    $child['process']->clearOutput();
                    $child['process']->clearErrorOutput();
                }

                usleep(100_000);
            }

            return self::SUCCESS;
        } finally {
            $this->stopChildren();
            $this->untrap();
        }
    }

    protected function sessionExecutable(): ?string
    {
        if (PHP_OS_FAMILY !== 'Linux' || ! extension_loaded('pcntl') || ! extension_loaded('posix')) {
            return null;
        }

        return (new ExecutableFinder)->find('setsid');
    }

    /** @param list<string> $command */
    protected function makeProcess(array $command): Process
    {
        return new Process($command, base_path(), timeout: null);
    }

    protected function awaitSession(Process $process, int $pid): void
    {
        $deadline = hrtime(true) + 1_000_000_000;

        while ($process->isRunning() && posix_getpgid($pid) !== $pid) {
            if (hrtime(true) >= $deadline) {
                throw new RuntimeException('Tidak dapat membuat process group terpisah.');
            }

            usleep(10_000);
        }
    }

    protected function signalGroup(int $pid, int $signal): void
    {
        // The scheduler may spawn schedule:run; signal the entire isolated group.
        posix_kill(-$pid, $signal);
    }

    protected function groupExists(int $pid): bool
    {
        return posix_kill(-$pid, 0);
    }

    private function stopChildren(): void
    {
        foreach ($this->children as $child) {
            $this->signalGroup($child['pid'], 15);

            if ($child['process']->isRunning() && ! $this->groupExists($child['pid'])) {
                $child['process']->stop(0);
            }
        }

        $deadline = hrtime(true) + (int) ($this->shutdownTimeout() * 1_000_000_000);

        do {
            $running = false;

            foreach ($this->children as $child) {
                $child['process']->isRunning();
                $child['process']->clearOutput();
                $child['process']->clearErrorOutput();
                $running = $this->groupExists($child['pid']) || $running;
            }

            if ($running) {
                usleep(100_000);
            }
        } while ($running && hrtime(true) < $deadline);

        foreach ($this->children as $child) {
            if ($this->groupExists($child['pid'])) {
                $this->signalGroup($child['pid'], 9);
            }

            $child['process']->stop(0);
        }

        $this->children = [];
    }

    protected function shutdownTimeout(): float
    {
        return 70;
    }
}
