<?php

declare(strict_types=1);

namespace staabm\ZfSelectStrip\Reflection;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use ReflectionClass;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;

final class ZfSelectReflection
{
    // code partly copied from symplify/symplify .. use upstream package later
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
     *
     * @see https://github.com/nikic/PHP-Parser/pull/681/files
     *
     * @var string
     */
    private const NEXT_NODE = 'next';

    private NodeFinder $nodeFinder;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * @param list<Expr> $boundValues
     */
    public function cloneTableSelect(Assign $selectCreate, Scope $scope, array &$boundValues): ?Zend_Db_Table_Select
    {
        $methodCall = $selectCreate->expr;
        if (!$methodCall instanceof MethodCall) {
            throw new ShouldNotHappenException(sprintf('Expected method call, got %s.', \get_class($methodCall)));
        }

        $tableClass = $methodCall->var;
        $objectType = $scope->getType($tableClass);

        if (!$objectType instanceof ObjectType) {
            return null;
        }

        $tableClassReflection = $objectType->getClassReflection();
        if (null === $tableClassReflection || !$tableClassReflection->isSubclassOf(Zend_Db_Table_Abstract::class)) {
            return null;
        }

        if ($tableClassReflection->isAbstract()) {
            return null;
        }

        $tableAbstract = $this->createTableAbstract($tableClassReflection);

        $selectArgs = $methodCall->getArgs();
        if (\count($selectArgs) >= 1) {
            $withFromPartArgType = $scope->getType($selectArgs[0]->value);
            if (!$withFromPartArgType instanceof ConstantBooleanType) {
                throw new ShouldNotHappenException('Expected boolean constant');
            }
            $select = $tableAbstract->select($withFromPartArgType->getValue());
        } else {
            $select = $tableAbstract->select();
        }

        foreach ($this->findOnSelectMethodCalls($selectCreate) as $methodCall) {
            $methodName = $this->resolveName($methodCall->name);
            if (null === $methodName) {
                throw new ShouldNotHappenException('Method name could not be resolved');
            }
            $args = $methodCall->getArgs();

            switch (strtolower($methodName)) {
                case 'from':
                    if (\count($args) < 1) {
                        return null;
                    }
                    $fromType = $scope->getType($args[0]->value);
                    if (!$fromType instanceof ConstantStringType) {
                        return null;
                    }

                    $cols = Zend_Db_Table_Select::SQL_WILDCARD;
                    if (\count($args) >= 2) {
                        $colsType = $scope->getType($args[1]->value);
                        if (!$colsType instanceof ConstantArrayType) {
                            return null;
                        }
                        $cols = $this->constantArrayToScalarArray($colsType);
                    }

                    $select->from($fromType->getValue(), $cols);
                    break;

                case 'join':
                case 'joinleft':
                case 'joininner':
                    if (\count($args) < 2) {
                        return null;
                    }

                    $joinNameType = $scope->getType($args[0]->value);
                    $joinConditionsType = $scope->getType($args[1]->value);

                    if (!$joinNameType instanceof ConstantStringType) {
                        return null;
                    }
                    if (!$joinConditionsType instanceof ConstantStringType) {
                        if ($args[1]->value instanceof Expr\BinaryOp\Concat) {
                            $joinConditionsType = $this->resolveConcat($args[1]->value, $scope, $boundValues);
                            if (!$joinConditionsType instanceof ConstantStringType) {
                                return null;
                            }
                        } else {
                            return null;
                        }
                    }

                    $joinCols = '*';
                    if (\count($args) >= 3) {
                        $joinColsType = $scope->getType($args[2]->value);
                        if ($joinColsType instanceof ConstantArrayType) {
                            $joinCols = $this->constantArrayToScalarArray($joinColsType);
                        } elseif ($joinColsType instanceof ConstantStringType) {
                            $joinCols = $joinColsType->getValue();
                        } else {
                            throw new ShouldNotHappenException('Join columns should be string or array');
                        }
                    }

                    if ('join' === strtolower($methodName)) {
                        $select->join($joinNameType->getValue(), $joinConditionsType->getValue(), $joinCols);
                    } elseif ('joininner' === strtolower($methodName)) {
                        $select->joinInner($joinNameType->getValue(), $joinConditionsType->getValue(), $joinCols);
                    } else {
                        $select->joinLeft($joinNameType->getValue(), $joinConditionsType->getValue(), $joinCols);
                    }
                    break;

                case 'where':
                    if (\count($args) < 1) {
                        return null;
                    }
                    $whereCondType = $scope->getType($args[0]->value);
                    if (!$whereCondType instanceof ConstantStringType) {
                        return null;
                    }

                    if (\count($args) > 1) {
                        $boundValues[] = $args[1]->value;
                    }

                    $select->where($whereCondType->getValue());
                    break;

                case 'group':
                case 'order':
                    if (\count($args) < 1) {
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

                    if ('group' === strtolower($methodName)) {
                        $select->group($spec);
                    } else {
                        $select->order($spec);
                    }
                    break;

                case 'limit':
                    if (\count($args) < 1) {
                        return null;
                    }

                    $limitType = $scope->getType($args[0]->value);
                    if (!$limitType instanceof ConstantIntegerType) {
                        throw new ShouldNotHappenException('Limit should be an integer');
                    }
                    $limit = $limitType->getValue();

                    $offset = null;
                    if (\count($args) >= 2) {
                        $offsetType = $scope->getType($args[1]->value);
                        if (!$offsetType instanceof ConstantIntegerType) {
                            throw new ShouldNotHappenException('Offset should be an integer');
                        }
                        $offset = $offsetType->getValue();
                    }

                    if (null !== $offset) {
                        $select->limit($limit, $offset);
                    } else {
                        $select->limit($limit);
                    }

                    break;
                case 'setintegritycheck':
                    if (\count($args) < 1) {
                        return null;
                    }
                    $flagType = $scope->getType($args[0]->value);

                    if (!$flagType instanceof ConstantBooleanType) {
                        throw new ShouldNotHappenException('Integrity check flag should be boolean');
                    }

                    $select->setIntegrityCheck($flagType->getValue());
                    break;
                case '__tostring':
                    // prevent default-exception
                    break;
                default:
                    throw new ShouldNotHappenException('Unsupported method "'.$methodName.'"');
            }
        }

        return $select;
    }

    /**
     * @param list<Expr> $boundValues
     */
    private function resolveConcat(Expr\BinaryOp\Concat $expr, Scope $scope, array &$boundValues): ?ConstantStringType
    {
        $leftType = $scope->getType($expr->left);
        $rightType = $scope->getType($expr->right);

        if ($expr->left instanceof Expr\BinaryOp\Concat && $rightType instanceof ConstantStringType) {
            $left = $this->resolveConcat($expr->left, $scope, $boundValues);
            if ($left === null) {
                return null;
            }
            return new ConstantStringType($left->getValue() . $rightType->getValue());
        }
        if ($expr->right instanceof Expr\BinaryOp\Concat && $leftType instanceof ConstantStringType) {
            $right = $this->resolveConcat($expr->right, $scope, $boundValues);
            if ($right === null) {
                return null;
            }
            return new ConstantStringType($leftType->getValue() . $right->getValue());
        }

        if ($leftType instanceof ConstantStringType || $rightType instanceof ConstantStringType) {
            if ($expr->left instanceof MethodCall && $rightType instanceof ConstantStringType) {
                $boundValues[] = $expr->left;

                return new ConstantStringType('?'.$rightType->getValue());
            }
            if ($leftType instanceof ConstantStringType && $expr->right instanceof MethodCall) {
                $boundValues[] = $expr->right;
                return new ConstantStringType($leftType->getValue().'?');
            }
        }

        return null;
    }

    /**
     * @return scalar[]
     */
    private function constantArrayToScalarArray(ConstantArrayType $constantArrayType): array
    {
        $integerType = new IntegerType();
        if ($integerType->isSuperTypeOf($constantArrayType->getKeyType())->no()) {
            throw new ShouldNotHappenException('array shape is not yet supported');
        }

        $values = [];
        foreach ($constantArrayType->getValueTypes() as $valueType) {
            if (!$valueType instanceof ConstantStringType) {
                throw new ShouldNotHappenException('non string values not yet supported');
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

            if (null !== $assign && null !== $this->resolveName($assign->var)) {
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

    private function createTableAbstract(ClassReflection $tableClassReflection): Zend_Db_Table_Abstract
    {
        $reflectionClass = new ReflectionClass($tableClassReflection->getName());
        $instance = $reflectionClass->newInstance();
        if (!$instance instanceof Zend_Db_Table_Abstract) {
            throw new ShouldNotHappenException();
        }

        return $instance;
    }

    /**
     * @return MethodCall[]
     */
    public function findOnSelectMethodCalls(Assign $selectCreate): array
    {
        $methodCalls = [];

        $current = $selectCreate;
        do {
            $methodCall = $this->findFirstNext($current, function (Node $node) use ($selectCreate): bool {
                if ($node instanceof MethodCall && $this->resolveName($node->var) === $this->resolveName($selectCreate->var)) {
                    return true;
                }

                return false;
            });

            if (null !== $methodCall) {
                if (!$methodCall instanceof MethodCall) {
                    throw new ShouldNotHappenException();
                }

                $methodCalls[] = $methodCall;
                $current = $methodCall;
            }
        } while (null !== $methodCall);

        return $methodCalls;
    }

    private function resolveName(Node $node): ?string
    {
        if (!property_exists($node, 'name')) {
            return null;
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
            if ($next instanceof Return_ && null === $next->expr) {
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
