<?php

#################################################################
#  Copyright notice
#
#  (c) 2013 Jérôme Schneider <mail@jeromeschneider.fr>
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

namespace Baikal\Model\Config;

use Symfony\Component\Yaml\Yaml;

class Standard extends \Baikal\Model\Config {
    # Default values
    protected $aData = [
        "configured_version"     => BAIKAL_VERSION,
        "timezone"               => "Europe/Paris",
        "card_enabled"           => true,
        "cal_enabled"            => true,
        "admin_passwordhash"     => "",
        "dav_auth_type"          => "Digest",
        "ldap_mode"              => "None",
        "ldap_uri"               => "ldap://127.0.0.1",
        "ldap_bind_dn"           => "cn=baikal,ou=apps,dc=example,dc=com",
        "ldap_bind_password"     => "",
        "ldap_dn"                => "mail=%u",
        "ldap_cn"                => "cn",
        "ldap_mail"              => "mail",
        "ldap_search_base"       => "ou=users,dc=example,dc=com",
        "ldap_search_attribute"  => "uid=%U",
        "ldap_search_filter"     => "(objectClass=*)",
        "ldap_group"             => "cn=baikal,ou=groups,dc=example,dc=com",
        "failed_access_message"  => "user %u authentication failure for Baikal",
        // While not editable as will change admin & any existing user passwords,
        // could be set to different value when migrating from legacy config
        "auth_realm"             => "BaikalDAV",
        "base_uri"               => "",
    ];

    function __construct() {
        $this->aData["invite_from"] = "noreply@" . $_SERVER['SERVER_NAME']; // Default value
        parent::__construct("system");
    }

    function formMorphologyForThisModelInstance() {
        $oMorpho = new \Formal\Form\Morphology();

        $oMorpho->add(new \Formal\Element\Listbox([
            "prop"       => "timezone",
            "label"      => "Server Time zone",
            "validation" => "required",
            "options"    => \Baikal\Core\Tools::timezones(),
        ]));

        $oMorpho->add(new \Formal\Element\Checkbox([
            "prop"  => "card_enabled",
            "label" => "Enable CardDAV",
        ]));

        $oMorpho->add(new \Formal\Element\Checkbox([
            "prop"  => "cal_enabled",
            "label" => "Enable CalDAV",
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"  => "invite_from",
            "label" => "Email invite sender address",
            "help"  => "Leave empty to disable sending invite emails",
        ]));

        $oMorpho->add(new \Formal\Element\Password([
            "prop"  => "admin_passwordhash",
            "label" => "Admin password",
        ]));

        $oMorpho->add(new \Formal\Element\Password([
            "prop"       => "admin_passwordhash_confirm",
            "label"      => "Admin password, confirmation",
            "validation" => "sameas:admin_passwordhash",
        ]));

        $oMorpho->add(new \Formal\Element\Listbox([
            "prop"    => "dav_auth_type",
            "label"   => "WebDAV authentication type",
            "options" => ["Digest", "Basic", "Apache", "LDAP"],
            "refreshonchange" => true,
        ]));

        $oMorpho->add(new \Formal\Element\Listbox([
            "prop"    => "ldap_mode",
            "label"   => "LDAP authentication mode",
            "options" => ["DN", "Attribute", "Filter", "Group"],
            "refreshonchange" => true,
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"    => "ldap_uri",
            "label"   => "URI of the LDAP server",
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"    => "ldap_bind_dn",
            "label"   => "DN which Baikal will use to bind to the LDAP server",
        ]));

        $oMorpho->add(new \Formal\Element\Password([
            "prop"    => "ldap_bind_password",
            "label"   => "Password of the bind DN user",
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"    => "ldap_dn",
            "label"   => "User DN for bind",
            "help"    => "Replacments: %u => username, %U => user part, %d => domain part of username, %1-9 parts of the domain in reverse order",
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"    => "ldap_cn",
            "label"   => "LDAP-attribute for displayname",
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"    => "ldap_mail",
            "label"   => "LDAP-attribute for email",
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"    => "ldap_search_base",
            "label"   => "Base of the LDAP search",
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"    => "ldap_search_attribute",
            "label"   => "Attribute to match the user with",
            "help"    => "Replacments: %u => username, %U => user part, %d => domain part of username, %1-9 parts of the domain in reverse order",
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"    => "ldap_search_filter",
            "label"   => "LDAP filter to be applied to the search",
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"    => "ldap_group",
            "label"   => "Group DN that contains the member atribute of the user",
        ]));

        try {
            $config = Yaml::parseFile(PROJECT_PATH_CONFIG . "baikal.yaml");
        } catch (\Exception $e) {
            error_log('Error reading baikal.yaml file : ' . $e->getMessage());
        }

        if (!isset($config['system']["admin_passwordhash"]) || trim($config['system']["admin_passwordhash"]) === "") {
            # No password set (Form is used in install tool), so password is required as it has to be defined
            $oMorpho->element("admin_passwordhash")->setOption("validation", "required");
        } else {
            $sNotice = "-- Leave empty to keep current password --";
            $oMorpho->element("admin_passwordhash")->setOption("placeholder", $sNotice);
            $oMorpho->element("admin_passwordhash_confirm")->setOption("placeholder", $sNotice);
            $oMorpho->element("ldap_bind_password")->setOption("placeholder", "-- Not Shown --");
        }

        return $oMorpho;
    }

    function label() {
        return "Baïkal Settings";
    }

    function set($sProp, $sValue) {
        if ($sProp === "admin_passwordhash" || $sProp === "admin_passwordhash_confirm") {
            # Special handling for password and passwordconfirm

            if ($sProp === "admin_passwordhash" && $sValue !== "") {
                parent::set(
                    "admin_passwordhash",
                    \BaikalAdmin\Core\Auth::hashAdminPassword($sValue, $this->aData["auth_realm"])
                );
            }

            return $this;
        }

        if ($sProp === "ldap_bind_password" && $sValue === "") {
            return;
        }

        if (!isset($sValue)) {
            return;
        }

        parent::set($sProp, $sValue);
    }

    function get($sProp) {
        if ($sProp === "admin_passwordhash" || $sProp === "admin_passwordhash_confirm" || $sProp === "ldap_bind_password") {
            return "";
        }

        return parent::get($sProp);
    }
}
