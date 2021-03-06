<?hh //strict
namespace GraphQL\Error;

use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Utils\Utils;
use Exception;
use Throwable;
use GraphQL\Language\AST\Node;

/**
 * Describes an Error found during the parse, validate, or
 * execute phases of performing a GraphQL operation. In addition to a message
 * and stack trace, it also includes information about the locations in a
 * GraphQL document and/or execution result that correspond to the Error.
 *
 * When the error was caused by an exception thrown in resolver, original exception
 * is available via `getPrevious()`.
 *
 * Also read related docs on [error handling](error-handling.md)
 *
 * Class extends standard PHP `\Exception`, so all standard methods of base `\Exception` class
 * are available in addition to those listed below.
 */

class Error extends Exception implements \JsonSerializable, ClientAware
{
    const CATEGORY_GRAPHQL = 'graphql';
    const CATEGORY_INTERNAL = 'internal';

    /**
     * @var SourceLocation[]
     */
    private ?array<SourceLocation> $locations;

    /**
     * An array describing the JSON-path into the execution response which
     * corresponds to this error. Only included for errors during execution.
     *
     * @var array
     */
    public ?array<mixed> $path;

    /**
     * An array of GraphQL AST Nodes corresponding to this error.
     *
     * @var array
     */
    public ?array<Node> $nodes;

    /**
     * The source GraphQL document corresponding to this error.
     *
     * @var Source|null
     */
    private ?Source $source;

    /**
     * @var array
     */
    private ?array<int> $positions;

    /**
     * @var bool
     */
    private bool $isClientSafe;

    /**
     * @var string
     */
    protected string $category;

    /**
     * Given an arbitrary Error, presumably thrown while attempting to execute a
     * GraphQL operation, produce a new GraphQLError aware of the location in the
     * document responsible for the original Error.
     *
     * @param $error
     * @param array|null $nodes
     * @param array|null $path
     * @return Error
     */
    public static function createLocatedError(Exception $error, ?array<Node> $nodes = null, ?array<mixed> $path = null):Error
    {
        if ($error instanceof self) {
            if ($error->path !== null && $error->nodes !== null)
            {
                return $error;
            } else {
                $nodes = $nodes ?? $error->nodes;
                $path = $path ?? $error->path;
            }
        }

        $source = null;
        $positions = null;
        $originalError = null;

        if ($error instanceof Error) {
            $message = $error->getMessage();
            $originalError = $error;
            $nodes = $error->nodes ?? $nodes;
            $source = $error->getSource();
            $positions = $error->getPositions();

        }
        else if ($error instanceof \Exception)
        {
            $message = $error->getMessage();
            $originalError = $error;
        } else {
            $message = (string) $error;
        }

        return new Error(
            $message ?? 'An unknown error occurred.',
            $nodes,
            $source,
            $positions,
            $path,
            $originalError
        );
    }


    /**
     * @param Error $error
     * @return array
     */
    public static function formatError(Error $error):array<string, mixed>
    {
        return $error->toSerializableArray();
    }

    /**
     * @param string $message
     * @param array|null $nodes
     * @param Source $source
     * @param array|null $positions
     * @param array|null $path
     * @param \Throwable $previous
     */
    public function __construct(
        string $message,
        ?array<Node> $nodes = null,
        ?Source $source = null,
        ?array<int> $positions = null,
        ?array<mixed> $path = null,
        ?Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);

        if ($nodes instanceof \Traversable)
        {
            $nodes = \iterator_to_array($nodes);
        }

        $this->nodes = $nodes;
        $this->source = $source;
        $this->positions = $positions;
        $this->path = $path;

        if ($previous instanceof ClientAware)
        {
            $this->isClientSafe = $previous->isClientSafe();
            $this->category = $previous->getCategory() ?: static::CATEGORY_INTERNAL;
        }
        else if ($previous)
        {
            $this->isClientSafe = false;
            $this->category = static::CATEGORY_INTERNAL;
        }
        else
        {
            $this->isClientSafe = true;
            $this->category = static::CATEGORY_GRAPHQL;
        }
    }

    public function isClientSafe():bool
    {
        return $this->isClientSafe;
    }

    public function getCategory():string
    {
        return $this->category;
    }

    /**
     * @return Source|null
     */
    public function getSource():?Source
    {
        if (null === $this->source &&
            $this->nodes !== null)
        {
            $node = idx($this->nodes, 0);
            if (
                $node !== null &&
                $node->loc !== null)
            {
                $this->source = $node->loc->source;
            }
        }
        return $this->source;
    }

    /**
     * @return array
     */
    public function getPositions():array<int>
    {
        if (null === $this->positions)
        {
            $this->positions = [];
            if ($this->nodes !== null)
            {
                foreach($this->nodes as $node)
                {
                    $p = $node->loc?->start;
                    if($p !== null)
                    {
                        $this->positions[] = $p;
                    }
                }
            }
        }
        return $this->positions;
    }

    /**
     * An array of locations within the source GraphQL document which correspond to this error.
     *
     * Each entry has information about `line` and `column` within source GraphQL document:
     * $location->line;
     * $location->column;
     *
     * Errors during validation often contain multiple locations, for example to
     * point out to field mentioned in multiple fragments. Errors during execution include a
     * single location, the field which produced the error.
     *
     * @api
     * @return SourceLocation[]
     */
    public function getLocations():array<SourceLocation>
    {
        $locations = $this->locations;
        if (null === $locations)
        {
            $positions = $this->getPositions();
            $source = $this->getSource();

            if ($positions && $source)
            {
                $locations = \array_map(function ($pos) use ($source)
                {
                    return $source->getLocation($pos);
                }, $positions);
            }
            else
            {
                $locations = [];
            }
        }
        return $this->locations = $locations;
    }

    /**
     * Returns an array describing the path from the root value to the field which produced this error.
     * Only included for execution errors.
     *
     * @api
     * @return array|null
     */
    public function getPath():?array<mixed>
    {
        return $this->path;
    }

    /**
     * Returns array representation of error suitable for serialization
     *
     * @deprecated Use FormattedError::createFromException() instead
     * @return array
     */
    public function toSerializableArray():array<string, mixed>
    {
        $arr = [
            'message' => $this->getMessage()
        ];

        $locations = \array_map(function(SourceLocation $loc) {
            return $loc->toSerializableArray();
        },$this->getLocations());

        if (\count($locations) > 0)
        {
            $arr['locations'] = $locations;
        }

        if ($this->path !== null)
        {
            $arr['path'] = $this->path;
        }

        return $arr;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize():array<string, mixed>
    {
        return $this->toSerializableArray();
    }
}
