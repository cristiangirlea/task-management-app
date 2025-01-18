<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeRepository extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name : The name of the repository}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new repository class';

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
        $name = $this->argument('name');
        $path = app_path("Repositories/{$name}.php");

        if ($this->files->exists($path)) {
            $this->error("Repository {$name} already exists!");
            return Command::FAILURE;
        }

        $this->files->ensureDirectoryExists(app_path('Repositories'));
        $this->files->put($path, $this->getStub($name));

        $this->info("Repository {$name} created successfully.");
        return Command::SUCCESS;
    }

    /**
     * Get the stub for the repository class.
     */
    protected function getStub(string $name): string
    {
        return "<?php\n\nnamespace App\\Repositories;\n\nclass {$name}\n{\n    // Add your repository methods here\n}\n";
    }
}
