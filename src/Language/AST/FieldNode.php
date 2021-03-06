<?hh //strict
namespace GraphQL\Language\AST;

class FieldNode extends Node implements SelectionNode, HasDirectives
{
    public function __construct(
        public NameNode $name,
        public ?NameNode $alias = null,
        public array<ArgumentNode> $arguments = [],
        public array<DirectiveNode> $directives = [],
        public ?SelectionSetNode $selectionSet = null,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::FIELD);
    }

    public function getDirectives():array<DirectiveNode>
    {
        return $this->directives;
    }
}
