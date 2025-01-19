<?php

#################################################################
#  Copyright notice
#
#  (c) 2025 El-Virus <elvirus@ilsrv.com>
#  All rights reserved
#
#  http://sabre.io/baikal
#
#  This script is part of the Baïkal Server project. The Baïkal
#  Server project is free software; you can redistribute it
#  and/or modify it under the terms of the GNU General Public
#  License as published by the Free Software Foundation; either
#  version 2 of the License, or (at your option) any later version.
#
#  The GNU General Public License can be found at
#  http://www.gnu.org/copyleft/gpl.html.
#
#  This script is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  This copyright notice MUST APPEAR in all copies of the script!
#################################################################

namespace Baikal\Model\Principal;

use Baikal\Core\LDAP as LDAPCore;
use Baikal\Model\Structs\LDAPConfig;
use Symfony\Component\Yaml\Yaml;

/** @phpstan-consistent-constructor */
class LDAP extends DBPrincipal {
    public const EDITABLE = false;
    public readonly string $dn;

    protected $aData = [
        "uri"         => "",
        "displayname" => "",
        "email"       => "",
    ];

    public function __construct($username, $ldap_config = null) {
        if (!isset($ldap_config)) {
            $config = Yaml::parseFile(PROJECT_PATH_CONFIG . 'baikal.yaml');
            $ldap_config = LDAPConfig::fromArray($config['system']);
            unset($config);
        }

        $conn = ldap_connect($ldap_config->ldap_uri);
        if (!$conn) {
            throw new \Exception('LDAP connect failed');
        }
        if (!ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            throw new \Exception('LDAP server does not support protocol version 3');
        }

        try {
            switch ($ldap_config->ldap_mode) {
                case 'DN':
                    $this->dn = LDAPCore::patternReplace($ldap_config->ldap_dn, $username);
                    break;

                case 'Attribute':
                case 'Group':
                    try {
                        if (!LDAPCore::doesBind($conn, $ldap_config->ldap_bind_dn, $ldap_config->ldap_bind_password)) {
                            throw new \Exception('LDAP Service user fails to bind');
                        }

                        $attribute = $ldap_config->ldap_search_attribute;
                        $attribute = LDAPCore::patternReplace($attribute, $username);

                        $result = ldap_get_entries($conn, ldap_search($conn, $ldap_config->ldap_search_base, '(' . $attribute . ')',
                            [$ldap_config->ldap_search_attribute], 0, 1, 0, LDAP_DEREF_ALWAYS, []))[0];

                        if ((!isset($result)) || (!isset($result['dn']))) {
                            throw new \Exception('No LDAP entry matches Attribute');
                        }

                        if ($ldap_config->ldap_mode == 'Group') {
                            $inGroup = false;
                            $members = ldap_get_entries($conn, ldap_read($conn, $ldap_config->ldap_group, '(objectClass=*)',
                                ['member', 'uniqueMember'], 0, 0, 0, LDAP_DEREF_NEVER, []))[0];
                            if (isset($members['member'])) {
                                foreach ($members['member'] as $member) {
                                    if ($member == $result['dn']) {
                                        $inGroup = true;
                                        break;
                                    }
                                }
                            }
                            if (isset($members['uniqueMember'])) {
                                foreach ($members['uniqueMember'] as $member) {
                                    if ($member == $result['dn']) {
                                        $inGroup = false;
                                        break;
                                    }
                                }
                            }
                            if (!$inGroup) {
                                throw new \Exception('The user is not in the specified Group');
                            }
                        }

                        $this->dn = $result['dn'];
                    } catch (\ErrorException $e) {
                        error_log($e->getMessage());
                        error_log(ldap_error($conn));
                        throw new \Exception('LDAP error');
                    }
                    break;

                case 'Filter':
                    try {
                        if (!LDAPCore::doesBind($conn, $ldap_config->ldap_bind_dn, $ldap_config->ldap_bind_password)) {
                            throw new \Exception('LDAP Service user fails to bind');
                        }

                        $filter = $this->ldap_config->ldap_search_filter;
                        $filter = LDAPCore::patternReplace($filter, $username);

                        $result = ldap_get_entries($conn, ldap_search($conn, $ldap_config->ldap_search_base, $filter, [], 0, 1, 0, LDAP_DEREF_ALWAYS, []))[0];

                        $this->dn = $result['dn'];
                    } catch (\ErrorException $e) {
                        error_log($e->getMessage());
                        error_log(ldap_error($conn));
                        throw new \Exception('LDAP error');
                    }
                    break;

                default:
                    error_log('Unknown LDAP authentication mode');
                    throw new \Exception('Unknown LDAP authentication mode');
            }

            $results = ldap_read($conn, $this->dn, '(objectclass=*)', [$ldap_config->ldap_cn, $ldap_config->ldap_mail]);
            $entry = ldap_get_entries($conn, $results)[0];
            $displayname = $username;
            $email = 'unset-email';
            if (!empty($entry[$ldap_config->ldap_cn])) {
                $displayname = $entry[$ldap_config->ldap_cn][0];
            }
            if (!empty($entry[$ldap_config->ldap_mail])) {
                $email = $entry[$ldap_config->ldap_mail][0];
            }

            parent::set('uri', 'principals/' . $username);
            parent::set('displayname', $displayname);
            parent::set('email', $email);
        } finally {
            ldap_close($conn);
        }
    }

    static function fromPrincipal($db_principal, $username) {
        $principal = new static($username);

        if (isset($db_principal)) {
            $principal->aData[$db_principal->getPrimaryKey()] = $db_principal->getPrimary();
            $principal->bFloating = false;
        }

        $principal->persist();

        return $principal;
    }

    function set($sPropName, $sPropValue) {
        if (!array_key_exists($sPropName, $this->aData)) {
            parent::set($sPropName, $sPropValue);
        }
    }

    function persist() {
        return parent::persist();
    }

    function destroy() {
        return parent::destroy();
    }
}
