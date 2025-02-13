<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
declare(strict_types=1);

namespace Spiral\Prototype\NodeVisitors;

use PhpParser\Builder\Param;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Spiral\Prototype\Annotation;
use Spiral\Prototype\ClassNode;
use Spiral\Prototype\Dependency;
use Spiral\Prototype\Utils;

/**
 * Injects new constructor dependencies and modifies comment.
 */
final class UpdateConstructor extends NodeVisitorAbstract
{
    /** @var ClassNode */
    private $definition;

    /**
     * @param ClassNode $definition
     */
    public function __construct(ClassNode $definition)
    {
        $this->definition = $definition;
    }

    /**
     * @param Node $node
     * @return int|null|Node|Node[]
     */
    public function leaveNode(Node $node)
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return null;
        }

        $constructor = $this->getConstructorAttribute($node);
        if (!$this->definition->hasConstructor && $this->definition->constructorParams) {
            $this->addParentConstructorCall($constructor);
        }

        $this->addDependencies($constructor);

        $constructor->setDocComment(
            $this->addComments($constructor->getDocComment())
        );

        return $node;
    }

    /**
     * Add dependencies to constructor method.
     *
     * @param Node\Stmt\ClassMethod $constructor
     */
    private function addDependencies(Node\Stmt\ClassMethod $constructor): void
    {
        foreach ($this->definition->dependencies as $name => $dependency) {
            $constructor->params[] = (new Param($dependency->var))->setType(
                new Node\Name($this->getPropertyType($dependency))
            )->getNode();

            $prop = new Node\Expr\PropertyFetch(new Node\Expr\Variable("this"), $dependency->property);

            array_unshift(
                $constructor->stmts,
                new Node\Stmt\Expression(
                    new Node\Expr\Assign(
                        $prop,
                        new Node\Expr\Variable($dependency->var)
                    )
                )
            );
        }
    }

    /**
     * @param Node\Stmt\ClassMethod $constructor
     */
    private function addParentConstructorCall(Node\Stmt\ClassMethod $constructor): void
    {
        $parentConstructorDependencies = [];
        foreach ($this->definition->constructorParams as $param) {
            $parentConstructorDependencies[] = new Node\Arg(new Node\Expr\Variable($param->name));

            $cp = new Param($param->name);
            if (!empty($param->type)) {
                $type = $this->getParamType($param);
                if ($param->nullable) {
                    $type = "?$type";
                }

                $cp->setType(new Node\Name($type));
            }

            if ($param->hasDefault) {
                $cp->setDefault($param->default);
            }
            $constructor->params[] = $cp->getNode();
        }

        if ($parentConstructorDependencies) {
            array_unshift(
                $constructor->stmts,
                new Node\Stmt\Expression(
                    new Node\Expr\StaticCall(
                        new Node\Name('parent'),
                        '__construct',
                        $parentConstructorDependencies
                    )
                )
            );
        }
    }

    /**
     * @param Node\Stmt\Class_ $node
     * @return Node\Stmt\ClassMethod
     */
    private function getConstructorAttribute(Node\Stmt\Class_ $node): Node\Stmt\ClassMethod
    {
        return $node->getAttribute('constructor');
    }

    /**
     * Add PHPDoc comments into __construct.
     *
     * @param Doc|null $doc
     * @return Doc
     */
    private function addComments(Doc $doc = null): Doc
    {
        $an = new Annotation\Parser($doc ? $doc->getText() : "");

        $params = [];

        if (!$this->definition->hasConstructor) {
            foreach ($this->definition->constructorParams as $param) {
                if (!empty($param->type)) {
                    $type = $this->getParamType($param);
                    if ($param->nullable) {
                        $type = "$type|null";
                    }

                    $params[] = new Annotation\Line(
                        sprintf('%s $%s', $type, $param->name),
                        'param'
                    );
                } else {
                    $params[] = new Annotation\Line(
                        sprintf('$%s', $param->name),
                        'param'
                    );
                }
            }
        }

        foreach ($this->definition->dependencies as $name => $dependency) {
            $params[] = new Annotation\Line(
                sprintf('%s $%s', $this->getPropertyType($dependency), $dependency->var),
                'param'
            );
        }

        $placementID = 0;
        $previous = null;
        foreach ($an->lines as $index => $line) {
            // always next node
            $placementID = $index + 1;

            // inject before this parameters
            if ($line->is(['throws', 'return'])) {
                // insert before given node
                $placementID--;
                break;
            }

            $previous = $line;
        }

        if ($previous !== null && !$previous->isEmpty()) {
            $placementID++;
        }

        $an->lines = Utils::injectValues($an->lines, $placementID, $params);

        return new Doc($an->compile());
    }

    /**
     * @param Dependency $dependency
     * @return string
     */
    private function getPropertyType(Dependency $dependency): string
    {
        foreach ($this->definition->getStmts() as $stmt) {
            if ($stmt->name === $dependency->type->fullName) {
                if ($stmt->alias) {
                    return $stmt->alias;
                }
            }
        }

        return $dependency->type->getAliasOrShortName();
    }

    /**
     * @param ClassNode\ConstructorParam $param
     * @return string
     */
    private function getParamType(ClassNode\ConstructorParam $param): string
    {
        foreach ($this->definition->getStmts() as $stmt) {
            if ($stmt->name === $param->type->fullName) {
                if ($stmt->alias) {
                    return $stmt->alias;
                }
            }
        }

        if ($param->type->alias) {
            return $param->type->alias;
        }

        return $param->type->getSlashedShortName($param->isBuiltIn());
    }
}
