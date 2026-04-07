<?php

namespace JibayMcs\Tabbed\Commands;

use Illuminate\Console\Command;

class TabbedCommand extends Command
{
    public $signature = 'tabbed';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
