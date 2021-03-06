<?hh //strict
//decl
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Error\InvariantViolation;
use function Facebook\FBExpect\expect;
use GraphQL\Error\Warning;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;

class ExecutorLazySchemaTest extends \Facebook\HackTest\HackTest
{
    public ?ObjectType          $QueryType;
    public ?ObjectType          $SomeObjectType;
    public ?ObjectType          $OtherObjectType;
    public ?ObjectType          $DeeperObjectType;
    public ?CustomScalarType    $SomeScalarType;
    public ?UnionType           $SomeUnionType;
    public ?InterfaceType       $SomeInterfaceType;

    // TODO: find out why unused??
    public ?EnumType $SomeEnumType;
    public ?InputObjectType $SomeInputObjectType;

    public array<string> $calls = [];

    public array<string, bool> $loadedTypes = [];

    public async function afterEachTestAsync(): Awaitable<void>
    {
        $this->QueryType = null;
        $this->SomeObjectType = null;
        $this->OtherObjectType = null;
        $this->DeeperObjectType = null;
        $this->SomeScalarType = null;
        $this->SomeUnionType = null;
        $this->SomeInterfaceType = null;
        $this->calls = [];
        $this->loadedTypes = [];
    }

    public function testWarnsAboutSlowIsTypeOfForLazySchema():void
    {
        // isTypeOf used to resolve runtime type for Interface
        $petType = new InterfaceType([
            'name' => 'Pet',
            'fields' => function() {
                return [
                    'name' => ['type' => GraphQlType::string()]
                ];
            }
        ]);

        // Added to interface type when defined
        $dogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => function($obj) { return $obj instanceof Dog; },
            'fields' => function() {
                return [
                    'name' => ['type' => GraphQlType::string()],
                    'woofs' => ['type' => GraphQlType::boolean()]
                ];
            }
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf' => function ($obj) {
                return $obj instanceof Cat;
            },
            'fields' => function() {
                return [
                    'name' => ['type' => GraphQlType::string()],
                    'meows' => ['type' => GraphQlType::boolean()],
                ];
            }
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($petType),
                        'resolve' => function () {
                            return [new Dog('Odie', true), new Cat('Garfield', false)];
                        }
                    ]
                ]
            ]),
            'types' => [$catType, $dogType],
            'typeLoader' => function($name) use ($dogType, $petType, $catType) {
                switch ($name) {
                    case 'Dog':
                        return $dogType;
                    case 'Pet':
                        return $petType;
                    case 'Cat':
                        return $catType;
                }
            }
        ]);

        $query = '{
          pets {
            name
            ... on Dog {
              woofs
            }
            ... on Cat {
              meows
            }
          }
        }';

        $expected = new ExecutionResult([
            'pets' => [
                ['name' => 'Odie', 'woofs' => true],
                ['name' => 'Garfield', 'meows' => false]
            ],
        ]);

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        $result = Executor::execute($schema, Parser::parse($query));
        expect($result)->toBePHPEqual($expected);

        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);
        $result = Executor::execute($schema, Parser::parse($query));
        expect(count($result->errors))->toBePHPEqual(1);
        expect($result->errors[0]->getPrevious())->toBeInstanceOf('PHPUnit_Framework_Error_Warning');

        expect($result->errors[0]->getMessage())->toBePHPEqual(
            'GraphQL Interface Type `Pet` returned `null` from it`s `resolveType` function for value: instance of '.
            'GraphQL\Tests\Executor\Dog. Switching to slow resolution method using `isTypeOf` of all possible '.
            'implementations. It requires full schema scan and degrades query performance significantly.  '.
            'Make sure your `resolveType` always returns valid implementation or throws.');
    }

    public function testHintsOnConflictingTypeInstancesInDefinitions():void
    {
        $calls = [];
        /* HH_FIXME[2087]*/
        $typeLoader = function($name) use (&$calls) {
            $calls[] = $name;
            switch ($name) {
                case 'Test':
                    return new ObjectType([
                        'name' => 'Test',
                        'fields' => function() {
                            return [
                                'test' => GraphQlType::string(),
                            ];
                        }
                    ]);
                default:
                    return null;
            }
        };
        $query = new ObjectType([
            'name' => 'Query',
            'fields' => function() use ($typeLoader) {
                return [
                    'test' => $typeLoader('Test')
                ];
            }
        ]);
        $schema = new Schema([
            'query' => $query,
            'typeLoader' => $typeLoader
        ]);

        $query = '
            {
                test {
                    test
                }
            }
        ';

        expect($calls)->toBePHPEqual([]);
        $result = Executor::execute($schema, Parser::parse($query), ['test' => ['test' => 'value']]);
        expect($calls)->toBePHPEqual(['Test', 'Test']);

        expect($result->errors[0]->getMessage())
            ->toBePHPEqual(
            'Schema must contain unique named types but contains multiple types named "Test". '.
            'Make sure that type loader returns the same instance as defined in Query.test '.
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).');

        expect($result->errors[0]->getPrevious())->toBeInstanceOf(InvariantViolation::class);
    }

    public function testSimpleQuery():void
    {
        $schema = new Schema([
            'query' => $this->loadType('Query'),
            'typeLoader' => function($name) {
                return $this->loadType($name, true);
            }
        ]);

        $query = '{ object { string } }';
        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            ['object' => ['string' => 'test']]
        );

        $expected = [
            'data' => ['object' => ['string' => 'test']],
        ];
        $expectedExecutorCalls = [
            'Query.fields',
            'SomeObject',
            'SomeObject.fields'
        ];
        expect($result->toArray(1))->toBePHPEqual($expected);
        expect($this->calls)->toBePHPEqual($expectedExecutorCalls);
    }

    public function testDeepQuery():void
    {
        $schema = new Schema([
            'query' => $this->loadType('Query'),
            'typeLoader' => function($name) {
                return $this->loadType($name, true);
            }
        ]);

        $query = '{ object { object { object { string } } } }';
        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            ['object' => ['object' => ['object' => ['string' => 'test']]]]
        );

        $expected = [
            'data' => ['object' => ['object' => ['object' => ['string' => 'test']]]]
        ];
        $expectedLoadedTypes = [
            'Query' => true,
            'SomeObject' => true,
            'OtherObject' => true
        ];

        expect($result->toArray(1))->toBePHPEqual($expected);
        expect($this->loadedTypes)->toBePHPEqual($expectedLoadedTypes);

        $expectedExecutorCalls = [
            'Query.fields',
            'SomeObject',
            'SomeObject.fields'
        ];
        expect($this->calls)->toBePHPEqual($expectedExecutorCalls);
    }

    public function testResolveUnion():void
    {
        $schema = new Schema([
            'query' => $this->loadType('Query'),
            'typeLoader' => function($name) {
                return $this->loadType($name, true);
            }
        ]);

        $query = '
            {
                other {
                    union {
                        scalar
                    }
                }
            }
        ';
        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            ['other' => ['union' => ['scalar' => 'test']]]
        );

        $expected = [
            'data' => ['other' => ['union' => ['scalar' => 'test']]],
        ];
        $expectedLoadedTypes = [
            'Query' => true,
            'SomeObject' => true,
            'OtherObject' => true,
            'SomeUnion' => true,
            'SomeInterface' => true,
            'DeeperObject' => true,
            'SomeScalar' => true,
        ];

        expect($result->toArray(1))->toBePHPEqual($expected);
        expect($this->loadedTypes)->toBePHPEqual($expectedLoadedTypes);

        $expectedCalls = [
            'Query.fields',
            'OtherObject',
            'OtherObject.fields',
            'SomeUnion',
            'SomeUnion.resolveType',
            'SomeUnion.types',
            'DeeperObject',
            'SomeScalar',
        ];
        expect($this->calls)->toBePHPEqual($expectedCalls);
    }

    public function loadType(string $name, bool $isExecutorCall = false):?GraphQlType
    {
        if ($isExecutorCall)
        {
            $this->calls[] = $name;
        }
        $this->loadedTypes[$name] = true;

        switch ($name) {
            case 'Query':
                return $this->QueryType ?: $this->QueryType = new ObjectType([
                    'name' => 'Query',
                    'fields' => function() {
                        $this->calls[] = 'Query.fields';
                        return [
                            'object' => ['type' => $this->loadType('SomeObject')],
                            'other' => ['type' => $this->loadType('OtherObject')],
                        ];
                    }
                ]);
            case 'SomeObject':
                return $this->SomeObjectType ?: $this->SomeObjectType = new ObjectType([
                    'name' => 'SomeObject',
                    'fields' => function() {
                        $this->calls[] = 'SomeObject.fields';
                        return [
                            'string' => ['type' => GraphQlType::string()],
                            'object' => ['type' => $this->SomeObjectType]
                        ];
                    },
                    'interfaces' => function() {
                        $this->calls[] = 'SomeObject.interfaces';
                        return [
                            $this->loadType('SomeInterface')
                        ];
                    }
                ]);
            case 'OtherObject':
                return $this->OtherObjectType ?: $this->OtherObjectType = new ObjectType([
                    'name' => 'OtherObject',
                    'fields' => function() {
                        $this->calls[] = 'OtherObject.fields';
                        return [
                            'union' => ['type' => $this->loadType('SomeUnion')],
                            'iface' => ['type' => GraphQlType::nonNull($this->loadType('SomeInterface'))],
                        ];
                    }
                ]);
            case 'DeeperObject':
                return $this->DeeperObjectType ?: $this->DeeperObjectType = new ObjectType([
                    'name' => 'DeeperObject',
                    'fields' => function() {
                        return [
                            'scalar' => ['type' => $this->loadType('SomeScalar')],
                        ];
                    }
                ]);
            case 'SomeScalar':
                return $this->SomeScalarType ?: $this->SomeScalarType = new CustomScalarType([
                    'name' => 'SomeScalar',
                    'serialize' => function($value) {return $value;},
                    'parseValue' => function($value) {return $value;},
                    'parseLiteral' => function() {}
                ]);
            case 'SomeUnion':
                return $this->SomeUnionType ?: $this->SomeUnionType = new UnionType([
                    'name' => 'SomeUnion',
                    'resolveType' => function() {
                        $this->calls[] = 'SomeUnion.resolveType';
                        return $this->loadType('DeeperObject');
                    },
                    'types' => function() {
                        $this->calls[] = 'SomeUnion.types';
                        return [ $this->loadType('DeeperObject') ];
                    }
                ]);
            case 'SomeInterface':
                return $this->SomeInterfaceType ?: $this->SomeInterfaceType = new InterfaceType([
                    'name' => 'SomeInterface',
                    'resolveType' => function() {
                        $this->calls[] = 'SomeInterface.resolveType';
                        return $this->loadType('SomeObject');
                    },
                    'fields' => function() {
                        $this->calls[] = 'SomeInterface.fields';
                        return  [
                            'string' => ['type' => GraphQlType::string() ]
                        ];
                    }
                ]);
            default:
                return null;
        }
    }
}
