<?php

namespace staabm\ZfSelectStrip\Extensions;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use ReflectionClass;
use staabm\PHPStanDba\QueryReflection\QueryReflection;
use staabm\ZfSelectStrip\Reflection\ZfSelectReflection;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;
use function PHPStan\dumpType;

final class ZfSelectDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Zend_Db_Table_Select::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return '__toString' === $methodReflection->getName();
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $defaultReturn = ParametersAcceptorSelector::selectSingle($methodReflection->getVariants())->getReturnType();

        $zfSelectReflection = new ZfSelectReflection();
        $selectCreateAssign = $zfSelectReflection->findSelectCreateAssign($methodCall);
        if (null === $selectCreateAssign) {
            return $defaultReturn;
        }

        $boundValues = [];
        $selectClone = $zfSelectReflection->cloneTableSelect($selectCreateAssign, $scope, $boundValues);

        if (null === $selectClone) {
            return $defaultReturn;
        }

        return new ConstantStringType($selectClone->__toString());
    }
}