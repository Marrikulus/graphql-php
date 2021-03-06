<?hh //partial
namespace GraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\NoNull;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\TypeComparators;
use GraphQL\Utils\TypeInfo;
use GraphQL\Utils\Utils;
use namespace HH\Lib\{C};

/**
 * Schema Definition (see [related docs](type-system/schema.md))
 *
 * A Schema is created by supplying the root types of each type of operation:
 * query, mutation (optional) and subscription (optional). A schema definition is
 * then supplied to the validator and executor. Usage Example:
 *
 *     $schema = new GraphQL\Type\Schema([
 *       'query' => $MyAppQueryRootType,
 *       'mutation' => $MyAppMutationRootType,
 *     ]);
 *
 * Or using Schema Config instance:
 *
 *     $config = GraphQL\Type\SchemaConfig::create()
 *         ->setQuery($MyAppQueryRootType)
 *         ->setMutation($MyAppMutationRootType);
 *
 *     $schema = new GraphQL\Type\Schema($config);
 *
 * @package GraphQL
 */
class Schema
{
    /**
     * @var SchemaConfig
     */
    private SchemaConfig $config;

    /**
     * Contains currently resolved schema types
     *
     * @var Type[]
     */
    private array<string, ?GraphQlType> $resolvedTypes = [];

    /**
     * @var array
     */
    private $possibleTypeMap;

    /**
     * True when $resolvedTypes contain all possible schema types
     *
     * @var bool
     */
    private bool $fullyLoaded = false;

    /**
     * Schema constructor.
     *
     * @api
     * @param array|SchemaConfig $config
     */
    public function __construct($config)
    {
        if (is_array($config))
        {
            $config = SchemaConfig::create($config);
        }

        Utils::invariant(
            $config instanceof SchemaConfig,
            'Schema constructor expects instance of GraphQL\Type\SchemaConfig or an array with keys: %s; but got: %s',
            \implode(', ', [
                'query',
                'mutation',
                'subscription',
                'types',
                'directives',
                'typeLoader'
            ]),
            Utils::getVariableType($config)
        );

        Utils::invariant(
            $config->query instanceof ObjectType,
            "Schema query must be Object Type but got: " . Utils::getVariableType($config->query)
        );
        invariant(
            $config->query instanceof ObjectType,
            "Schema query must be Object Type but got: %s",
            Utils::getVariableType($config->query)
        );

        $this->config = $config;
        $this->resolvedTypes[$config->query->name] = $config->query;

        if ($config->mutation !== null)
        {
            $this->resolvedTypes[$config->mutation->name] = $config->mutation;
        }
        if ($config->subscription !== null)
        {
            $this->resolvedTypes[$config->subscription->name] = $config->subscription;
        }
        if (is_array($this->config->types))
        {
            foreach ($this->resolveAdditionalTypes() as $type)
            {
                if (isset($this->resolvedTypes[$type->name]))
                {
                    Utils::invariant(
                        $type === $this->resolvedTypes[$type->name],
                        "Schema must contain unique named types but contains multiple types named \"$type\" ".
                        "(see http://webonyx.github.io/graphql-php/type-system/#type-registry)."
                    );
                }
                $this->resolvedTypes[$type->name] = $type;
            }
        }
        //TODO(ingimar): have to fix IntrospectionTest::testExecutesAnIntrospectionQuery() to use the array_merge. The test is order dependant.
        $this->resolvedTypes += GraphQlType::getInternalTypes() + Introspection::getTypes();


        //$this->resolvedTypes = [
        //    ...$this->resolvedTypes,
        //    ...GraphQlType::getInternalTypes(),
        //    ...Introspection::getTypes(),
        //];
        //$this->resolvedTypes = array_merge(GraphQlType::getInternalTypes(), Introspection::getTypes(), $this->resolvedTypes);

        if (!$this->config->typeLoader) {
            // Perform full scan of the schema
            $this->getTypeMap();
        }
    }

    /**
     * Returns schema query type
     *
     * @api
     * @return ObjectType
     */
    public function getQueryType():ObjectType
    {
        return $this->config->query;
    }

    /**
     * Returns schema mutation type
     *
     * @api
     * @return ObjectType|null
     */
    public function getMutationType():?ObjectType
    {
        return $this->config->mutation;
    }

    /**
     * Returns schema subscription
     *
     * @api
     * @return ObjectType|null
     */
    public function getSubscriptionType():?ObjectType
    {
        return $this->config->subscription;
    }

    /**
     * @api
     * @return SchemaConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Returns array of all types in this schema. Keys of this array represent type names, values are instances
     * of corresponding type definitions
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @api
     * @return GraphQlType[]
     */
    public function getTypeMap():array<string, ?GraphQlType>
    {
        if (!$this->fullyLoaded) {
            $this->resolvedTypes = $this->collectAllTypes();
            $this->fullyLoaded = true;
        }
        return $this->resolvedTypes;
    }

    /**
     * Returns type by it's name
     *
     * @api
     * @param string $name
     * @return GraphQlType
     */
    public function getType(string $name):?GraphQlType
    {
        if (!array_key_exists($name, $this->resolvedTypes))
        {
            $this->resolvedTypes[$name] = $this->loadType($name);
        }
        return $this->resolvedTypes[$name];
    }

    /**
     * @return array
     */
    private function collectAllTypes()
    {
        $typeMap = [];
        foreach ($this->resolvedTypes as $type) {
            $typeMap = TypeInfo::extractTypes($type, $typeMap);
        }
        // When types are set as array they are resolved in constructor
        if (\is_callable($this->config->types)) {
            foreach ($this->resolveAdditionalTypes() as $type) {
                $typeMap = TypeInfo::extractTypes($type, $typeMap);
            }
        }
        return $typeMap;
    }

    /**
     * @return \Generator
     */
    private function resolveAdditionalTypes():\Generator<int, GraphQlType, void>
    {
        $types = $this->config->types ?: [];

        if (\is_callable($types))
        {
            $types = $types();
        }

        if (!is_array($types) && !$types instanceof \Traversable)
        {
            throw new InvariantViolation(\sprintf(
                'Schema types callable must return array or instance of Traversable but got: %s',
                Utils::getVariableType($types)
            ));
        }

        foreach ($types as $index => $type)
        {
            if (!$type instanceof GraphQlType)
            {
                throw new InvariantViolation(\sprintf(
                    'Each entry of schema types must be instance of GraphQL\Type\Definition\GraphQlType but entry at %s is %s',
                    $index,
                    Utils::printSafe($type)
                ));
            }
            yield $type;
        }
    }

    /**
     * Returns all possible concrete types for given abstract type
     * (implementations for interfaces and members of union type for unions)
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @api
     * @param AbstractType $abstractType
     * @return ObjectType[]
     */
    public function getPossibleTypes(AbstractType $abstractType)
    {
        $possibleTypeMap = $this->getPossibleTypeMap();
        return isset($possibleTypeMap[$abstractType->name]) ? \array_values($possibleTypeMap[$abstractType->name]) : [];
    }

    /**
     * @return array
     */
    private function getPossibleTypeMap()
    {
        if ($this->possibleTypeMap === null)
        {
            $this->possibleTypeMap = [];
            foreach ($this->getTypeMap() as $type)
            {
                if ($type instanceof ObjectType)
                {
                    foreach ($type->getInterfaces() as $interface)
                    {
                        $this->possibleTypeMap[$interface->name][$type->name] = $type;
                    }
                }
                else if ($type instanceof UnionType)
                {
                    foreach ($type->getTypes() as $innerType)
                    {
                        $this->possibleTypeMap[$type->name][$innerType->name] = $innerType;
                    }
                }
            }
        }
        return $this->possibleTypeMap;
    }

    /**
     * @param $typeName
     * @return GraphQlType
     */
    private function loadType(string $typeName):?GraphQlType
    {
        $typeLoader = $this->config->typeLoader;

        if (!$typeLoader)
        {
            $type = $this->defaultTypeLoader($typeName);
            return $type;
        }

        $type = $typeLoader($typeName);

        if (!$type instanceof GraphQlType)
        {
            throw new InvariantViolation(
                "Type loader is expected to return valid type \"$typeName\", but it returned " . Utils::printSafe($type)
            );
        }
        if ($type->name !== $typeName)
        {
            throw new InvariantViolation(
                "Type loader is expected to return type \"$typeName\", but it returned \"{$type->name}\""
            );
        }

        return $type;
    }

    /**
     * Returns true if object type is concrete type of given abstract type
     * (implementation for interfaces and members of union type for unions)
     *
     * @api
     * @param AbstractType $abstractType
     * @param ObjectType $possibleType
     * @return bool
     */
    public function isPossibleType(AbstractType $abstractType, ObjectType $possibleType)
    {
        if ($abstractType instanceof InterfaceType)
        {
            return $possibleType->implementsInterface($abstractType);
        }

        invariant(
            $abstractType instanceof UnionType,
            "Expected UnionType got %s",
            Utils::printSafe($abstractType)
        );
        /** @var UnionType $abstractType */
        return $abstractType->isPossibleType($possibleType);
    }

    /**
     * Returns a list of directives supported by this schema
     *
     * @api
     * @return Directive[]
     */
    public function getDirectives():array<Directive>
    {
        return $this->config->directives ?? GraphQL::getStandardDirectives();
    }

    /**
     * Returns instance of directive by name
     *
     * @api
     * @param $name
     * @return Directive
     */
    public function getDirective(string $name):?Directive
    {
        foreach ($this->getDirectives() as $directive)
        {
            if ($directive->name === $name) {
                return $directive;
            }
        }
        return null;
    }

    /**
     * @return SchemaDefinitionNode
     */
    public function getAstNode():?SchemaDefinitionNode
    {
        return $this->config->getAstNode();
    }

    /**
     * @param $typeName
     * @return Type
     */
    private function defaultTypeLoader(string $typeName):?GraphQlType
    {
        // Default type loader simply fallbacks to collecting all types
        $typeMap = $this->getTypeMap();
        return \array_key_exists($typeName, $typeMap) ? $typeMap[$typeName] : null;
    }

    /**
     * Validates schema.
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @api
     * @throws InvariantViolation
     */
    public function assertValid():void
    {
        foreach ($this->config->getDirectives() as $index => $directive)
        {
            Utils::invariant(
                $directive instanceof Directive,
                "Each entry of \"directives\" option of Schema config must be an instance of %s but entry at position %d is %s.",
                Directive::class,
                $index,
                Utils::printSafe($directive)
            );
        }

        $internalTypes = GraphQlType::getInternalTypes() + Introspection::getTypes();

        foreach ($this->getTypeMap() as $name => $type)
        {
            if (\array_key_exists($name, $internalTypes))
            {
                continue;
            }

            if($type === null)
            {
                continue;
            }

            $type->assertValid();

            if ($type instanceof AbstractType)
            {
                $possibleTypes = $this->getPossibleTypes($type);

                Utils::invariant(
                    !empty($possibleTypes),
                    "Could not find possible implementing types for {$type->name} " .
                    'in schema. Check that schema.types is defined and is an array of ' .
                    'all possible types in the schema.'
                );

            } else if ($type instanceof ObjectType)
            {
                foreach ($type->getInterfaces() as $iface) {
                    $this->assertImplementsIntarface($type, $iface);
                }
            }

            // Make sure type loader returns the same instance as registered in other places of schema
            if ($this->config->typeLoader)
            {
                Utils::invariant(
                    $this->loadType($name) === $type,
                    "Type loader returns different instance for {$name} than field/argument definitions. ".
                    'Make sure you always return the same instance for the same type name.'
                );
            }
        }
    }

    private function assertImplementsIntarface(ObjectType $object, InterfaceType $iface):void
    {
        $objectFieldMap = $object->getFields();
        $ifaceFieldMap  = $iface->getFields();

        // Assert each interface field is implemented.
        foreach ($ifaceFieldMap as $fieldName => $ifaceField)
        {

            // Assert interface field exists on object.
            Utils::invariant(
                isset($objectFieldMap[$fieldName]),
                "{$iface->name} expects field \"{$fieldName}\" but {$object->name} does not provide it"
            );

            $objectField = $objectFieldMap[$fieldName];

            // Assert interface field type is satisfied by object field type, by being
            // a valid subtype. (covariant)
            Utils::invariant(
                TypeComparators::isTypeSubTypeOf($this, $objectField->getType(), $ifaceField->getType()),
                "{$iface->name}.{$fieldName} expects type \"{$ifaceField->getType()}\" " .
                "but " .
                "{$object->name}.${fieldName} provides type \"{$objectField->getType()}\""
            );

            // Assert each interface field arg is implemented.
            foreach ($ifaceField->args as $ifaceArg)
            {
                $argName = $ifaceArg->name;

                /** @var FieldArgument $objectArg */
                $objectArg = C\find($objectField->args, function(FieldArgument $arg) use ($argName) {
                    return $arg->name === $argName;
                });

                // Assert interface field arg exists on object field.
                Utils::invariant(
                    $objectArg,
                    "{$iface->name}.{$fieldName} expects argument \"{$argName}\" but ".
                    "{$object->name}.{$fieldName} does not provide it."
                );

                invariant(
                    $objectArg !== null,
                    "%s.%s expects argument \"%s\" but %s.%s does not provide it.",
                    $iface->name,
                    $fieldName,
                    $argName,
                    $object->name,
                    $fieldName
                );

                // Assert interface field arg type matches object field arg type.
                // (invariant)
                Utils::invariant(
                    TypeComparators::isEqualType($ifaceArg->getType(), $objectArg->getType()),
                    "{$iface->name}.{$fieldName}({$argName}:) expects type " .
                    "\"{$ifaceArg->getType()->name}\" but " .
                    "{$object->name}.{$fieldName}({$argName}:) provides type " .
                    "\"{$objectArg->getType()->name}\"."
                );

                // Assert additional arguments must not be required.
                foreach ($objectField->args as $objectArg)
                {
                    $argName = $objectArg->name;
                    $ifaceArg = C\find($ifaceField->args, function(FieldArgument $arg) use ($argName)
                    {
                        return $arg->name === $argName;
                    });
                    if (!$ifaceArg)
                    {
                        Utils::invariant(
                            !($objectArg->getType() instanceof NoNull),
                            "{$object->name}.{$fieldName}({$argName}:) is of required type " .
                            "\"{$objectArg->getType()}\" but is not also provided by the " .
                            "interface {$iface->name}.{$fieldName}."
                        );
                    }
                }
            }
        }
    }
}
