<?php

namespace Spiral\Prototype\ClassDefinition;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\ParserFactory;
use Spiral\Prototype\Annotation\Parser;
use Spiral\Prototype\ClassDefinition;
use Spiral\Prototype\ClassDefinition\ConflictResolver;
use Spiral\Prototype\Dependency;
use Spiral\Prototype\NodeVisitors\ClassDefinition\LocateStatements;
use Spiral\Prototype\NodeVisitors\ClassDefinition\DeclareClass;
use Spiral\Prototype\NodeVisitors\ClassDefinition\LocateVariables;

class Extractor
{
    /** @var Parser */
    private $parser;

    /** @var ConflictResolver\Names */
    private $namesResolver;

    /** @var ConflictResolver\Namespaces */
    private $namespacesResolver;

    public function __construct(ConflictResolver\Names $namesResolver, ConflictResolver\Namespaces $namespacesResolver, Parser $parser = null)
    {
        $this->namesResolver = $namesResolver;
        $this->namespacesResolver = $namespacesResolver;
        $this->parser = $parser ?? (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
    }

    /**
     * @param string       $code
     * @param Dependency[] $dependencies
     *
     * @return ClassDefinition
     */
    public function extract(string $code, array $dependencies): ClassDefinition
    {
        $definition = $this->makeDefinition($code);
        $definition->dependencies = $dependencies;

        $stmts = new LocateStatements();
        $vars = new LocateVariables();
        $this->traverse($code, $stmts, $vars);

        $this->fillStmts($definition, $stmts->getImports(), $stmts->getInstantiations());
        $this->fillConstructorParams($definition);
        $this->fillConstructorVars($vars->getVars(), $definition);
        $this->resolveConflicts($definition);

        return $definition;
    }

    private function makeDefinition(string $code): ClassDefinition
    {
        $declarator = new DeclareClass();
        $this->traverse($code, $declarator);

        if (empty($declarator->getClass())) {
            throw new \RuntimeException('Class not declared');
        }

        if ($declarator->getNamespace()) {
            return ClassDefinition::createWithNamespace($declarator->getClass(), $declarator->getNamespace());
        }

        return ClassDefinition::create($declarator->getClass());
    }

    private function traverse(string $code, NodeVisitor ...$visitors)
    {
        $tr = new NodeTraverser();

        foreach ($visitors as $visitor) {
            $tr->addVisitor($visitor);
        }

        $tr->traverse($this->parser->parse($code));
    }

    private function fillStmts(ClassDefinition $definition, array $imports, array $instantiations)
    {
        foreach ($imports as $import) {
            $definition->addImportUsage($import['name'], $import['alias']);
        }

        foreach ($instantiations as $instantiation) {
            $definition->addInstantiation($instantiation);
        }
    }

    private function fillConstructorParams(ClassDefinition $definition)
    {
        $reflection = new \ReflectionClass("{$definition->namespace}\\{$definition->class}");

        $constructor = $reflection->getConstructor();
        if (!empty($constructor)) {
            $definition->hasConstructor = $constructor->getDeclaringClass()->getName() === $reflection->getName();

            foreach ($reflection->getConstructor()->getParameters() as $parameter) {
                $definition->addParam($parameter);
            }
        }
    }

    /**
     * Collect all variable definitions from constructor method body.
     * Vars which are however also inserted via method are ignored (and still used as constructor params).
     *
     * @param array           $vars
     * @param ClassDefinition $definition
     */
    private function fillConstructorVars(array $vars, ClassDefinition $definition)
    {
        foreach ($vars as $k => $var) {
            if (isset($definition->constructorParams[$var])) {
                unset($vars[$k]);
            }
        }

        $definition->constructorVars = $vars;
    }

    private function resolveConflicts(ClassDefinition $definition)
    {
        $this->namesResolver->resolve($definition);
        $this->namespacesResolver->resolve($definition);
    }
}