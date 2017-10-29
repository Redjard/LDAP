<?php
/**
 * This file is part of the FreeDSx package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Ldap\Operation\Request;

use FreeDSx\Ldap\Asn1\Asn1;
use FreeDSx\Ldap\Asn1\Encoder\BerEncoder;
use FreeDSx\Ldap\Asn1\Type\AbstractType;
use FreeDSx\Ldap\Asn1\Type\OctetStringType;
use FreeDSx\Ldap\Asn1\Type\SequenceType;
use FreeDSx\Ldap\Exception\ProtocolException;
use FreeDSx\Ldap\Protocol\ProtocolElementInterface;

/**
 * An Extended Request. RFC 4511, 4.12
 *
 * ExtendedRequest ::= [APPLICATION 23] SEQUENCE {
 *     requestName      [0] LDAPOID,
 *     requestValue     [1] OCTET STRING OPTIONAL }
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class ExtendedRequest implements RequestInterface
{
    protected const APP_TAG = 23;

    /**
     * Represents a request to cancel an operation. RFC 3909.
     */
    public const OID_CANCEL = '1.3.6.1.1.8';

    /**
     * Represents a request to issue a StartTLS to encrypt the connection.
     */
    public const OID_START_TLS = '1.3.6.1.4.1.1466.20037';

    /**
     * Represents a "whoami" request. RFC 4532.
     */
    public const OID_WHOAMI = '1.3.6.1.4.1.4203.1.11.3';

    /**
     * Represents a Password Modify Extended Operation. RFC 3062.
     */
    public const OID_PWD_MODIFY = '1.3.6.1.4.1.4203.1.11.1';

    /**
     * @var string
     */
    protected $requestName;

    /**
     * @var null
     */
    protected $requestValue;

    /**
     * @param string $requestName
     * @param null $requestValue
     */
    public function __construct(string $requestName, $requestValue = null)
    {
        $this->requestName = $requestName;
        $this->requestValue = $requestValue;
    }

    /**
     * @param string $requestName
     * @return $this
     */
    public function setName(string $requestName)
    {
        $this->requestName = $requestName;

        return $this;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->requestName;
    }

    /**
     * @param $requestValue
     * @return $this
     */
    public function setValue($requestValue)
    {
        $this->requestValue = $requestValue;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->requestValue;
    }

    /**
     * {@inheritdoc}
     */
    public function toAsn1(): AbstractType
    {
        $asn1 =  Asn1::sequence(Asn1::context(0, Asn1::ldapOid($this->requestName)));

        if ($this->requestValue) {
            $value = $this->requestValue;
            $encoder = new BerEncoder();
            if ($this->requestValue instanceof AbstractType) {
                $value = $encoder->encode($this->requestValue);
            } elseif ($this->requestValue instanceof ProtocolElementInterface) {
                $value = $encoder->encode($this->requestValue->toAsn1());
            }
            $asn1->addChild(Asn1::context(1, Asn1::octetString($value)));
        }

        return Asn1::application(self::APP_TAG, $asn1);
    }

    /**
     * {@inheritdoc}
     */
    public static function fromAsn1(AbstractType $type)
    {
        return new self(...self::parseAsn1ExtendedRequest($type));
    }

    /**
     * @param AbstractType $type
     * @return AbstractType
     * @throws ProtocolException
     */
    protected static function decodeEncodedValue(AbstractType $type) : ?AbstractType
    {
        [1 => $value] = self::parseAsn1ExtendedRequest($type);

        return $value !== null ? (new BerEncoder())->decode($value) : null;
    }

    /**
     * @param AbstractType $type
     * @return array
     * @throws ProtocolException
     */
    protected static function parseAsn1ExtendedRequest(AbstractType $type)
    {
        if (!($type instanceof SequenceType && (count($type) === 1 || count($type) === 2))) {
            throw new ProtocolException('The extended request is malformed 1');
        }
        $oid = null;
        $value = null;

        foreach ($type->getChildren() as $child) {
            if ($child->getTagClass() === AbstractType::TAG_CLASS_CONTEXT_SPECIFIC && $child->getTagNumber() === 0) {
                $oid = $child;
            } elseif ($child->getTagClass() === AbstractType::TAG_CLASS_CONTEXT_SPECIFIC && $child->getTagNumber() === 1) {
                $value = $child;
            }
        }
        if ($oid === null || !$oid instanceof OctetStringType) {
            throw new ProtocolException('The extended request is malformed 2');
        }
        if ($value !== null && !$value instanceof OctetStringType) {
            throw new ProtocolException('The extended request is malformed 3');
        }
        if ($value !== null) {
            $value = $value->getValue();
        }

        return [$oid->getValue(), $value];
    }
}