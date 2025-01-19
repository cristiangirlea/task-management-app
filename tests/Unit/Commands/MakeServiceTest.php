<?php

namespace Tests\Unit\Commands;

class MakeServiceTest extends MakeCommandTest
{
    protected function getPath(): string
    {
        return 'app/Services';
    }

    protected function getCommandName(): string
    {
        return 'make:service';
    }

    protected function getNamespace(): string
    {
        return 'App\\Services';
    }

    public function test_it_creates_a_service(): void
    {
        $this->runCommand('TestService');
    }

    public function test_it_does_not_create_duplicate_services(): void
    {
        $this->assertDuplicateCommand('DuplicateService');
    }
}
