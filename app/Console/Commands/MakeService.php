<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeService extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:service {name : The name of the service class}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new service class';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = ucfirst($this->argument('name')); // Normalize the class name
        $path = app_path("Services/{$name}.php");

        if ($this->files->exists($path)) {
            $this->error("Service {$name} already exists!");
            return Command::FAILURE;
        }

        $this->files->ensureDirectoryExists(app_path('Services'));
        $this->files->put($path, $this->getStub($name));

        if ($this->files->exists($path)) {
            $this->info("Service {$name} created successfully.");
            return Command::SUCCESS;
        } else {
            $this->error("Failed to create Service {$name}.");
            return Command::FAILURE;
        }
    }

    /**
     * Get the stub for the service class.
     */
    protected function getStub(string $name): string
    {
        return "<?php\n\nnamespace App\\Services;\n\nclass {$name}\n{\n    // Add your service methods here\n}\n";
    }
}
