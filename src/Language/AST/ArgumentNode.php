<?hh
namespace GraphQL\Language\AST;

class ArgumentNode extends Node
{
    public string $kind = NodeKind::ARGUMENT;

    /**
     * @var ValueNode
     */
    public $value;

    /**
     * @var NameNode
     */
    public $name;
}
