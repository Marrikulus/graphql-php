<?hh //partial
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;
use namespace HH\Lib\{Vec};

class FieldsOnCorrectType extends AbstractValidationRule
{
    public static function undefinedFieldMessage(string $field, string $type, array<string> $suggestedTypes = []):string
    {
        $message = 'Cannot query field "' . $field . '" on type "' . $type.'".';

        $maxLength = 5;
        $count = \count($suggestedTypes);
        if ($count > 0)
        {
            $suggestions = \array_slice($suggestedTypes, 0, $maxLength);
            $suggestions = Vec\map($suggestions, function($t) { return "\"$t\""; });
            $suggestions = \implode(', ', $suggestions);

            if ($count > $maxLength) {
                $suggestions .= ', and ' . ($count - $maxLength) . ' other types';
            }
            $message .= " However, this field exists on $suggestions.";
            $message .= ' Perhaps you meant to use an inline fragment?';
        }
        return $message;
    }

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::FIELD => function(FieldNode $node) use ($context) {
                $type = $context->getParentType();
                if ($type)
                {
                    $fieldDef = $context->getFieldDef();
                    if (!$fieldDef)
                    {
                        $context->reportError(new Error(
                            static::undefinedFieldMessage($node->name->value, $type->name),
                            [$node]
                        ));
                    }
                }
            }
        ];
    }
}
