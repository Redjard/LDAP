<?php
/**
 * This file is part of the FreeDSx package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Ldap\Search\Filter;

use FreeDSx\Ldap\Asn1\Asn1;
use FreeDSx\Ldap\Search\Filter\EqualityFilter;
use FreeDSx\Ldap\Search\Filter\FilterInterface;
use PhpSpec\ObjectBehavior;

class EqualityFilterSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('foo', 'bar');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(EqualityFilter::class);
    }

    function it_should_implement_fiter_interface()
    {
        $this->shouldImplement(FilterInterface::class);
    }

    function it_should_get_the_attribute_name()
    {
        $this->getAttribute()->shouldBeEqualTo('foo');
        $this->setAttribute('foobar')->getAttribute()->shouldBeEqualTo('foobar');
    }

    function it_should_get_the_value()
    {
        $this->getValue()->shouldBeEqualTo('bar');
    }

    function it_should_generate_correct_asn1()
    {
        $this->toAsn1()->shouldBeLike(Asn1::context(3, Asn1::sequence(
            Asn1::ldapString('foo'),
            Asn1::octetString('bar')
        )));
    }

    function it_should_be_constructed_from_asn1()
    {
        $this::fromAsn1((new EqualityFilter('foo', 'bar'))->toAsn1())->shouldBeLike(new EqualityFilter('foo', 'bar'));
    }
}