<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Prototype\Tests;

use PHPUnit\Framework\TestCase;
use Spiral\Prototype\PropertyExtractor;

class ExtractorTest extends TestCase
{
    public function testExtract()
    {
        $e = new PropertyExtractor();

        $expected = ['test', 'test2', 'test3', 'testClass'];
        $prototypes = $e->getPrototypeProperties(file_get_contents(__DIR__ . '/Fixtures/TestClass.php'));
        sort($prototypes);
        $this->assertSame($expected, $prototypes);
    }

    public function testExtractNone()
    {
        $e = new PropertyExtractor();
        $this->assertSame(
            [],
            $e->getPrototypeProperties(file_get_contents(__DIR__ . '/Fixtures/HydratedClass.php'))
        );
    }
}
