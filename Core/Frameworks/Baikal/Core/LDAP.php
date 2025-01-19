<?php

namespace Baikal\Core;

use Baikal\Model\Principal\LDAP as Principal;

#################################################################
#  Copyright notice
#
#  (c) 2022      Aisha Tammy <aisha@bsd.ac>
#  (c) 2022-2025 El-Virus <elvirus@ilsrv.com>
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

/**
 * This is an authentication backend that uses ldap.
 */
class LDAP extends \Sabre\DAV\Auth\Backend\AbstractBasic {
    /**
     * Reference to PDO connection.
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * PDO table name we'll be using.
     *
     * @var string
     */
    protected $table_name;

    /**
     * LDAP Config.
     * LDAP Config Struct.
     *
     * @var \Baikal\Model\Structs\LDAPConfig
     */
    protected $ldap_config;

    /**
     * Replaces patterns for their assigned value using the
     * given username, using cyrus-sasl style replacements.
     *
     * %u   - gets replaced by full username
     * %U   - gets replaced by user part when the
     *        username is an email address
     * %d   - gets replaced by domain part when the
     *        username is an email address
     * %%   - gets replaced by %
     * %1-9 - gets replaced by parts of the the domain
     *        split by '.' in reverse order
     *
     * full example for jane.doe@mail.example.org:
     *        %u = jane.doe@mail.example.org
     *        %U = jane.doe
     *        %d = mail.example.org
     *        %1 = org
     *        %2 = example
     *        %3 = mail
     *
     * @param string $line
     * @param string $username
     *
     * @return string
     */
    public static function patternReplace($line, $username) {
        $user_split = [$username];
        $user = $username;
        $domain = '';
        try {
            $user_split = explode('@', $username, 2);
            $user = $user_split[0];
            if (2 == count($user_split)) {
                $domain = $user_split[1];
            }
        } catch (\Exception $ignored) {
        }
        $domain_split = [];
        try {
            $domain_split = array_reverse(explode('.', $domain));
        } catch (\Exception $ignored) {
            $domain_split = [];
        }

        $parsed_line = '';
        for ($i = 0; $i < strlen($line); ++$i) {
            if ('%' == $line[$i]) {
                ++$i;
                $next_char = $line[$i];
                if ('u' == $next_char) {
                    $parsed_line .= $username;
                } elseif ('U' == $next_char) {
                    $parsed_line .= $user;
                } elseif ('d' == $next_char) {
                    $parsed_line .= $domain;
                } elseif ('%' == $next_char) {
                    $parsed_line .= '%';
                } else {
                    for ($j = 1; $j <= count($domain_split) && $j <= 9; ++$j) {
                        if ($next_char == '' . $j) {
                            $parsed_line .= $domain_split[$j - 1];
                        }
                    }
                }
            } else {
                $parsed_line .= $line[$i];
            }
        }

        return $parsed_line;
    }

    /**
     * Checks if a user can bind with a password.
     * If an error is produced, it will be logged.
     *
     * @param \LDAP\Connection &$conn
     * @param string $dn
     * @param string $password
     *
     * @return bool
     */
    public static function doesBind(&$conn, $dn, $password) {
        try {
            $bind = ldap_bind($conn, $dn, $password);
            if ($bind) {
                return true;
            }
        } catch (\ErrorException $e) {
            error_log($e->getMessage());
            error_log(ldap_error($conn));
        }

        return false;
    }

    /**
     * Creates the backend object.
     *
     * @param \PD0 $pdo
     * @param string $table_name
     * @param \Baikal\Model\Structs\LDAPConfig $ldap_config
     */
    public function __construct(\PDO $pdo, $table_name, $ldap_config) {
        $this->pdo          = $pdo;
        $this->table_name   = $table_name;
        $this->ldap_config  = $ldap_config;
    }

    /**
     * Connects to an LDAP server and tries to authenticate.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    protected function ldapOpen($username, $password) {
        try {
            $principal = new Principal($username, $this->ldap_config);
        } catch (\Exception $ignored) {
            return false;
        }

        $conn = ldap_connect($this->ldap_config->ldap_uri);
        if (!$conn) {
            return false;
        }
        if (!ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            return false;
        }

        $success = $this->doesBind($conn, $principal->dn, $password);

        ldap_close($conn);

        if ($success) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM ' . $this->table_name . ' WHERE username = ?');
            $stmt->execute([$username]);
            $result = $stmt->fetchAll();

            if (empty($result)) {
                $user = new \Baikal\Model\User();
                $user->set('federation', 'LDAP');
                $user->set('username', $username);
                $user->persist();
            }
        }

        return $success;
    }

    /**
     * Validates a username and password by trying to authenticate against LDAP.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    protected function validateUserPass($username, $password) {
        if (!extension_loaded("ldap")) {
            error_log('PHP LDAP extension not enabled');

            return false;
        }

        return $this->ldapOpen($username, $password);
    }
}
