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

namespace BaikalAdmin\Controller\Install;

class Initialize extends \Flake\Core\Controller {
    protected $aMessages = [];
    protected $oModel;
    protected $oForm;    # \Formal\Form

    function execute() {
        # Assert that /Specific is writable

        if (!file_exists(PROJECT_PATH_SPECIFIC) || !is_dir(PROJECT_PATH_SPECIFIC) || !is_writable(PROJECT_PATH_SPECIFIC) || !file_exists(PROJECT_PATH_CONFIG) || !is_dir(PROJECT_PATH_CONFIG) || !is_writable(PROJECT_PATH_CONFIG)) {
            $message = "<h1>Error - Insufficient  permissions on the configuration folders</h1><p>";
            $message .= "<p>In order to work properly, Baïkal needs to have write permissions in the <strong>Specific/</strong> and <strong>config/</strong> folder.</p>";

            exit($message);
        }

        $this->createHtaccessFilesIfNeeded();

        $this->oModel = new \Baikal\Model\Config\Standard();

        // If we come from pre-0.7.0, we need to get the values from the config.php and config.system.php files
        if (file_exists(PROJECT_PATH_SPECIFIC . "config.php")) {
            require_once PROJECT_PATH_SPECIFIC . "config.php";
            $this->oModel->set('timezone', PROJECT_TIMEZONE);
            $this->oModel->set('card_enabled', BAIKAL_CARD_ENABLED);
            $this->oModel->set('cal_enabled', BAIKAL_CAL_ENABLED);
            $this->oModel->set('invite_from', defined("BAIKAL_INVITE_FROM") ? BAIKAL_INVITE_FROM : "");
            $this->oModel->set('dav_auth_type', BAIKAL_DAV_AUTH_TYPE);
        }
        if (file_exists(PROJECT_PATH_SPECIFIC . "config.system.php")) {
            require_once PROJECT_PATH_SPECIFIC . "config.system.php";
            $this->oModel->set('auth_realm', BAIKAL_AUTH_REALM);
        }

        $this->oForm = $this->oModel->formForThisModelInstance([
            "close" => false,
            "hook.morphology" => [new \BaikalAdmin\Controller\Settings\Standard(), "morphologyHook"],
        ]);

        if ($this->oForm->submitted()) {
            $this->oForm->execute();

            if ($this->oForm->persisted()) {
                // If we come from pre-0.7.0, we need to remove the INSTALL_DISABLED file so we go to the next step
                if (file_exists(PROJECT_PATH_SPECIFIC . '/INSTALL_DISABLED')) {
                    @unlink(PROJECT_PATH_SPECIFIC . '/INSTALL_DISABLED');
                }
                if (file_exists(PROJECT_PATH_SPECIFIC . "config.php")) {
                    @unlink(PROJECT_PATH_SPECIFIC . "config.php");
                }

                # Creating system config, and initializing BAIKAL_ENCRYPTION_KEY
                $oDatabaseConfig = new \Baikal\Model\Config\Database();
                $oDatabaseConfig->set("encryption_key", md5(microtime() . rand()));

                # Default: PDO::SQLite or PDO::MySQL ?
                $aPDODrivers = \PDO::getAvailableDrivers();
                if (in_array('sqlite', $aPDODrivers)) {    # PDO::MySQL is already asserted in \Baikal\Core\Tools::assertEnvironmentIsOk()
                    $oDatabaseConfig->set("backend", 'sqlite');
                } elseif (in_array('mysql', $aPDODrivers)) {
                    $oDatabaseConfig->set("backend", 'mysql');
                } else {
                    $oDatabaseConfig->set("backend", 'pgsql');
                }

                $oDatabaseConfig->persist();
            }
        }
    }

    function render() {
        $sBigIcon = "glyph2x-magic";
        $sBaikalVersion = BAIKAL_VERSION;

        $oView = new \BaikalAdmin\View\Install\Initialize();
        $oView->setData("baikalversion", BAIKAL_VERSION);

        // If we come from pre-0.7.0 (old config files are still present),
        // we need to tell the installer page to show a warning message.
        $oView->setData("oldConfigSystem", file_exists(PROJECT_PATH_SPECIFIC . "config.system.php"));

        if ($this->oForm->persisted()) {
            $sLink = PROJECT_URI . "admin/install/?/database";
            \Flake\Util\Tools::redirect($sLink);
            exit(0);

        #$sMessage = "<p>Baïkal is now configured. You may <a class='btn btn-success' href='" . PROJECT_URI . "admin/'>Access the Baïkal admin</a></p>";
        #$sForm = "";
        } else {
            $sMessage = "";
            $sForm = $this->oForm->render();
        }

        $oView->setData("message", $sMessage);
        $oView->setData("form", $sForm);

        return $oView->render();
    }

    protected function createHtaccessFilesIfNeeded() {
        $this->copyResourceFile("System/htaccess-documentroot", PROJECT_PATH_DOCUMENTROOT . ".htaccess");
        $this->copyResourceFile("System/htaccess-deny-all", PROJECT_PATH_SPECIFIC . ".htaccess");
        $this->copyResourceFile("System/htaccess-deny-all", PROJECT_PATH_CONFIG . ".htaccess");
    }

    private function copyResourceFile($template, $destination) {
        if (!file_exists($destination)) {
            @copy(PROJECT_PATH_CORERESOURCES . $template, $destination);
        }

        if (!file_exists($destination)) {
            throw new \Exception("Unable to create " . $destination . "; you may try to create it manually by copying " . PROJECT_PATH_CORERESOURCES . $template);
        }
    }
}
