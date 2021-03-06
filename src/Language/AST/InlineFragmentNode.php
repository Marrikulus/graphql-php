<?hh //strict
namespace GraphQL\Language\AST;

class InlineFragmentNode extends Node implements SelectionNode, HasDirectives
{
    public function __construct(
        public ?NamedTypeNode $typeCondition,
        public array<DirectiveNode> $directives,
        public SelectionSetNode $selectionSet,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::INLINE_FRAGMENT);
    }

    public function getDirectives():array<DirectiveNode>
    {
    	return $this->directives;
    }
}
