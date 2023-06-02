<?php

/**
 * This file is part of the FreeDSx LDAP package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Ldap\Protocol\ClientProtocolHandler;

use FreeDSx\Asn1\Exception\EncoderException;
use FreeDSx\Ldap\Control\Control;
use FreeDSx\Ldap\Exception\BindException;
use FreeDSx\Ldap\Exception\ConnectionException;
use FreeDSx\Ldap\Exception\OperationException;
use FreeDSx\Ldap\Exception\ProtocolException;
use FreeDSx\Ldap\Exception\ReferralException;
use FreeDSx\Ldap\Exception\UnsolicitedNotificationException;
use FreeDSx\Ldap\Operation\Request\SaslBindRequest;
use FreeDSx\Ldap\Operation\Response\BindResponse;
use FreeDSx\Ldap\Operation\ResultCode;
use FreeDSx\Ldap\Operations;
use FreeDSx\Ldap\Protocol\LdapMessageResponse;
use FreeDSx\Ldap\Protocol\Queue\ClientQueue;
use FreeDSx\Ldap\Protocol\Queue\MessageWrapper\SaslMessageWrapper;
use FreeDSx\Sasl\Exception\SaslException;
use FreeDSx\Sasl\Mechanism\MechanismInterface;
use FreeDSx\Sasl\Sasl;
use FreeDSx\Sasl\SaslContext;

/**
 * Logic for handling a SASL bind.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class ClientSaslBindHandler implements RequestHandlerInterface
{
    use MessageCreationTrait;

    /**
     * @var Control[]
     */
    private array $controls = [];

    /**
     * @var Sasl
     */
    private Sasl $sasl;

    public function __construct(?Sasl $sasl = null)
    {
        $this->sasl = $sasl ?? new Sasl();
    }

    /**
     * {@@inheritDoc}
     *
     * @throws BindException
     * @throws ProtocolException
     * @throws EncoderException
     * @throws ConnectionException
     * @throws OperationException
     * @throws ReferralException
     * @throws UnsolicitedNotificationException
     * @throws SaslException
     * @throws \FreeDSx\Socket\Exception\ConnectionException
     */
    public function handleRequest(ClientProtocolContext $context): ?LdapMessageResponse
    {
        /** @var SaslBindRequest $request */
        $request = $context->getRequest();
        $this->controls = $context->getControls();

        # If we are selecting a mechanism from the RootDSE, we must check for a downgrade afterwards.
        $detectDowngrade = ($request->getMechanism() === '');
        $mech = $this->selectSaslMech($request, $context);

        $queue = $context->getQueue();
        $message = $context->messageToSend();
        $queue->sendMessage($message);

        $response = $queue->getMessage($message->getMessageId());
        $saslResponse = $response->getResponse();
        if (!$saslResponse instanceof BindResponse) {
            throw new ProtocolException(sprintf(
                'Expected a bind response during a SASL bind. But got: %s',
                get_class($saslResponse)
            ));
        }
        if ($saslResponse->getResultCode() !== ResultCode::SASL_BIND_IN_PROGRESS) {
            return $response;
        }
        $response = $this->processSaslChallenge($request, $queue, $saslResponse, $mech);
        if (
            $detectDowngrade
            && $response->getResponse() instanceof BindResponse
            && $response->getResponse()->getResultCode() === ResultCode::SUCCESS
        ) {
            $this->checkDowngradeAttempt($context);
        }

        return $response;
    }

    /**
     * @throws BindException
     * @throws ProtocolException
     * @throws EncoderException
     * @throws ConnectionException
     * @throws OperationException
     * @throws ReferralException
     * @throws UnsolicitedNotificationException
     * @throws SaslException
     * @throws \FreeDSx\Socket\Exception\ConnectionException
     */
    private function selectSaslMech(
        SaslBindRequest $request,
        ClientProtocolContext $context
    ): MechanismInterface {
        if ($request->getMechanism() !== '') {
            $mech = $this->sasl->get($request->getMechanism());
            $request->setMechanism($mech->getName());

            return $mech;
        }
        $rootDse = $context->getRootDse();
        $availableMechs = $rootDse->get('supportedSaslMechanisms');
        $availableMechs = $availableMechs === null ? [] : $availableMechs->getValues();
        $mech = $this->sasl->select($availableMechs, $request->getOptions());
        $request->setMechanism($mech->getName());

        return $mech;
    }

    /**
     * @throws BindException
     * @throws ProtocolException
     * @throws EncoderException
     * @throws UnsolicitedNotificationException
     * @throws SaslException
     * @throws \FreeDSx\Socket\Exception\ConnectionException
     */
    private function processSaslChallenge(
        SaslBindRequest $request,
        ClientQueue $queue,
        BindResponse $saslResponse,
        MechanismInterface $mech
    ): LdapMessageResponse {
        $challenge = $mech->challenge();

        do {
            $context = $challenge->challenge($saslResponse->getSaslCredentials(), $request->getOptions());
            $saslBind = Operations::bindSasl($request->getOptions(), $request->getMechanism(), $context->getResponse());
            $response = $this->sendRequestGetResponse($saslBind, $queue);
            $saslResponse = $response->getResponse();
            if (!$saslResponse instanceof BindResponse) {
                throw new BindException(sprintf(
                    'Expected a bind response during a SASL bind. But got: %s',
                    get_class($saslResponse)
                ));
            }
        } while (!$this->isChallengeComplete($context, $saslResponse));

        if (!$context->isComplete()) {
            $context = $challenge->challenge($saslResponse->getSaslCredentials(), $request->getOptions());
        }

        if ($saslResponse->getResultCode() === ResultCode::SUCCESS && $context->hasSecurityLayer()) {
            $queue->setMessageWrapper(new SaslMessageWrapper($mech->securityLayer(), $context));
        }

        return $response;
    }

    /**
     * @throws ProtocolException
     * @throws EncoderException
     * @throws UnsolicitedNotificationException
     * @throws \FreeDSx\Socket\Exception\ConnectionException
     */
    private function sendRequestGetResponse(
        SaslBindRequest $request,
        ClientQueue $queue
    ): LdapMessageResponse {
        $messageTo = $this->makeRequest($queue, $request, $this->controls);
        $queue->sendMessage($messageTo);

        return $queue->getMessage($messageTo->getMessageId());
    }

    private function isChallengeComplete(
        SaslContext $context,
        BindResponse $response
    ): bool {
        if ($context->isComplete() || $context->getResponse() === null) {
            return true;
        }

        if ($response->getResultCode() === ResultCode::SUCCESS) {
            return true;
        }

        return $response->getResultCode() !== ResultCode::SASL_BIND_IN_PROGRESS;
    }

    /**
     * @throws BindException
     * @throws ProtocolException
     * @throws EncoderException
     * @throws ConnectionException
     * @throws OperationException
     * @throws ReferralException
     * @throws UnsolicitedNotificationException
     * @throws SaslException
     * @throws \FreeDSx\Socket\Exception\ConnectionException
     */
    private function checkDowngradeAttempt(ClientProtocolContext $context): void
    {
        $priorRootDse = $context->getRootDse();
        $rootDse = $context->getRootDse(reload: true);

        $mechs = $rootDse->get('supportedSaslMechanisms');
        $priorMechs = $priorRootDse->get('supportedSaslMechanisms');
        $priorMechs = $priorMechs !== null ? $priorMechs->getValues() : [];
        $mechs = $mechs !== null ? $mechs->getValues() : [];

        if (count(array_diff($mechs, $priorMechs)) !== 0) {
            throw new BindException(
                'Possible SASL downgrade attack detected. The advertised SASL mechanisms have changed.'
            );
        }
    }
}
