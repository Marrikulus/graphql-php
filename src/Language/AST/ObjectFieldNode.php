<?hh //strict
namespace GraphQL\Language\AST;

class ObjectFieldNode extends Node
{
    public function __construct(
        public NameNode $name,
        public Node $value,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::OBJECT_FIELD);
    }
}
