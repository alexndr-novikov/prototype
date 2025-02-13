<?php

namespace Spiral\Prototype\Tests\ClassNode;

use PHPUnit\Framework\TestCase;
use Spiral\Core\Container;
use Spiral\Prototype\Exception\ClassNotDeclaredException;
use Spiral\Prototype\NodeExtractor;

class ExtractorTest extends TestCase
{
    /**
     * @throws ClassNotDeclaredException
     */
    public function testNoClass()
    {
        $this->expectException(ClassNotDeclaredException::class);
        $this->getExtractor()->extract(dirname(__DIR__) . '/Fixtures/noClass.php', []);
    }

    private function getExtractor(): NodeExtractor
    {
        $container = new Container();

        return $container->get(NodeExtractor::class);
    }
}
