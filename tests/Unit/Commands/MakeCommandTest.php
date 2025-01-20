<?php

namespace Tests\Unit\Commands;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

abstract class MakeCommandTest extends TestCase
{
    protected array $createdFiles = [];
    private ?string $customPath = null;
    private ?string $commandName = null;
    private ?string $namespace = null;

    /**
     * Set a custom path for the generated files.
     */
    public function setPath(string $path): void
    {
        $this->customPath = $path;
    }

    /**
     * Set the artisan command name.
     */
    public function setCommandName(string $name): void
    {
        $this->commandName = $name;
    }

    /**
     * Set the namespace for the generated class.
     */
    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * Get the path where the generated file should be created.
     */
    protected function getPath(): string
    {
        // Ensure the custom path (if set) does NOT include the "app/" prefix
        if (str_starts_with($this->customPath, 'app/')) {
            return ltrim($this->customPath, 'app/');
        }

        return $this->customPath ?? throw new \InvalidArgumentException('No path has been set.');
    }

    /**
     * Get the full file path for the generated file.
     */
    protected function getFilePath(string $className): string
    {
        return app_path(
            trim(str_replace('/', DIRECTORY_SEPARATOR, $this->getPath()), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR .
            "{$className}.php"
        );
    }

    /**
     * Get the artisan command name to test.
     */
    protected function getCommandName(): string
    {
        return $this->commandName ?? throw new \InvalidArgumentException('No artisan command has been set.');
    }

    /**
     * Get the namespace for the generated class.
     */
    protected function getNamespace(): string
    {
        return $this->namespace ?? throw new \InvalidArgumentException('No namespace has been set.');
    }

    /**
     * Run the command to generate the file.
     */
    protected function runCommand(string $className): void
    {
        $command = $this->getCommandName();
        $filePath = $this->getFilePath($className);

        $this->artisan("{$command} {$className}")->run();

        $this->assertFileExists($filePath, "The file {$filePath} was not created.");

        // Track files for cleanup
        $this->createdFiles[] = $filePath;
    }

    /**
     * Assert that the command handles duplicate creation gracefully.
     */
    protected function assertDuplicateCommand(string $className): void
    {
        $command = $this->getCommandName();
        $filePath = $this->getFilePath($className);

        // Run the command for the first time
        $this->artisan("{$command} {$className}")->run();

        $this->assertFileExists($filePath, "The file {$filePath} was not created on the first attempt.");

        // Track for cleanup
        if (!in_array($filePath, $this->createdFiles)) {
            $this->createdFiles[] = $filePath;
        }

        // Run the command again to simulate duplicate creation
        $this->artisan("{$command} {$className}")->run();

        $this->assertFileExists($filePath, "The file {$filePath} should still exist after a duplicate creation attempt.");
    }

    /**
     * Assert that the command assigns the correct namespace.
     */
    protected function assertFileHasNamespace(string $className): void
    {
        $filePath = $this->getFilePath($className);
        $namespace = $this->getNamespace();

        $fileContent = file_get_contents($filePath);

        // Assert the namespace is correct
        $this->assertStringContainsString(
            "namespace {$namespace};",
            $fileContent,
            "Expected namespace '{$namespace}' not found in file {$filePath}."
        );
    }

    /**
     * Run after every test to clean up generated files.
     */
    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $filePath) {
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }

        // Clear the array to reset for the next test
        $this->createdFiles = [];

        parent::tearDown();
    }
}
