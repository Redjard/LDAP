<?php

/**
 * This file is part of the FreeDSx LDAP package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Ldap\Operation\Response\SyncInfo;

use FreeDSx\Asn1\Asn1;
use FreeDSx\Ldap\Operation\Response\IntermediateResponse;
use FreeDSx\Ldap\Operation\Response\SyncInfo\SyncNewCookie;
use FreeDSx\Ldap\Protocol\LdapEncoder;
use PhpSpec\ObjectBehavior;

class SyncNewCookieSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('omnomnom');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(SyncNewCookie::class);
    }

    function it_should_get_the_cookie()
    {
        $this->getCookie()->shouldBeEqualTo('omnomnom');
    }

    function it_should_have_the_correct_response_name()
    {
        $this->getName()->shouldBeEqualTo(IntermediateResponse::OID_SYNC_INFO);
    }

    function it_should_be_constructed_from_ASN1()
    {
        $encoder = new LdapEncoder();

        $this::fromAsn1(Asn1::application(25, Asn1::sequence(
            Asn1::context(0, Asn1::octetString(IntermediateResponse::OID_SYNC_INFO)),
            Asn1::context(1, Asn1::octetString($encoder->encode(Asn1::context(0, Asn1::octetString('omnomnom')))))
        )))->shouldBeLike(new SyncNewCookie('omnomnom'));
    }

    function it_should_generate_correct_ASN1()
    {
        $encoder = new LdapEncoder();

        $this->toAsn1()->shouldBeLike(Asn1::application(25, Asn1::sequence(
            Asn1::context(0, Asn1::octetString(IntermediateResponse::OID_SYNC_INFO)),
            Asn1::context(1, Asn1::octetString($encoder->encode(Asn1::context(0, Asn1::octetString('omnomnom')))))
        )));
    }
}
