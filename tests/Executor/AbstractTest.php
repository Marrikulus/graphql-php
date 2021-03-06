<?hh //strict
//decl
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Executor\ExecutionResult;
use function Facebook\FBExpect\expect;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\UnionType;

class AbstractTest extends \Facebook\HackTest\HackTest
{
    // Execute: Handles execution of abstract types

    /**
     * @it isTypeOf used to resolve runtime type for Interface
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForInterface():void
    {
        // isTypeOf used to resolve runtime type for Interface
        $petType = new InterfaceType([
            'name' => 'Pet',
            'fields' => [
                'name' => ['type' => GraphQlType::string()]
            ]
        ]);

        // Added to interface type when defined
        $dogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => function($obj) { return $obj instanceof Dog; },
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()]
            ]
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf' => function ($obj) {
                return $obj instanceof Cat;
            },
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()],
            ]
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
            'types' => [$catType, $dogType]
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
            ]
        ]);

        $result = Executor::execute($schema, Parser::parse($query));
        expect($result)->toBePHPEqual($expected);
    }

    /**
     * @it isTypeOf used to resolve runtime type for Union
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForUnion():void
    {
        $dogType = new ObjectType([
            'name' => 'Dog',
            'isTypeOf' => function($obj) { return $obj instanceof Dog; },
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()]
            ]
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'isTypeOf' => function ($obj) {
                return $obj instanceof Cat;
            },
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $petType = new UnionType([
            'name' => 'Pet',
            'types' => [$dogType, $catType]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($petType),
                        'resolve' => function() {
                            return [ new Dog('Odie', true), new Cat('Garfield', false) ];
                        }
                    ]
                ]
            ])
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
            ]
        ]);

        expect(Executor::execute($schema, Parser::parse($query)))->toBePHPEqual($expected);
    }

    /**
     * @it resolveType on Interface yields useful error
     */
    public function testResolveTypeOnInterfaceYieldsUsefulError():void
    {
        $DogType = null;
        $CatType = null;
        $HumanType = null;

        $PetType = new InterfaceType([
            'name' => 'Pet',
            'resolveType' => function ($obj) use (&$DogType, &$CatType, &$HumanType) {
                if ($obj instanceof Dog) {
                    return $DogType;
                }
                if ($obj instanceof Cat) {
                    return $CatType;
                }
                if ($obj instanceof Human) {
                    return $HumanType;
                }
                return null;
            },
            'fields' => [
                'name' => ['type' => GraphQlType::string()]
            ]
        ]);

        $HumanType = new ObjectType([
            'name' => 'Human',
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($PetType),
                        'resolve' => function () {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false),
                                new Human('Jon')
                            ];
                        }
                    ]
                ],
            ]),
            'types' => [$DogType, $CatType]
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

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                    null
                ]
            ],
            'errors' => [[
                'debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".',
                'locations' => [['line' => 2, 'column' => 11]],
                'path' => ['pets', 2]
            ]]
        ];
        $actual = GraphQL::executeAndReturnResult($schema, $query)->toArray(1);

        expect($actual)->toInclude($expected);
    }

    /**
     * @it resolveType on Union yields useful error
     */
    public function testResolveTypeOnUnionYieldsUsefulError():void
    {
        $HumanType = new ObjectType([
            'name' => 'Human',
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $PetType = new UnionType([
            'name' => 'Pet',
            'resolveType' => function ($obj) use ($DogType, $CatType, $HumanType) {
                if ($obj instanceof Dog) {
                    return $DogType;
                }
                if ($obj instanceof Cat) {
                    return $CatType;
                }
                if ($obj instanceof Human) {
                    return $HumanType;
                }
            },
            'types' => [$DogType, $CatType]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($PetType),
                        'resolve' => function () {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false),
                                new Human('Jon')
                            ];
                        }
                    ]
                ]
            ])
        ]);

        $query = '{
          pets {
            ... on Dog {
              name
              woofs
            }
            ... on Cat {
              name
              meows
            }
          }
        }';

        $result = GraphQL::executeAndReturnResult($schema, $query)->toArray(1);
        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie',
                        'woofs' => true],
                    ['name' => 'Garfield',
                        'meows' => false],
                    null
                ]
            ],
            'errors' => [[
                'debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".',
                'locations' => [['line' => 2, 'column' => 11]],
                'path' => ['pets', 2]
            ]]
        ];
        expect($result)->toInclude($expected);
    }

    /**
     * @it resolveType allows resolving with type name
     */
    public function testResolveTypeAllowsResolvingWithTypeName():void
    {
        $PetType = new InterfaceType([
            'name' => 'Pet',
            'resolveType' => function($obj) {
                if ($obj instanceof Dog) return 'Dog';
                if ($obj instanceof Cat) return 'Cat';
                return null;
            },
            'fields' => [
                'name' => [ 'type' => GraphQlType::string() ]
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [ $PetType ],
            'fields' => [
                'name' => [ 'type' => GraphQlType::string() ],
                'woofs' => [ 'type' => GraphQlType::boolean() ],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [ $PetType ],
            'fields' => [
                'name' => [ 'type' => GraphQlType::string() ],
                'meows' => [ 'type' => GraphQlType::boolean() ],
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($PetType),
                        'resolve' => function() {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false)
                            ];
                        }
                    ]
                ]
            ]),
            'types' => [ $CatType, $DogType ]
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

        $result = GraphQL::execute($schema, $query);

        expect($result)->toBePHPEqual([
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false]
                ]
            ]
        ]);
    }

    public function testHintsOnConflictingTypeInstancesInResolveType():void
    {
        $createTest = function() use (&$iface) {
            return new ObjectType([
                'name' => 'Test',
                'fields' => [
                    'a' => GraphQlType::string()
                ],
                'interfaces' => function() use ($iface) {
                    return [$iface];
                }
            ]);
        };

        $iface = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'a' => GraphQlType::string()
            ],
            'resolveType' => function() use (&$createTest) {
                return $createTest();
            }
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'node' => $iface,
                'test' => $createTest()
            ]
        ]);

        $schema = new Schema([
            'query' => $query,
        ]);
        $schema->assertValid();

        $query = '
            {
                node {
                    a
                }
            }
        ';

        $result = Executor::execute($schema, Parser::parse($query), ['node' => ['a' => 'value']]);

        expect($result->errors[0]->getMessage())
            ->toBePHPEqual(
            'Schema must contain unique named types but contains multiple types named "Test". '.
            'Make sure that `resolveType` function of abstract type "Node" returns the same type instance '.
            'as referenced anywhere else within the schema '.
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).');
    }
}
