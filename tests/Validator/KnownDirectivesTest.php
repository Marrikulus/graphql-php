<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\KnownDirectives;

class KnownDirectivesTest extends TestCase
{
    // Validate: Known directives

    /**
     * @it with no directives
     */
    public function testWithNoDirectives():void
    {
        $this->expectPassesRule(new KnownDirectives(), '
      query Foo {
        name
        ...Frag
      }

      fragment Frag on Dog {
        name
      }
        ');
    }

    /**
     * @it with known directives
     */
    public function testWithKnownDirectives():void
    {
        $this->expectPassesRule(new KnownDirectives(), '
      {
        dog @include(if: true) {
          name
        }
        human @skip(if: true) {
          name
        }
      }
        ');
    }

    /**
     * @it with unknown directive
     */
    public function testWithUnknownDirective():void
    {
        $this->expectFailsRule(new KnownDirectives(), '
      {
        dog @unknown(directive: "value") {
          name
        }
      }
        ', [
            $this->unknownDirective('unknown', 3, 13)
        ]);
    }

    /**
     * @it with many unknown directives
     */
    public function testWithManyUnknownDirectives():void
    {
        $this->expectFailsRule(new KnownDirectives(), '
      {
        dog @unknown(directive: "value") {
          name
        }
        human @unknown(directive: "value") {
          name
          pets @unknown(directive: "value") {
            name
          }
        }
      }
        ', [
            $this->unknownDirective('unknown', 3, 13),
            $this->unknownDirective('unknown', 6, 15),
            $this->unknownDirective('unknown', 8, 16)
        ]);
    }

    /**
     * @it with well placed directives
     */
    public function testWithWellPlacedDirectives():void
    {
        $this->expectPassesRule(new KnownDirectives(), '
      query Foo {
        name @include(if: true)
        ...Frag @include(if: true)
        skippedField @skip(if: true)
        ...SkippedFrag @skip(if: true)
      }
        ');
    }

    /**
     * @it with misplaced directives
     */
    public function testWithMisplacedDirectives():void
    {
        $this->expectFailsRule(new KnownDirectives(), '
      query Foo @include(if: true) {
        name @operationOnly
        ...Frag @operationOnly
      }
        ', [
            $this->misplacedDirective('include', 'QUERY', 2, 17),
            $this->misplacedDirective('operationOnly', 'FIELD', 3, 14),
            $this->misplacedDirective('operationOnly', 'FRAGMENT_SPREAD', 4, 17),
        ]);
    }

    private function unknownDirective(string $directiveName, int $line, int $column):array<string, mixed>
    {
        return FormattedError::create(
            KnownDirectives::unknownDirectiveMessage($directiveName),
            [ new SourceLocation($line, $column) ]
        );
    }

    public function misplacedDirective(string $directiveName, string $placement, int $line, int $column):array<string, mixed>
    {
        return FormattedError::create(
            KnownDirectives::misplacedDirectiveMessage($directiveName, $placement),
            [new SourceLocation($line, $column)]
        );
    }
}
