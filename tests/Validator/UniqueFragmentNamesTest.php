<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\UniqueFragmentNames;

class UniqueFragmentNamesTest extends TestCase
{
    // Validate: Unique fragment names

    /**
     * @it no fragments
     */
    public function testNoFragments():void
    {
        $this->expectPassesRule(new UniqueFragmentNames(), '
      {
        field
      }
        ');
    }

    /**
     * @it one fragment
     */
    public function testOneFragment():void
    {
        $this->expectPassesRule(new UniqueFragmentNames(), '
      {
        ...fragA
      }

      fragment fragA on Type {
        field
      }
        ');
    }

    /**
     * @it many fragments
     */
    public function testManyFragments():void
    {
        $this->expectPassesRule(new UniqueFragmentNames(), '
      {
        ...fragA
        ...fragB
        ...fragC
      }
      fragment fragA on Type {
        fieldA
      }
      fragment fragB on Type {
        fieldB
      }
      fragment fragC on Type {
        fieldC
      }
        ');
    }

    /**
     * @it inline fragments are always unique
     */
    public function testInlineFragmentsAreAlwaysUnique():void
    {
        $this->expectPassesRule(new UniqueFragmentNames(), '
      {
        ...on Type {
          fieldA
        }
        ...on Type {
          fieldB
        }
      }
        ');
    }

    /**
     * @it fragment and operation named the same
     */
    public function testFragmentAndOperationNamedTheSame():void
    {
        $this->expectPassesRule(new UniqueFragmentNames(), '
      query Foo {
        ...Foo
      }
      fragment Foo on Type {
        field
      }
        ');
    }

    /**
     * @it fragments named the same
     */
    public function testFragmentsNamedTheSame():void
    {
        $this->expectFailsRule(new UniqueFragmentNames(), '
      {
        ...fragA
      }
      fragment fragA on Type {
        fieldA
      }
      fragment fragA on Type {
        fieldB
      }
        ', [
            $this->duplicateFrag('fragA', 5, 16, 8, 16)
        ]);
    }

    /**
     * @it fragments named the same without being referenced
     */
    public function testFragmentsNamedTheSameWithoutBeingReferenced():void
    {
        $this->expectFailsRule(new UniqueFragmentNames(), '
      fragment fragA on Type {
        fieldA
      }
      fragment fragA on Type {
        fieldB
      }
        ', [
            $this->duplicateFrag('fragA', 2, 16, 5, 16)
        ]);
    }

    private function duplicateFrag(string $fragName, int $l1, int $c1, int $l2, int $c2):array<string, mixed>
    {
        return FormattedError::create(
            UniqueFragmentNames::duplicateFragmentNameMessage($fragName),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
