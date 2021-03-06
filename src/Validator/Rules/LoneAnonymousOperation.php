<?hh //strict
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;
use namespace HH\Lib\{Vec};

/**
 * Lone anonymous operation
 *
 * A GraphQL document is only valid if when it contains an anonymous operation
 * (the query short-hand) that it contains only that one operation definition.
 */
class LoneAnonymousOperation extends AbstractValidationRule
{
    public static function anonOperationNotAloneMessage():string
    {
        return 'This anonymous operation must be the only defined operation.';
    }

    /* HH_FIXME[4030]*/
    public function getVisitor(ValidationContext $context)
    {
        $operationCount = 0;
        return [
            /* HH_FIXME[2087]*/
            NodeKind::DOCUMENT => function(DocumentNode $node) use (&$operationCount)
            {
                $tmp = Vec\filter(
                    $node->definitions,
                    function ($definition)
                    {
                        return $definition->kind === NodeKind::OPERATION_DEFINITION;
                    }
                );
                $operationCount = \count($tmp);
            },
            /* HH_FIXME[2087]*/
            NodeKind::OPERATION_DEFINITION => function(OperationDefinitionNode $node) use (&$operationCount, $context) {
                if (!$node->name && $operationCount > 1) {
                    $context->reportError(
                        new Error(self::anonOperationNotAloneMessage(), [$node])
                    );
                }
            }
        ];
    }
}
