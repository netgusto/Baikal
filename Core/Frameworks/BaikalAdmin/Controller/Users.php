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

namespace BaikalAdmin\Controller;

class Users extends \Flake\Core\Controller {
    protected $aMessages = [];

    /**
     * @var \Baikal\Model\User
     */
    private $oModel;

    /**
     * @var \Formal\Form
     */
    private $oForm;

    function execute() {
        if ($this->actionEditRequested()) {
            $this->actionEdit();
        }

        if ($this->actionNewRequested()) {
            $this->actionNew();
        }

        if ($this->actionDeleteRequested()) {
            $this->actionDelete();
        }
    }

    function render() {
        $oView = new \BaikalAdmin\View\Users();

        # List of users
        $aUsers = [];
        $oUsers = \Baikal\Model\User::getBaseRequester()->execute();

        foreach ($oUsers as $user) {
            $aUsers[] = [
                "linkcalendars"    => \BaikalAdmin\Controller\Users::linkCalendars($user),
                "linkaddressbooks" => \BaikalAdmin\Controller\Users::linkAddressBooks($user),
                "linkedit"         => \BaikalAdmin\Controller\Users::linkEdit($user),
                "linkdelete"       => \BaikalAdmin\Controller\Users::linkDelete($user),
                "mailtouri"        => $user->getMailtoURI(),
                "username"         => $user->get("username"),
                "displayname"      => $user->get("displayname"),
                "email"            => $user->get("email"),
                "federation"       => $user->get("federation"),
            ];
        }

        $oView->setData("users", $aUsers);
        $oView->setData("calendaricon", \Baikal\Model\Calendar::icon());
        $oView->setData("usericon", \Baikal\Model\User::icon());
        $oView->setData("davUri", PROJECT_URI . 'dav.php');

        # Messages
        $sMessages = implode("\n", $this->aMessages);
        $oView->setData("messages", $sMessages);

        # Form
        if ($this->actionNewRequested() || $this->actionEditRequested()) {
            $sForm = $this->oForm->render();
        } else {
            $sForm = "";
        }

        $oView->setData("form", $sForm);
        $oView->setData("usericon", \Baikal\Model\User::icon());
        $oView->setData("controller", $this);

        return $oView->render();
    }

    protected function initForm() {
        if ($this->actionEditRequested() || $this->actionNewRequested()) {
            $aOptions = [
                "closeurl" => self::link(),
            ];

            $this->oForm = $this->oModel->formForThisModelInstance($aOptions);
        }
    }

    # Action edit
    protected function actionEditRequested() {
        $aParams = $this->getParams();
        if (array_key_exists("edit", $aParams) && intval($aParams["edit"]) > 0) {
            return true;
        }

        return false;
    }

    protected function actionEdit() {
        $aParams = $this->getParams();
        $this->oModel = new \Baikal\Model\User(intval($aParams["edit"]));
        if ($this->oModel->isEditable()) {
            $this->initForm();

            if ($this->oForm->submitted()) {
                $this->oForm->execute();
            }
        }
    }

    # Action delete

    protected function actionDeleteRequested() {
        $aParams = $this->getParams();
        if (array_key_exists("delete", $aParams) && intval($aParams["delete"]) > 0) {
            return true;
        }

        return false;
    }

    protected function actionDeleteConfirmed() {
        if ($this->actionDeleteRequested() === false) {
            return false;
        }

        $aParams = $this->getParams();

        if (array_key_exists("confirm", $aParams) && intval($aParams["confirm"]) === 1) {
            return true;
        }

        return false;
    }

    protected function actionDelete() {
        $aParams = $this->getParams();
        $iUser = intval($aParams["delete"]);

        if ($this->actionDeleteConfirmed() !== false) {
            # catching Exception thrown when model already destroyed
            # happens when user refreshes delete-page, for instance

            try {
                $oUser = new \Baikal\Model\User($iUser);
                $oUser->destroy();
            } catch (\Exception $e) {
                # user is already deleted; silently discarding
                error_log($e);
            }

            # Redirecting to admin home
            \Flake\Util\Tools::redirectUsingMeta($this->link());
        } else {
            $oUser = new \Baikal\Model\User($iUser);
            $this->aMessages[] = \Formal\Core\Message::warningConfirmMessage(
                "Check twice, you're about to delete " . $oUser->label() . "</strong> from the database !",
                "<p>You are about to delete a user and all it's calendars / contacts. This operation cannot be undone.</p><p>So, now that you know all that, what shall we do ?</p>",
                $this->linkDeleteConfirm($oUser),
                "Delete <strong><i class='" . $oUser->icon() . " icon-white'></i> " . $oUser->label() . "</strong>",
                $this->link()
            );
        }
    }

    # Action new
    protected function actionNewRequested() {
        $aParams = $this->getParams();
        if (array_key_exists("new", $aParams) && intval($aParams["new"]) === 1) {
            return true;
        }

        return false;
    }

    protected function actionNew() {
        $this->oModel = new \Baikal\Model\User();
        $this->initForm();

        if ($this->oForm->submitted()) {
            $this->oForm->execute();

            if ($this->oForm->persisted()) {
                $this->oForm->setOption(
                    "action",
                    $this->linkEdit(
                        $this->oForm->modelInstance()
                    )
                );
            }
        }
    }

    function linkNew() {
        return self::buildRoute([
            "new" => 1,
        ]) . "#form";
    }

    static function linkEdit(\Baikal\Model\User $user) {
        if (!$user->isEditable()) {
            return null;
        }

        return self::buildRoute([
            "edit" => $user->get("id"),
        ]) . "#form";
    }

    static function linkDelete(\Baikal\Model\User $user) {
        return self::buildRoute([
            "delete" => $user->get("id"),
        ]) . "#message";
    }

    static function linkDeleteConfirm(\Baikal\Model\User $user) {
        return self::buildRoute([
            "delete"  => $user->get("id"),
            "confirm" => 1,
        ]) . "#message";
    }

    static function linkCalendars(\Baikal\Model\User $user) {
        return \BaikalAdmin\Controller\User\Calendars::buildRoute([
            "user" => $user->get("id"),
        ]);
    }

    static function linkAddressBooks(\Baikal\Model\User $user) {
        return \BaikalAdmin\Controller\User\AddressBooks::buildRoute([
            "user" => $user->get("id"),
        ]);
    }
}
