<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Console\Commands;

use Cjmellor\Approval\Models\Approval;
use Illuminate\Console\Command;

class ProcessExpiredApprovalsCommand extends Command
{
    protected $signature = 'approval:process-expired';

    protected $description = 'Process all expired approvals based on their configured actions';

    public function handle(): int
    {
        $count = Approval::processExpired();

        $this->info(string: "{$count} expired approval(s) processed successfully.");

        return Command::SUCCESS;
    }
}
