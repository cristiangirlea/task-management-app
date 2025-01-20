<?php

namespace Tests\Unit\Commands;

class MakeResponseHandlerTest extends MakeCommandTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Dynamically configure all necessary options for the test
        $this->setPath('Http/Responses');
        $this->setCommandName('make:response-handler');
        $this->setNamespace('App\\Http\\Responses');
    }

    public function test_it_creates_a_response_handler(): void
    {
        $className = 'TestResponseHandler';
        $this->runCommand($className);
        $this->assertFileHasNamespace($className);
    }

    public function test_it_does_not_create_duplicate_response_handlers(): void
    {
        $this->assertDuplicateCommand('DuplicateResponseHandler');
    }
}
