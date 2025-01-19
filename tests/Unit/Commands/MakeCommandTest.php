<?php

namespace Tests\Unit\Commands;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

abstract class MakeCommandTest extends TestCase
{
    protected array $createdFiles = [];

    /**
     * Get the path where the generated file should be created.
     */
    abstract protected function getPath(): string;

    /**
     * Get the artisan command to test.
     */
    abstract protected function getCommandName(): string;

    /**
     * Run after each test to clean up generated files.
     */
    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $filePath) {
            if (File::exists($filePath)) {
                File::delete($filePath);
                echo "Deleted: $filePath\n";
            }
        }

        // Clear the array to reset for the next test
        $this->createdFiles = [];

        parent::tearDown();
    }





    /**
     * Run the command to generate the file.
     */
    protected function runCommand(string $className): void
    {
        $command = $this->getCommandName();
        $filePath = base_path($this->getPath() . "/$className.php");

        // Run the command and check file creation
        $result = $this->artisan("$command $className")->run();

        // Add created file to cleanup list
        $this->createdFiles[] = $filePath;

        // Assert the file exists
        $this->assertFileExists($filePath, "The file {$filePath} was not created.");

        // Validate namespace and class declaration
        $content = File::get($filePath);
        $this->assertStringContainsString("namespace {$this->getNamespace()};", $content);
        $this->assertStringContainsString("class $className", $content);
    }


    /**
     * Assert the command handles duplicate creation gracefully.
     */
    protected function assertDuplicateCommand(string $className): void
    {
        $command = $this->getCommandName();
        $filePath = base_path($this->getPath() . "/$className.php");

        // First creation
        $this->artisan("$command $className")->run();

        // Ensure the file is created
        $this->assertFileExists($filePath, "The file {$filePath} was not created on the first attempt.");

        // Track the file for cleanup
        if (!in_array($filePath, $this->createdFiles)) {
            $this->createdFiles[] = $filePath;
        }

        // Attempt duplicate creation
        $this->artisan("$command $className")->run();

        // Ensure the file still exists after duplicate creation attempt
        $this->assertFileExists($filePath, "The file {$filePath} should still exist after a duplicate creation attempt.");

        // Add the duplicate attempt explicitly to track for cleanup
        if (!in_array($filePath, $this->createdFiles)) {
            $this->createdFiles[] = $filePath;
        }
    }





    /**
     * Get the namespace for the generated class.
     */
    abstract protected function getNamespace(): string;
}
