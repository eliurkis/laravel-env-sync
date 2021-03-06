<?php

namespace Eli\LaravelEnvSync\Console;

use Eli\LaravelEnvSync\SyncService;
use Eli\LaravelEnvSync\Writer\WriterInterface;

class SyncCommand extends BaseCommand
{
    private const YES = 'y';
    private const NO = 'n';
    private const CHANGE = 'c';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'env:sync {--reverse} {--src=} {--dest=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise the .env & .env.example files.';

    private $sync;
    private $writer;

    /**
     * Create a new command instance.
     *
     * @param SyncService $sync
     * @param WriterInterface $writer
     */
    public function __construct(SyncService $sync, WriterInterface $writer)
    {
        parent::__construct();
        $this->sync = $sync;
        $this->writer = $writer;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        [$src, $dest] = $this->getSrcAndDest();

        if ($this->option('reverse')) {
            [$src, $dest] = [$dest, $src];
        }

        $forceCopy = $this->option('no-interaction');
        if ($forceCopy) {
            $this->warn('--no-interaction flag detected - will copy all new keys');
        }

        $diffs = $this->sync->getDiff($src, $dest);

        foreach ($diffs as $key => $diff) {
            $action = self::YES;
            if (!$forceCopy) {
                $question = sprintf("'%s' is not present into your %s file. Its default value is '%s'. Would you like to add it?", $key, basename($dest), $diff);
                $action = $this->choice($question, [
                    self::YES => 'Copy the default value',
                    self::CHANGE => 'Change the default value',
                    self::NO => 'Skip'
                ], self::YES);
            }

            if ($action === self::NO) {
                continue;
            }

            if ($action === self::CHANGE) {
                $diff = $this
                    ->output
                    ->ask(sprintf("Please choose a value for '%s'", $key, $diff), null, function ($value) {
                        return $value;
                    });
            }

            $this->writer->append($dest, $key, $diff);
        }

        $this->info($dest . ' is now synced with ' . $src . '.');
    }
}
