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
        $handlerName = "{$controllerName}";

        return <<<EOT
<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class {$handlerName} extends ApiResponseHandler
{
    /**
     * Handle the response for the index method.
     */
    public function indexResponse(\$data, string \$message = 'Data retrieved successfully'): JsonResponse
    {
        return \$this->successResponse(\$data, \$message);
    }

    /**
     * Handle the response for the store method.
     */
    public function storeResponse(\$data, string \$message = 'Resource created successfully', int \$status = 201): JsonResponse
    {
        return \$this->successResponse(\$data, \$message, \$status);
    }

    /**
     * Handle the response for the show method.
     */
    public function showResponse(\$data, string \$message = 'Resource retrieved successfully'): JsonResponse
    {
        return \$this->successResponse(\$data, \$message);
    }

    /**
     * Handle the response for the update method.
     */
    public function updateResponse(\$data, string \$message = 'Resource updated successfully'): JsonResponse
    {
        return \$this->successResponse(\$data, \$message);
    }

    /**
     * Handle the response for the destroy method.
     */
    public function destroyResponse(string \$message = 'Resource deleted successfully', int \$status = 204): JsonResponse
    {
        return \$this->successResponse(null, \$message, \$status);
    }
}
EOT;
    }
}
