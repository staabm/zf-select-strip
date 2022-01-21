<?php

namespace staabm\ZfSelectStrip\Extensions;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use staabm\ZfSelectStrip\Reflection\ZfSelectReflection;
use Zend_Db_Table_Select;

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
