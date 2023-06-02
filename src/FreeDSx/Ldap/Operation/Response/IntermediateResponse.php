<?php

/**
 * This file is part of the FreeDSx LDAP package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Ldap\Operation\Response;

use FreeDSx\Asn1\Asn1;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\SequenceType;
use FreeDSx\Ldap\Exception\ProtocolException;

/**
 * RFC 4511, 4.13.
 *
 * IntermediateResponse ::= [APPLICATION 25] SEQUENCE {
 *     responseName     [0] LDAPOID OPTIONAL,
 *     responseValue    [1] OCTET STRING OPTIONAL }
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class IntermediateResponse implements ResponseInterface
{
    protected const TAG_NUMBER = 25;

    private ?string $responseName;

    private ?string $responseValue;

    public function __construct(
        ?string $responseName,
        ?string $responseValue,
    ) {
        $this->responseName = $responseName;
        $this->responseValue = $responseValue;
    }

    public function getName(): ?string
    {
        return $this->responseName;
    }

    public function getValue(): ?string
    {
        return $this->responseValue;
    }

    /**
     * {@inheritDoc}
     */
    public static function fromAsn1(AbstractType $type): IntermediateResponse
    {
        if (!$type instanceof SequenceType) {
            throw new ProtocolException('The intermediate response is malformed');
        }

        $name = null;
        $value = null;
        foreach ($type->getChildren() as $child) {
            if ($child->getTagNumber() === 0 && $child->getTagClass() === AbstractType::TAG_CLASS_CONTEXT_SPECIFIC) {
                $name = $child->getValue();
            }
            if ($child->getTagNumber() === 1 && $child->getTagClass() === AbstractType::TAG_CLASS_CONTEXT_SPECIFIC) {
                $value = $child->getValue();
            }
        }

        return new self(
            $name,
            $value
        );
    }

    /**
     * {@inheritdoc}
     */
    public function toAsn1(): AbstractType
    {
        $response = Asn1::sequence();

        if ($this->responseName !== null) {
            $response->addChild(Asn1::context(
                tagNumber: 0,
                type: Asn1::octetString($this->responseName),
            ));
        }
        if ($this->responseValue !== null) {
            $response->addChild(Asn1::context(
                tagNumber: 1,
                type: Asn1::octetString($this->responseValue),
            ));
        }

        return Asn1::application(
            tagNumber: self::TAG_NUMBER,
            type: $response,
        );
    }
}
