<?php

declare(strict_types=1);

namespace staabm\ZfSelectStrip\Reflection;


use PhpParser\Builder\Method;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;
use ReflectionClass;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;

final class ZfSelectReflection {
    /**
     * Do not change, part of internal PHPStan naming.
     *
     * @var string
     */
    private const PREVIOUS = 'previous';

    /**
     * Convention key name in php-parser and PHPStan for parent node.
     *
     * @var string
     */
    private const PARENT = 'parent';

    private NodeFinder $nodeFinder;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    public function fakeTableSelect(Assign $selectCreate, Scope $scope): ?Zend_Db_Table_Select {

        $methodCall = $selectCreate->expr;
        if (!$methodCall instanceof MethodCall) {
            throw new ShouldNotHappenException();
        }

        $tableClass = $methodCall->var;
        $objectType = $scope->getType($tableClass);

        if (!$objectType instanceof ObjectType) {
            return null;
        }

        $tableClassReflection = $objectType->getClassReflection();
        if ($tableClassReflection ===null ){
            return null;
        }
        $fakeTableAbstract = $this->fakeTableAbstract($tableClassReflection);

        $select = new Zend_Db_Table_Select($fakeTableAbstract);
        return $select;
    }

    public function fakeTableAbstract(ClassReflection $tableClassReflection) : Zend_Db_Table_Abstract {
        $tableName = $tableClassReflection->getNativeProperty('_name')->getNativeReflection()->getDefaultValue();
        $pkName = $tableClassReflection->getNativeProperty('_primary')->getNativeReflection()->getDefaultValue();

        $fakeTableAbstract = new class extends Zend_Db_Table_Abstract {
        };
        $reflectionClass = new ReflectionClass($fakeTableAbstract);

        $tableProperty = $reflectionClass->getProperty('_name');
        $tableProperty->setAccessible(true);
        $tableProperty->setValue($fakeTableAbstract, $tableName);

        $tableProperty = $reflectionClass->getProperty('_primary');
        $tableProperty->setAccessible(true);
        $tableProperty->setValue($fakeTableAbstract, $pkName);

        return $fakeTableAbstract;
    }

    /**
     * @param MethodCall|Variable $expr
     */
    public function findSelectCreateAssign(Expr $expr): ?Assign
    {
        // todo: use astral simpleNameResolver
        $nameResolver = function (Node $node) {
            if (\is_string($node->name)) {
                return $node->name;
            }
            if ($node->name instanceof Node\Identifier) {
                return $node->name->toString();
            }
        };

        $current = $expr;
        while (null !== $current) {
            /** @var Assign|null $assign */
            $assign = $this->findFirstPreviousOfNode($current, function ($node) {
                return $node instanceof Assign;
            });

            if (null !== $assign) {
                if ($expr instanceof MethodCall && $nameResolver($assign->var) === $nameResolver($expr->var)) {
                    return $assign;
                }
                if ($expr instanceof Variable && $nameResolver($assign->var) === $nameResolver($expr)) {
                    return $assign;
                }
            }

            $current = $assign;
        }

        return null;
    }

    /**
     * @param callable(Node $node):bool $filter
     */
    private function findFirstPreviousOfNode(Node $node, callable $filter): ?Node
    {
        // move to previous expression
        $previousStatement = $node->getAttribute(self::PREVIOUS);
        if (null !== $previousStatement) {
            if (!$previousStatement instanceof Node) {
                throw new ShouldNotHappenException();
            }
            $foundNode = $this->findFirst([$previousStatement], $filter);
            // we found what we need
            if (null !== $foundNode) {
                return $foundNode;
            }

            return $this->findFirstPreviousOfNode($previousStatement, $filter);
        }

        $parent = $node->getAttribute(self::PARENT);
        if ($parent instanceof FunctionLike) {
            return null;
        }

        if ($parent instanceof Node) {
            return $this->findFirstPreviousOfNode($parent, $filter);
        }

        return null;
    }

    /**
     * @param Node|Node[] $nodes
     * @param callable(Node $node):bool $filter
     */
    private function findFirst(Node|array $nodes, callable $filter): ?Node
    {
        return $this->nodeFinder->findFirst($nodes, $filter);
    }
}
