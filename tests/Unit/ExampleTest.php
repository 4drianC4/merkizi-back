<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_example(): void
    {
        $value = 1 + 1;

        $this->assertSame(2, $value);
    }
}
