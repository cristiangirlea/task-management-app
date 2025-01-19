<?php

namespace Tests\Unit\Commands;

class MakeRepositoryTest extends MakeCommandTest
{
    protected function getPath(): string
    {
        return 'app/Repositories';
    }

    protected function getCommandName(): string
    {
        return 'make:repository';
    }

    protected function getNamespace(): string
    {
        return 'App\\Repositories';
    }

    public function test_it_creates_a_repository(): void
    {
        $this->runCommand('TestRepository');
    }

    public function test_it_does_not_create_duplicate_repositories(): void
    {
        $this->assertDuplicateCommand('DuplicateRepository');
    }
}
