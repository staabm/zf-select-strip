<?php

declare(strict_types=1);

namespace staabm\ZfSelectStrip\Rector;

use Clx_Model_Mapper_Abstract;
use ClxProductNet_DbStatement;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;
use Rector\Core\Rector\AbstractRector;
use Rector\NodeTypeResolver\Node\AttributeKey;
use staabm\ZfSelectStrip\Reflection\ZfSelectReflection;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Zend_Db_Table_Select;

final class ZfSelectRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);
        if (!$scope instanceof Scope) {
            return null;
        }

        if ($this->shouldSkip($node, $scope)) {
            return null;
        }

        $tableSelectArg = $node->getArgs()[0];
        $argExpr = $tableSelectArg->value;

        if (!$argExpr instanceof MethodCall && !$argExpr instanceof Variable) {
            throw new ShouldNotHappenException('Expected "MethodCall" or "Variable"');
        }

        $zfSelectReflection = new ZfSelectReflection();
        $selectCreateAssign = $zfSelectReflection->findSelectCreateAssign($argExpr);
        if (null === $selectCreateAssign) {
            return null;
        }

        $boundValues = [];
        $selectClone = $zfSelectReflection->cloneTableSelect($selectCreateAssign, $scope, $boundValues);
        if (null === $selectClone) {
            return null;
        }

        if ($this->nodeNameResolver->isName($node->name, 'fetchRow')) {
            $node->name = new Identifier('fetchRowByStatement');
        }
        if ($this->nodeNameResolver->isName($node->name, 'fetchAll')) {
            $node->name = new Identifier('fetchAllByStatement');
        }

        $items = [];
        foreach ($boundValues as $boundValue) {
            $items[] = new Node\Expr\ArrayItem($boundValue);
        }

        $wrappedStatement = new New_(
            new Name(ClxProductNet_DbStatement::class),
            [$tableSelectArg, new Arg(new Array_($items))]
        );
        $node->args[0] = new Arg($wrappedStatement);

        $selectCreateAssign->expr = new String_($selectClone->__toString());

        $methodCalls = $zfSelectReflection->findOnSelectMethodCalls($selectCreateAssign);
        foreach ($methodCalls as $methodCall) {
            $this->nodesToRemoveCollector->addNodeToRemove($methodCall);
        }

        return $node;
    }

    private function shouldSkip(MethodCall $methodCall, Scope $scope): bool
    {
        if (\count($methodCall->getArgs()) < 1) {
            return true;
        }

        $argType = $scope->getType($methodCall->getArgs()[0]->value);
        if (!$argType instanceof ObjectType) {
            return true;
        }

        if (null === $argType->getClassReflection()) {
            return true;
        }

        if (Zend_Db_Table_Select::class !== $argType->getClassReflection()->getName() && !$argType->getClassReflection()->isSubclassOf(Zend_Db_Table_Select::class)) {
            return true;
        }

        if (!$this->nodeNameResolver->isName($methodCall->name, 'fetchRow') && !$this->nodeNameResolver->isName($methodCall->name, 'fetchAll')) {
            return true;
        }

        $varType = $this->nodeTypeResolver->getType($methodCall->var);
        if ($varType instanceof ThisType || $varType instanceof ObjectType) {
            if (null === $varType->getClassReflection()) {
                return true;
            }

            if ($varType->getClassReflection()->isSubclassOf(Clx_Model_Mapper_Abstract::class)) {
                return false;
            }
        }

        return true;
    }

    /**
     * This method helps other to understand the rule and to generate documentation.
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change method calls from set* to change*.', [
                new CodeSample(
                // code before
                    '$user->setPassword("123456");',
                    // code after
                    '$user->changePassword("123456");'
                ),
            ]
        );
    }
}
