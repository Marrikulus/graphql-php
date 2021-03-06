<?hh //strict
//decl
namespace GraphQL\Tests;

use GraphQL\Language\AST\NameNode;
use function Facebook\FBExpect\expect;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;

class SchemaPrinterTest extends \Facebook\HackTest\HackTest
{
    /**
     * @it prints minimal ast
     */
    public function testPrintsMinimalAst():void
    {
        $ast = new ScalarTypeDefinitionNode(
            new NameNode('foo')
        );
        expect(Printer::doPrint($ast))->toBePHPEqual('scalar foo');
    }

    /**
     * @it produces helpful error messages
     */
    public function testProducesHelpfulErrorMessages():void
    {
        // $badAst1 = { random: 'Data' };
        $badAst = (object) ['random' => 'Data'];
        $this->setExpectedException('Exception', 'Invalid AST Node: {"random":"Data"}');
        Printer::doPrint($badAst);
    }

    /**
     * @it does not alter ast
     */
    public function testDoesNotAlterAst():void
    {
        $kitchenSink = \file_get_contents(__DIR__ . '/schema-kitchen-sink.graphql');

        $ast = Parser::parse($kitchenSink);
        $astCopy = $ast->cloneDeep();
        Printer::doPrint($ast);

        expect($ast)->toBePHPEqual($astCopy);
    }

    public function testPrintsKitchenSink():void
    {
        $kitchenSink = \file_get_contents(__DIR__ . '/schema-kitchen-sink.graphql');

        $ast = Parser::parse($kitchenSink);
        $printed = Printer::doPrint($ast);

        $expected = 'schema {
  query: QueryType
  mutation: MutationType
}

type Foo implements Bar {
  one: Type
  two(argument: InputType!): Type
  three(argument: InputType, other: String): Int
  four(argument: String = "string"): String
  five(argument: [String] = ["string", "string"]): String
  six(argument: InputType = {key: "value"}): Type
  seven(argument: Int = null): Type
}

type AnnotatedObject @onObject(arg: "value") {
  annotatedField(arg: Type = "default" @onArg): Type @onField
}

interface Bar {
  one: Type
  four(argument: String = "string"): String
}

interface AnnotatedInterface @onInterface {
  annotatedField(arg: Type @onArg): Type @onField
}

union Feed = Story | Article | Advert

union AnnotatedUnion @onUnion = A | B

union AnnotatedUnionTwo @onUnion = A | B

scalar CustomScalar

scalar AnnotatedScalar @onScalar

enum Site {
  DESKTOP
  MOBILE
}

enum AnnotatedEnum @onEnum {
  ANNOTATED_VALUE @onEnumValue
  OTHER_VALUE
}

input InputType {
  key: String!
  answer: Int = 42
}

input AnnotatedInput @onInputObjectType {
  annotatedField: Type @onField
}

extend type Foo {
  seven(argument: [String]): Type
}

extend type Foo @onType {}

type NoFields {}

directive @skip(if: Boolean!) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

directive @include(if: Boolean!) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

directive @include2(if: Boolean!) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT
';
        expect($printed)->toBePHPEqual($expected);
    }
}
