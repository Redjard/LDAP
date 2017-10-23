<?php
/**
 * This file is part of the phpDS package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpDs\Ldap\Operation\Response;

use PhpDs\Ldap\Operation\LdapResult;

/**
 * RFC 4511 Section 4.8
 *
 * DelResponse ::= [APPLICATION 11] LDAPResult
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class DeleteResponse extends LdapResult
{
    protected $tagNumber = 11;
}