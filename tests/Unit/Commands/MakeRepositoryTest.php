<?php

namespace Tests\Unit\Commands;

class MakeRepositoryTest extends MakeCommandTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Dynamically configure all necessary options for the test
        $this->setPath('Repositories');
        $this->setCommandName('make:repository');
        $this->setNamespace('App\\Repositories');
    }

    public function test_it_creates_a_repository(): void
    {
        $className = 'TestRepository';
        $this->runCommand($className);
        $this->assertFileHasNamespace($className);
    }

    public function test_it_does_not_create_duplicate_repositories(): void
    {
        $this->assertDuplicateCommand('DuplicateRepository');
    }
}
