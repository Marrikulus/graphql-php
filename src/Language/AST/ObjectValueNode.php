<?hh //strict
namespace GraphQL\Language\AST;

class ObjectValueNode extends Node implements ValueNode<array<ObjectFieldNode>>
{
    public function __construct(
        public array<ObjectFieldNode> $fields,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::OBJECT);
    }

    public function getValue():array<ObjectFieldNode>
	{
		return $this->fields;
	}
}
