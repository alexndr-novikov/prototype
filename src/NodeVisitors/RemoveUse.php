<?php
declare(strict_types=1);
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Prototype\NodeVisitors;


use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Spiral\Prototype\Traits\PrototypeTrait;

/**
 * Remove PrototypeTrait use.
 */
class RemoveUse extends NodeVisitorAbstract
{
    /**
     * @param Node $node
     * @return int|null|Node|Node[]
     */
    public function leaveNode(Node $node)
    {
        if (!$node instanceof Node\Stmt\Use_) {
            return null;
        }

        foreach ($node->uses as $index => $use) {
            if ($use->name->toString() == PrototypeTrait::class) {
                unset($node->uses[$index]);
            }
        }

        if (empty($node->uses)) {
            return NodeTraverser::REMOVE_NODE;
        }

        return $node;
    }
}