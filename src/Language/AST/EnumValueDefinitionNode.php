<?hh //strict
namespace GraphQL\Language\AST;

class EnumValueDefinitionNode extends Node
{
    public string $kind = NodeKind::ENUM_VALUE_DEFINITION;

    public function __construct(
        public NameNode $name,
        public NodeList $directives,
        public ?string $description,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
