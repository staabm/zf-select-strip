<?php

declare(strict_types=1);

namespace staabm\ZfSelectStrip\Rector;

use Clx_Model_Mapper_Abstract;
use ClxProductNet_DbStatement;
use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

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
     * @param MethodCall $node - we can add "MethodCall" type here, because
     *                         only this node is in "getNodeTypes()"
     */
    public function refactor(Node $node): ?Node
    {
        if ($this->shouldSkip($node)) {
            return null;
        }


        /*
        $newNode
        $this->nodesToAddCollector->addNodeAfterNode($newNode, $node);
*/

        $node->name = new Identifier('fetchRowByStatement');

        $wrappedStatement = new New_(
            new Name(ClxProductNet_DbStatement::class),
            [$node->args[0], new Arg(new Array_())]
        );
        $node->args[0] = new Arg($wrappedStatement);

        // return $node if you modified it
        return $node;
    }

    private function shouldSkip(MethodCall $methodCall): bool
    {
        if (count($methodCall->getArgs()) < 1) {
            return true;
        }

        if (!$this->nodeNameResolver->isName($methodCall->name, 'fetchRow')) {
            return true;
        }

        $varType = $this->nodeTypeResolver->getType($methodCall->var);
        if (!$varType instanceof ThisType) {
            return true;
        }

        return !$varType->getClassReflection()->isSubclassOf(Clx_Model_Mapper_Abstract::class);
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