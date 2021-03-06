<?hh //strict
//decl
namespace GraphQL\Tests\Utils;

use GraphQL\GraphQL;
use function Facebook\FBExpect\expect;
use GraphQL\Schema;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\SchemaPrinter;

class SchemaPrinterTest extends \Facebook\HackTest\HackTest
{
    // Describe: Type System Printer

    private function printForTest(Schema $schema):string
    {
        return "\n" . SchemaPrinter::doPrint($schema);
    }

    private function printSingleFieldSchema(array<string, mixed> $fieldConfig):string
    {
        $root = new ObjectType([
            'name' => 'Root',
            'fields' => [
                'singleField' => $fieldConfig
            ]
        ]);
        return $this->printForTest(new Schema(['query' => $root]));
    }

    /**
     * @it Prints String Field
     */
    public function testPrintsStringField():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::string()
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField: String
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints [String] Field
     */
    public function testPrintArrayStringField():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::listOf(GraphQlType::string())
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField: [String]
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints String! Field
     */
    public function testPrintNonNullStringField():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::nonNull(GraphQlType::string())
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField: String!
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints [String]! Field
     */
    public function testPrintNonNullArrayStringField():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::nonNull(GraphQlType::listOf(GraphQlType::string()))
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField: [String]!
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints [String!] Field
     */
    public function testPrintArrayNonNullStringField():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::listOf(GraphQlType::nonNull(GraphQlType::string()))
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField: [String!]
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints [String!]! Field
     */
    public function testPrintNonNullArrayNonNullStringField():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::nonNull(GraphQlType::listOf(GraphQlType::nonNull(GraphQlType::string())))
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField: [String!]!
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints Object Field
     */
    public function testPrintObjectField():void
    {
        $fooType = new ObjectType([
            'name' => 'Foo',
            'fields' => ['str' => ['type' => GraphQlType::string()]]
        ]);

        $root = new ObjectType([
            'name' => 'Root',
            'fields' => ['foo' => ['type' => $fooType]]
        ]);

        $schema = new Schema(['query' => $root]);
        $output = $this->printForTest($schema);
        expect('
schema {
  query: Root
}

type Foo {
  str: String
}

type Root {
  foo: Foo
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints String Field With Int Arg
     */
    public function testPrintsStringFieldWithIntArg():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::string(),
            'args' => ['argOne' => ['type' => GraphQlType::int()]]
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField(argOne: Int): String
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints String Field With Int Arg With Default
     */
    public function testPrintsStringFieldWithIntArgWithDefault():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::string(),
            'args' => ['argOne' => ['type' => GraphQlType::int(), 'defaultValue' => 2]]
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField(argOne: Int = 2): String
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints String Field With Int Arg With Default Null
     */
    public function testPrintsStringFieldWithIntArgWithDefaultNull():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::string(),
            'args' => ['argOne' => ['type' => GraphQlType::int(), 'defaultValue' => null]]
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField(argOne: Int = null): String
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints String Field With Int! Arg
     */
    public function testPrintsStringFieldWithNonNullIntArg():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::string(),
            'args' => ['argOne' => ['type' => GraphQlType::nonNull(GraphQlType::int())]]
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField(argOne: Int!): String
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints String Field With Multiple Args
     */
    public function testPrintsStringFieldWithMultipleArgs():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::string(),
            'args' => [
                'argOne' => ['type' => GraphQlType::int()],
                'argTwo' => ['type' => GraphQlType::string()]
            ]
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField(argOne: Int, argTwo: String): String
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints String Field With Multiple Args, First is Default
     */
    public function testPrintsStringFieldWithMultipleArgsFirstIsDefault():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::string(),
            'args' => [
                'argOne' => ['type' => GraphQlType::int(), 'defaultValue' => 1],
                'argTwo' => ['type' => GraphQlType::string()],
                'argThree' => ['type' => GraphQlType::boolean()]
            ]
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField(argOne: Int = 1, argTwo: String, argThree: Boolean): String
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints String Field With Multiple Args, Second is Default
     */
    public function testPrintsStringFieldWithMultipleArgsSecondIsDefault():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::string(),
            'args' => [
                'argOne' => ['type' => GraphQlType::int()],
                'argTwo' => ['type' => GraphQlType::string(), 'defaultValue' => 'foo'],
                'argThree' => ['type' => GraphQlType::boolean()]
            ]
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField(argOne: Int, argTwo: String = "foo", argThree: Boolean): String
}
')->toBePHPEqual($output);
    }

    /**
     * @it Prints String Field With Multiple Args, Last is Default
     */
    public function testPrintsStringFieldWithMultipleArgsLastIsDefault():void
    {
        $output = $this->printSingleFieldSchema([
            'type' => GraphQlType::string(),
            'args' => [
                'argOne' => ['type' => GraphQlType::int()],
                'argTwo' => ['type' => GraphQlType::string()],
                'argThree' => ['type' => GraphQlType::boolean(), 'defaultValue' => false]
            ]
        ]);
        expect('
schema {
  query: Root
}

type Root {
  singleField(argOne: Int, argTwo: String, argThree: Boolean = false): String
}
')->toBePHPEqual($output);
    }

    /**
     * @it Print Interface
     */
    public function testPrintInterface():void
    {
        $fooType = new InterfaceType([
            'name' => 'Foo',
            'resolveType' => function() { return null; },
            'fields' => ['str' => ['type' => GraphQlType::string()]]
        ]);

        $barType = new ObjectType([
            'name' => 'Bar',
            'fields' => ['str' => ['type' => GraphQlType::string()]],
            'interfaces' => [$fooType]
        ]);

        $root = new ObjectType([
            'name' => 'Root',
            'fields' => ['bar' => ['type' => $barType]]
        ]);

        $schema = new Schema([
            'query' => $root,
            'types' => [$barType]
        ]);
        $output = $this->printForTest($schema);
        expect('
schema {
  query: Root
}

type Bar implements Foo {
  str: String
}

interface Foo {
  str: String
}

type Root {
  bar: Bar
}
')->toBePHPEqual($output);
    }

    /**
     * @it Print Multiple Interface
     */
    public function testPrintMultipleInterface():void
    {
        $fooType = new InterfaceType([
            'name' => 'Foo',
            'resolveType' => function() { return null; },
            'fields' => ['str' => ['type' => GraphQlType::string()]]
        ]);

        $baazType = new InterfaceType([
            'name' => 'Baaz',
            'resolveType' => function() { return null; },
            'fields' => ['int' => ['type' => GraphQlType::int()]]
        ]);

        $barType = new ObjectType([
            'name' => 'Bar',
            'fields' => [
                'str' => ['type' => GraphQlType::string()],
                'int' => ['type' => GraphQlType::int()]
            ],
            'interfaces' => [$fooType, $baazType]
        ]);

        $root = new ObjectType([
            'name' => 'Root',
            'fields' => ['bar' => ['type' => $barType]]
        ]);

        $schema = new Schema([
            'query' => $root,
            'types' => [$barType]
        ]);
        $output = $this->printForTest($schema);
        expect('
schema {
  query: Root
}

interface Baaz {
  int: Int
}

type Bar implements Foo, Baaz {
  str: String
  int: Int
}

interface Foo {
  str: String
}

type Root {
  bar: Bar
}
')->toBePHPEqual($output);
    }

    /**
     * @it Print Unions
     */
    public function testPrintUnions():void
    {
        $fooType = new ObjectType([
            'name' => 'Foo',
            'fields' => ['bool' => ['type' => GraphQlType::boolean()]]
        ]);

        $barType = new ObjectType([
            'name' => 'Bar',
            'fields' => ['str' => ['type' => GraphQlType::string()]]
        ]);

        $singleUnion = new UnionType([
            'name' => 'SingleUnion',
            'resolveType' => function() { return null; },
            'types' => [$fooType]
        ]);

        $multipleUnion = new UnionType([
            'name' => 'MultipleUnion',
            'resolveType' => function() { return null; },
            'types' => [$fooType, $barType]
        ]);

        $root = new ObjectType([
            'name' => 'Root',
            'fields' => [
                'single' => ['type' => $singleUnion],
                'multiple' => ['type' => $multipleUnion]
            ]
        ]);

        $schema = new Schema(['query' => $root]);
        $output = $this->printForTest($schema);
        expect('
schema {
  query: Root
}

type Bar {
  str: String
}

type Foo {
  bool: Boolean
}

union MultipleUnion = Foo | Bar

type Root {
  single: SingleUnion
  multiple: MultipleUnion
}

union SingleUnion = Foo
')->toBePHPEqual($output);
    }

    /**
     * @it Print Input Type
     */
    public function testInputType():void
    {
        $inputType = new InputObjectType([
            'name' => 'InputType',
            'fields' => ['int' => ['type' => GraphQlType::int()]]
        ]);

        $root = new ObjectType([
            'name' => 'Root',
            'fields' => [
                'str' => [
                    'type' => GraphQlType::string(),
                    'args' => ['argOne' => ['type' => $inputType]]
                ]
            ]
        ]);

        $schema = new Schema(['query' => $root]);
        $output = $this->printForTest($schema);
        expect('
schema {
  query: Root
}

input InputType {
  int: Int
}

type Root {
  str(argOne: InputType): String
}
')->toBePHPEqual($output);
    }

    /**
     * @it Custom Scalar
     */
    public function testCustomScalar():void
    {
        $oddType = new CustomScalarType([
            'name' => 'Odd',
            'serialize' => function($value) {
                return $value % 2 === 1 ? $value : null;
            }
        ]);

        $root = new ObjectType([
            'name' => 'Root',
            'fields' => [
                'odd' => ['type' => $oddType]
            ]
        ]);

        $schema = new Schema(['query' => $root]);
        $output = $this->printForTest($schema);
        expect('
schema {
  query: Root
}

scalar Odd

type Root {
  odd: Odd
}
')->toBePHPEqual($output);
    }

    /**
     * @it Enum
     */
    public function testEnum():void
    {
        $RGBType = new EnumType([
            'name' => 'RGB',
            'values' => [
                'RED' => ['value' => 0],
                'GREEN' => ['value' => 1],
                'BLUE' => ['value' => 2]
            ]
        ]);

        $root = new ObjectType([
            'name' => 'Root',
            'fields' => [
                'rgb' => ['type' => $RGBType]
            ]
        ]);

        $schema = new Schema(['query' => $root]);
        $output = $this->printForTest($schema);
        expect('
schema {
  query: Root
}

enum RGB {
  RED
  GREEN
  BLUE
}

type Root {
  rgb: RGB
}
')->toBePHPEqual($output);
    }

    /**
     * @it Print Introspection Schema
     */
    /*public function testPrintIntrospectionSchema():void
    {
        $root = new ObjectType([
            'name' => 'Root',
            'fields' => [
                'onlyField' => ['type' => GraphQlType::string()]
            ]
        ]);

        $schema = new Schema(['query' => $root]);
        $output = SchemaPrinter::printIntrosepctionSchema($schema);
        $introspectionSchema = <<<'EOT'
schema {
  query: Root
}

# Directs the executor to include this field or fragment only when the `if` argument is true.
directive @include(
  # Included when true.
  if: Boolean!
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

# Directs the executor to skip this field or fragment when the `if` argument is true.
directive @skip(
  # Skipped when true.
  if: Boolean!
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

# Marks an element of a GraphQL schema as no longer supported.
directive @deprecated(
  # Explains why this element was deprecated, usually also including a suggestion
  # for how to access supported similar data. Formatted in
  # [Markdown](https://daringfireball.net/projects/markdown/).
  reason: String = "No longer supported"
) on FIELD_DEFINITION | ENUM_VALUE

# A Directive provides a way to describe alternate runtime execution and type validation behavior in a GraphQL document.
#
# In some cases, you need to provide options to alter GraphQL's execution behavior
# in ways field arguments will not suffice, such as conditionally including or
# skipping a field. Directives provide this by describing additional information
# to the executor.
type __Directive {
  name: String!
  description: String
  locations: [__DirectiveLocation!]!
  args: [__InputValue!]!
  onOperation: Boolean! @deprecated(reason: "Use `locations`.")
  onFragment: Boolean! @deprecated(reason: "Use `locations`.")
  onField: Boolean! @deprecated(reason: "Use `locations`.")
}

# A Directive can be adjacent to many parts of the GraphQL language, a
# __DirectiveLocation describes one such possible adjacencies.
enum __DirectiveLocation {
  # Location adjacent to a query operation.
  QUERY

  # Location adjacent to a mutation operation.
  MUTATION

  # Location adjacent to a subscription operation.
  SUBSCRIPTION

  # Location adjacent to a field.
  FIELD

  # Location adjacent to a fragment definition.
  FRAGMENT_DEFINITION

  # Location adjacent to a fragment spread.
  FRAGMENT_SPREAD

  # Location adjacent to an inline fragment.
  INLINE_FRAGMENT

  # Location adjacent to a schema definition.
  SCHEMA

  # Location adjacent to a scalar definition.
  SCALAR

  # Location adjacent to an object type definition.
  OBJECT

  # Location adjacent to a field definition.
  FIELD_DEFINITION

  # Location adjacent to an argument definition.
  ARGUMENT_DEFINITION

  # Location adjacent to an interface definition.
  INTERFACE

  # Location adjacent to a union definition.
  UNION

  # Location adjacent to an enum definition.
  ENUM

  # Location adjacent to an enum value definition.
  ENUM_VALUE

  # Location adjacent to an input object type definition.
  INPUT_OBJECT

  # Location adjacent to an input object field definition.
  INPUT_FIELD_DEFINITION
}

# One possible value for a given Enum. Enum values are unique values, not a
# placeholder for a string or numeric value. However an Enum value is returned in
# a JSON response as a string.
type __EnumValue {
  name: String!
  description: String
  isDeprecated: Boolean!
  deprecationReason: String
}

# Object and Interface types are described by a list of Fields, each of which has
# a name, potentially a list of arguments, and a return type.
type __Field {
  name: String!
  description: String
  args: [__InputValue!]!
  type: __Type!
  isDeprecated: Boolean!
  deprecationReason: String
}

# Arguments provided to Fields or Directives and the input fields of an
# InputObject are represented as Input Values which describe their type and
# optionally a default value.
type __InputValue {
  name: String!
  description: String
  type: __Type!

  # A GraphQL-formatted string representing the default value for this input value.
  defaultValue: String
}

# A GraphQL Schema defines the capabilities of a GraphQL server. It exposes all
# available types and directives on the server, as well as the entry points for
# query, mutation, and subscription operations.
type __Schema {
  # A list of all types supported by this server.
  types: [__Type!]!

  # The type that query operations will be rooted at.
  queryType: __Type!

  # If this server supports mutation, the type that mutation operations will be rooted at.
  mutationType: __Type

  # If this server support subscription, the type that subscription operations will be rooted at.
  subscriptionType: __Type

  # A list of all directives supported by this server.
  directives: [__Directive!]!
}

# The fundamental unit of any GraphQL Schema is the type. There are many kinds of
# types in GraphQL as represented by the `__TypeKind` enum.
#
# Depending on the kind of a type, certain fields describe information about that
# type. Scalar types provide no information beyond a name and description, while
# Enum types provide their values. Object and Interface types provide the fields
# they describe. Abstract types, Union and Interface, provide the Object types
# possible at runtime. List and NonNull types compose other types.
type __Type {
  kind: __TypeKind!
  name: String
  description: String
  fields(includeDeprecated: Boolean = false): [__Field!]
  interfaces: [__Type!]
  possibleTypes: [__Type!]
  enumValues(includeDeprecated: Boolean = false): [__EnumValue!]
  inputFields: [__InputValue!]
  ofType: __Type
}

# An enum describing what kind of type a given `__Type` is.
enum __TypeKind {
  # Indicates this type is a scalar.
  SCALAR

  # Indicates this type is an object. `fields` and `interfaces` are valid fields.
  OBJECT

  # Indicates this type is an interface. `fields` and `possibleTypes` are valid fields.
  INTERFACE

  # Indicates this type is a union. `possibleTypes` is a valid field.
  UNION

  # Indicates this type is an enum. `enumValues` is a valid field.
  ENUM

  # Indicates this type is an input object. `inputFields` is a valid field.
  INPUT_OBJECT

  # Indicates this type is a list. `ofType` is a valid field.
  LIST

  # Indicates this type is a non-null. `ofType` is a valid field.
  NON_NULL
}

EOT;
        expect($introspectionSchema)->toBePHPEqual($output);
    }*/
}