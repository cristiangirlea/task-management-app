<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeResponseHandler extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:response-handler {name : The name of the controller for which to create the response handler}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new response handler for a controller';

    /**
     * Filesystem instance.
     */
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
        $name = ucfirst($this->argument('name'));
        $path = app_path("Http/Responses/{$name}.php");

        if ($this->files->exists($path)) {
            $this->error("Response handler {$name} already exists!");
            return Command::FAILURE;
        }

        $this->files->ensureDirectoryExists(app_path('Http/Responses'));
        $this->files->put($path, $this->getStub($name));

        if ($this->files->exists($path)) {
            $this->info("Response handler {$name} created successfully.");
            return Command::SUCCESS;
        } else {
            $this->error("Failed to create Response Handler {$name}.");
            return Command::FAILURE;
        }
    }

    /**
     * Generate the content for the response handler.
     */
    protected function getStub(string $controllerName): string
    {
        $handlerName = "{$controllerName}ResponseHandler";
        $resourceName = str_replace('ResponseHandler', '', $handlerName);

        return <<<EOT
<?php

namespace App\Http\Responses;

use App\Handlers\ResponseHandler;

final class {$handlerName} extends ResponseHandler
{
    /**
     * Define the name of the resource.
     */
    protected function getResourceName(): string
    {
        return '{$resourceName}';
    }
}
EOT;
    }
}
