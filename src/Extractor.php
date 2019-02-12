<?php
declare(strict_types=1);
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Prototype;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Spiral\Prototype\NodeVisitors\LocateProperties;

class Extractor
{
    /** @var Parser */
    private $parser;

    /**
     * @param Parser|null $parser
     */
    public function __construct(Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
    }

    /**
     * Get list of all virtual property names.
     *
     * @param string $code
     * @return array
     */
    public function getPrototypeNames(string $code): array
    {
        $v = new LocateProperties();

        $tr = new NodeTraverser();
        $tr->addVisitor($v);
        $tr->traverse($this->parser->parse($code));

        return $v->getDependencies();
    }
}