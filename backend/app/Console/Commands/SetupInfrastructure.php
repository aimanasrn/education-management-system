<?php

namespace App\Console\Commands;

use App\InfrastructureEnvironment;
use Illuminate\Console\Command;

class SetupInfrastructure extends Command
{
    protected $signature = 'ems:setup-infrastructure';

    protected $description = 'Generate missing local Docker infrastructure secrets without rotating existing values';

    public function handle(): int
    {
        try {
            InfrastructureEnvironment::prepare(dirname(base_path()));
        } catch (\RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $this->info('Local infrastructure secrets are ready. Existing values were preserved. Start Docker Compose to use them.');

        return self::SUCCESS;
    }
}
