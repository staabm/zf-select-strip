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
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeDumper;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeUtils;
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

    /**
     * @internal of php-parser, do not change
     * @see https://github.com/nikic/PHP-Parser/pull/681/files
     * @var string
     */
    public const NEXT_NODE = 'next';

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

        foreach($this->findOnSelectMethodCalls($selectCreate) as $methodCall) {
            $methodName = $this->resolveName($methodCall->name);
            if($methodName === null) {
                throw new ShouldNotHappenException();
            }
            $args = $methodCall->getArgs();

            switch(strtolower($methodName)) {
                case 'from':
                {
                    if (count($args) < 1) {
                        return null;
                    }
                    $fromType = $scope->getType($args[0]->value);
                    if (!$fromType instanceof ConstantStringType) {
                        return null;
                    }

                    $cols = Zend_Db_Table_Select::SQL_WILDCARD;
                    if (count($args) >= 2) {
                        $colsType = $scope->getType($args[1]->value);
                        if (!$colsType instanceof ConstantArrayType) {
                            return null;
                        }
                        $cols = $this->constantArrayToScalarArray($colsType);
                    }

                    $select->from($fromType->getValue(), $cols);
                    break;
                }
                case 'join':
                case 'joinleft':
                {
                    if (count($args) < 3) {
                        return null;
                    }

                    $joinNameType = $scope->getType($args[0]->value);
                    $joinConditionsType = $scope->getType($args[1]->value);
                    $joinColsType = $scope->getType($args[2]->value);

                    if (!$joinNameType instanceof ConstantStringType) {
                        return null;
                    }
                    if (!$joinConditionsType instanceof ConstantStringType) {
                        return null;
                    }

                    if ($joinColsType instanceof ConstantArrayType) {
                        if (!$joinColsType->isEmpty()) {
                            throw new ShouldNotHappenException();
                        }
                        $joinCols = [];
                    } elseif ($joinColsType instanceof ConstantStringType) {
                        $joinCols = $joinColsType->getValue();
                    } else {
                        throw new ShouldNotHappenException();
                    }

                    if (strtolower($methodName) === 'join') {
                        $select->join($joinNameType->getValue(), $joinConditionsType->getValue(), $joinCols);
                    } else {
                        $select->joinLeft($joinNameType->getValue(), $joinConditionsType->getValue(), $joinCols);
                    }
                    break;
                }
                case 'where':
                {
                    if (count($args) < 2) {
                        return null;
                    }
                    $whereCondType = $scope->getType($args[0]->value);
                    $whereValueType = $scope->getType($args[1]->value);
                    if (!$whereCondType instanceof ConstantStringType) {
                        return null;
                    }
                    if (!$whereValueType instanceof ConstantIntegerType) {
                        return null;
                    }
                    $select->where($whereCondType->getValue(), $whereValueType->getValue());
                    break;
                }
                case 'group':
                case 'order':
                {
                    if (count($args) < 1) {
                        return null;
                    }
                    $specType = $scope->getType($args[0]->value);

                    if ($specType instanceof ConstantStringType) {
                        $spec = $specType->getValue();
                    } elseif ($specType instanceof ConstantArrayType) {
                        $spec = $this->constantArrayToScalarArray($specType);
                    } else {
                        return null;
                    }

                    if (strtolower($methodName) === 'group') {
                        $select->group($spec);
                    } else {
                        $select->order($spec);
                    }
                    break;
                }
                case 'setintegritycheck':
                {
                    if (count($args) < 1) {
                        return null;
                    }
                    $flagType = $scope->getType($args[0]->value);

                    if (!$flagType instanceof ConstantBooleanType) {
                        throw new ShouldNotHappenException();
                    }

                    $select->setIntegrityCheck($flagType->getValue());
                }
            }
        }

        return $select;
    }

    /**
     * @param ConstantArrayType $constantArrayType
     * @return scalar[]
     */
    private function constantArrayToScalarArray(ConstantArrayType $constantArrayType):array {
        $integerType = new IntegerType();
        if (!$integerType->isSuperTypeOf($constantArrayType->getKeyType())) {
            // no array shape support yet
            throw new ShouldNotHappenException();
        }

        $values = [];
        foreach($constantArrayType->getValueTypes() as $valueType) {
            if (!$valueType instanceof ConstantStringType) {
                // no array shape support yet
                throw new ShouldNotHappenException();
            }
            $values[] = $valueType->getValue();
        }
        return $values;
    }

    /**
     * @param MethodCall|Variable $expr
     */
    public function findSelectCreateAssign(Expr $expr): ?Assign
    {
        $current = $expr;
        while (null !== $current) {
            /** @var Assign|null $assign */
            $assign = $this->findFirstPreviousOfNode($current, function ($node) {
                return $node instanceof Assign;
            });

            if (null !== $assign && $this->resolveName($assign->var) !== null) {
                if ($expr instanceof MethodCall && $this->resolveName($assign->var) === $this->resolveName($expr->var)) {
                    return $assign;
                }
                if ($expr instanceof Variable && $this->resolveName($assign->var) === $this->resolveName($expr)) {
                    return $assign;
                }
            }

            $current = $assign;
        }

        return null;
    }

    private function fakeTableAbstract(ClassReflection $tableClassReflection) : Zend_Db_Table_Abstract {
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
     * @return MethodCall[]
     */
    public function findOnSelectMethodCalls(Assign $selectCreate): array {
        $methodCalls = [];

        $current = $selectCreate;
        do {
            $methodCall = $this->findFirstNext($current, function (Node $node) use ($selectCreate):bool {
                if ($node instanceof MethodCall && $this->resolveName($node->var) === $this->resolveName($selectCreate->var)) {
                    return true;
                }
                return false;
            });
            if (!$methodCall instanceof MethodCall) {
                throw new ShouldNotHappenException();
            }

            if ($methodCall !== null) {
                $methodCalls[] = $methodCall;
                $current = $methodCall;
            }
        } while ($methodCall !== null);

        return $methodCalls;
    }

    /**
     * @param Node $node
     */
    private function resolveName(Node $node):?string {
        if (!property_exists($node, 'name')) {
            throw new ShouldNotHappenException();
        }

        if (\is_string($node->name)) {
            return $node->name;
        }
        if ($node->name instanceof Node\Identifier) {
            return $node->name->toString();
        }

        return null;
    }

    private function findFirstNext(Node $node, callable $filter): ?Node
    {
        $next = $node->getAttribute(self::NEXT_NODE);
        if ($next instanceof Node) {
            if ($next instanceof Return_ && $next->expr === null) {
                return null;
            }

            $found = $this->findFirst($next, $filter);
            if ($found instanceof Node) {
                return $found;
            }

            return $this->findFirstNext($next, $filter);
        }

        $parent = $node->getAttribute(self::PARENT);
        if ($parent instanceof Return_ || $parent instanceof FunctionLike) {
            return null;
        }

        if ($parent instanceof Node) {
            return $this->findFirstNext($parent, $filter);
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
