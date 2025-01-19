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

namespace Baikal\Model;

use Symfony\Component\Yaml\Yaml;

class User extends \Flake\Core\Model\Db {
    const DATATABLE = "users";
    const PRIMARYKEY = "id";
    const LABELFIELD = "username";

    protected $aData = [
        "federation" => null,
        "username" => "",
        "digesta1" => "",
    ];

    protected $oIdentityPrincipal;

    function initByPrimary($sPrimary) {
        parent::initByPrimary($sPrimary);

        # Initializing principals
        $dbPrincipal = \Baikal\Model\Principal\DBPrincipal::getBaseRequester()
            ->addClauseEquals("uri", "principals/" . $this->get("username"))
            ->execute()
            ->first();

        switch (parent::get("federation")) {
            case null:
                $this->oIdentityPrincipal = $dbPrincipal;
                break;

            case "LDAP":
                $this->oIdentityPrincipal = \Baikal\Model\Principal\LDAP::fromPrincipal($dbPrincipal, $this->get("username"));
                break;

            default:
                throw new \Exception("Unknown user federation");
        }
    }

    function getAddressBooksBaseRequester() {
        $oBaseRequester = \Baikal\Model\AddressBook::getBaseRequester();
        $oBaseRequester->addClauseEquals(
            "principaluri",
            "principals/" . $this->get("username")
        );

        return $oBaseRequester;
    }

    function getCalendarsBaseRequester() {
        $oBaseRequester = \Baikal\Model\Calendar::getBaseRequester();
        $oBaseRequester->addClauseEquals(
            "principaluri",
            "principals/" . $this->get("username")
        );

        return $oBaseRequester;
    }

    function initFloating() {
        parent::initFloating();

        # Initializing principals
        $this->oIdentityPrincipal = new \Baikal\Model\Principal\DBPrincipal();
    }

    function get($sPropName) {
        if ($sPropName === "password" || $sPropName === "passwordconfirm") {
            # Special handling for password and passwordconfirm
            return "";
        }

        try {
            # does the property exist on the model object ?
            $sRes = parent::get($sPropName);
        } catch (\Exception $e) {
            # no, it may belong to the oIdentityPrincipal model object
            if ($this->oIdentityPrincipal) {
                $sRes = $this->oIdentityPrincipal->get($sPropName);
            } else {
                $sRes = "";
            }
        }

        return $sRes;
    }

    function set($sPropName, $sPropValue) {
        if ($sPropName === "password" || $sPropName === "passwordconfirm") {
            # Special handling for password and passwordconfirm

            if ($sPropName === "password" && $sPropValue !== "") {
                parent::set(
                    "digesta1",
                    $this->getPasswordHashForPassword($sPropValue)
                );
            }

            return $this;
        }

        try {
            # does the property exist on the model object ?
            parent::set($sPropName, $sPropValue);
        } catch (\Exception $e) {
            # no, it may belong to the oIdentityPrincipal model object
            if ($this->oIdentityPrincipal) {
                $this->oIdentityPrincipal->set($sPropName, $sPropValue);
            }
        }

        return $this;
    }

    function persist() {
        $bFloating = $this->floating();

        # Persisted first, as Model users loads this data
        $this->oIdentityPrincipal->set("uri", "principals/" . $this->get("username"));
        $this->oIdentityPrincipal->persist();

        parent::persist();

        if ($bFloating) {
            # Creating default calendar for user
            $oDefaultCalendar = new \Baikal\Model\Calendar();
            $oDefaultCalendar->set(
                "principaluri",
                "principals/" . $this->get("username")
            )->set(
                "displayname",
                "Default calendar"
            )->set(
                "uri",
                "default"
            )->set(
                "description",
                "Default calendar"
            )->set(
                "components",
                "VEVENT,VTODO"
            );

            $oDefaultCalendar->persist();

            # Creating default address book for user
            $oDefaultAddressBook = new \Baikal\Model\AddressBook();
            $oDefaultAddressBook->set(
                "principaluri",
                "principals/" . $this->get("username")
            )->set(
                "displayname",
                "Default Address Book"
            )->set(
                "uri",
                "default"
            )->set(
                "description",
                "Default Address Book for " . $this->get("displayname")
            );

            $oDefaultAddressBook->persist();
        }
    }

    function destroy() {
        # TODO: delete all related resources (principals, calendars, calendar events, contact books and contacts)

        # Destroying identity principal
        if ($this->oIdentityPrincipal != null) {
            $this->oIdentityPrincipal->destroy();
        }

        $oCalendars = $this->getCalendarsBaseRequester()->execute();
        foreach ($oCalendars as $calendar) {
            $calendar->destroy();
        }

        $oAddressBooks = $this->getAddressBooksBaseRequester()->execute();
        foreach ($oAddressBooks as $addressbook) {
            $addressbook->destroy();
        }

        parent::destroy();
    }

    function getMailtoURI() {
        return "mailto:" . rawurlencode($this->get("displayname") . " <" . $this->get("email") . ">");
    }

    function formMorphologyForThisModelInstance() {
        $oMorpho = new \Formal\Form\Morphology();

        $oMorpho->add(new \Formal\Element\Text([
            "prop"       => "username",
            "label"      => "Username",
            "validation" => "required,unique",
            "popover"    => [
                "title"   => "Username",
                "content" => "The login for this user account. It has to be unique.",
            ],
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"       => "displayname",
            "label"      => "Display name",
            "validation" => "required",
            "popover"    => [
                "title"   => "Display name",
                "content" => "This is the name that will be displayed in your CalDAV/CardDAV clients.",
            ],
        ]));

        $oMorpho->add(new \Formal\Element\Text([
            "prop"       => "email",
            "label"      => "Email",
            "validation" => "required,email",
        ]));

        $oMorpho->add(new \Formal\Element\Password([
            "prop"  => "password",
            "label" => "Password",
        ]));

        $oMorpho->add(new \Formal\Element\Password([
            "prop"       => "passwordconfirm",
            "label"      => "Confirm password",
            "validation" => "sameas:password",
        ]));

        if ($this->floating()) {
            $oMorpho->element("username")->setOption("help", "May be an email, but not forcibly.");
            $oMorpho->element("password")->setOption("validation", "required");
        } else {
            $sNotice = "-- Leave empty to keep current password --";
            $oMorpho->element("username")->setOption("readonly", true);

            $oMorpho->element("password")->setOption("popover", [
                "title"   => "Password",
                "content" => "Write something here only if you want to change the user password.",
            ]);

            $oMorpho->element("passwordconfirm")->setOption("popover", [
                "title"   => "Confirm password",
                "content" => "Write something here only if you want to change the user password.",
            ]);

            $oMorpho->element("password")->setOption("placeholder", $sNotice);
            $oMorpho->element("passwordconfirm")->setOption("placeholder", $sNotice);
        }

        return $oMorpho;
    }

    static function icon() {
        return "icon-user";
    }

    static function mediumicon() {
        return "glyph-user";
    }

    static function bigicon() {
        return "glyph2x-user";
    }

    function getPasswordHashForPassword($sPassword) {
        try {
            $config = Yaml::parseFile(PROJECT_PATH_CONFIG . "baikal.yaml");
        } catch (\Exception $e) {
            error_log('Error reading baikal.yaml file : ' . $e->getMessage());
        }

        return md5($this->get("username") . ':' . $config['system']['auth_realm'] . ':' . $sPassword);
    }

    function isEditable() {
        return $this->oIdentityPrincipal::class::EDITABLE;
    }
}
