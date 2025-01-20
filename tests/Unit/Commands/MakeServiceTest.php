<?php

namespace Tests\Unit\Commands;

class MakeServiceTest extends MakeCommandTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Dynamically configure all necessary options for the test
        $this->setPath('Services');
        $this->setCommandName('make:service');
        $this->setNamespace('App\\Services');
    }

    public function test_it_creates_a_service(): void
    {
        $className = 'TestService';
        $this->runCommand($className);
        $this->assertFileHasNamespace($className);
    }

    public function test_it_does_not_create_duplicate_services(): void
    {
        $this->assertDuplicateCommand('DuplicateService');
    }
}
