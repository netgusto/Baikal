<?php

#################################################################
#  Copyright notice
#
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

namespace Baikal\Model\Structs;

/**
 * Struct that holds the Configuration parameters for LDAP authentication.
 */
final class LDAPConfig {
    public $ldap_mode;
    public $ldap_uri;
    public $ldap_bind_dn;
    public $ldap_bind_password;
    public $ldap_dn;
    public $ldap_cn;
    public $ldap_mail;
    public $ldap_search_base;
    public $ldap_search_attribute;
    public $ldap_search_filter;
    public $ldap_group;

    public static function fromArray($array) {
        $LDAPConfig = new static();

        $LDAPConfig->ldap_mode              = $array['ldap_mode'];
        $LDAPConfig->ldap_uri               = $array['ldap_uri'];
        $LDAPConfig->ldap_bind_dn           = $array['ldap_bind_dn'];
        $LDAPConfig->ldap_bind_password     = $array['ldap_bind_password'];
        $LDAPConfig->ldap_dn                = $array['ldap_dn'];
        $LDAPConfig->ldap_cn                = $array['ldap_cn'];
        $LDAPConfig->ldap_mail              = $array['ldap_mail'];
        $LDAPConfig->ldap_search_base       = $array['ldap_search_base'];
        $LDAPConfig->ldap_search_attribute  = $array['ldap_search_attribute'];
        $LDAPConfig->ldap_search_filter     = $array['ldap_search_filter'];
        $LDAPConfig->ldap_group             = $array['ldap_group'];

        return $LDAPConfig;
    }
}
