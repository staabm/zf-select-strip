<?php

declare(strict_types=1);

namespace staabm\ZfSelectStrip\Rector;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Type\ObjectType;
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


        // we only care about "set*" method names
        if (! $this->isName($node->name, 'set*')) {
            // return null to skip it
            return null;
        }

        /*
        $newNode
        $this->nodesToAddCollector->addNodeAfterNode($newNode, $node);
*/

        $methodCallName = $this->getName($node->name);
        $newMethodCallName = Strings::replace($methodCallName, '#^fetchRow#', 'fetchRowByStatement');

        $node->name = new Identifier($newMethodCallName);

        // return $node if you modified it
        return $node;
    }

    private function shouldSkip(MethodCall $methodCall): bool
    {
        if (! $this->nodeNameResolver->isName($methodCall->name, 'fetchRow')) {
            return true;
        }

        $varType = $this->nodeTypeResolver->getType($methodCall->var);
        if (! $varType instanceof ObjectType) {
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