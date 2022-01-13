<?php

namespace staabm\ZfSelectStrip\Tests\PHPStan;

use PHPStan\Testing\TypeInferenceTestCase;

class ZfInferenceTest extends TypeInferenceTestCase
{
    public function dataFileAsserts(): iterable
    {
        // make sure class constants can be resolved
        require_once __DIR__.'/data/zf-select.php';
        yield from $this->gatherAssertTypes(__DIR__.'/data/zf-select.php');
    }

    /**
     * @dataProvider dataFileAsserts
     *
     * @param mixed ...$args
     */
    public function testFileAsserts(
        string $assertType,
        string $file,
               ...$args,
    ): void {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../config/extensions.neon',
        ];
    }
}
