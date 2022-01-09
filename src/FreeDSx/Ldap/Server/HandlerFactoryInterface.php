<?php

/**
 * This file is part of the FreeDSx LDAP package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Ldap\Server;

use FreeDSx\Ldap\Server\RequestHandler\RequestHandlerInterface;
use FreeDSx\Ldap\Server\RequestHandler\RootDseHandlerInterface;

/**
 * Responsible for instantiating classes needed by the core server logic.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
interface HandlerFactoryInterface
{
    /**
     * @return RequestHandlerInterface
     */
    public function makeRequestHandler(): RequestHandlerInterface;

    /**
     * @return RootDseHandlerInterface|null
     */
    public function makeRootDseHandler(): ?RootDseHandlerInterface;
}