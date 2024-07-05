<?php
/*
 -------------------------------------------------------------------------
 DocuSeal plugin for GLPI
 Copyright (C) 2021 by the DocuSeal Development Team.

 https://github.com/pluginsGLPI/docuseal
 -------------------------------------------------------------------------

 LICENSE

 This file is part of DocuSeal.

 DocuSeal is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 DocuSeal is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with DocuSeal. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

define('PLUGIN_DOCUSEAL_VERSION', '0.0.1');

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_docuseal() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['docuseal'] = true;

   Plugin::registerClass('PluginDocusealConfig', ['addtabon' => 'Config']);
   $PLUGIN_HOOKS['config_page']['docuseal'] = 'front/config.form.php';

   include_once(Plugin::getPhpDir('docuseal')."/inc/config.class.php");

   $plugin = new Plugin();
   if ($plugin->isActivated("datainjection")) {
      $PLUGIN_HOOKS['menu_entry']['docuseal'] = 'front/preference.form.php';
   } elseif ($plugin->isActivated("geststock")) {
      $PLUGIN_HOOKS['menu_entry']['docuseal'] = 'front/preference.form.php';
   }

   $PLUGIN_HOOKS['pre_item_add']['docuseal'] = [
      'TicketValidation' => [
         'PluginDocusealHook',
         'preItemAdd'
      ]
   ];

   $PLUGIN_HOOKS['item_update']['docuseal'] = [
      'TicketValidation' => [
         'PluginDocusealHook',
         'itemUpdate'
      ]
   ];

   $PLUGIN_HOOKS['pre_item_purge']['docuseal'] = [
      'TicketValidation' => [
         'PluginDocusealHook',
         'preItemPurge'
      ]
   ];
}


/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_docuseal() {
   return [
      'name'           => 'DocuSeal',
      'version'        => PLUGIN_DOCUSEAL_VERSION,
      'author'         => '<a href="https://rafaantonio.com.br">Rafael Antonio</a>',
      'license'        => 'GPLv3+',
      'homepage'       => 'https://https://rafaantonio.com.br',
      'requirements'   => [
         'glpi' => [
            'min' => '10.0',
         ]
      ]
   ];
}

/**
 * Check pre-requisites before install
 * OPTIONNAL, but recommanded
 *
 * @return boolean
 */
function plugin_docuseal_check_prerequisites() {

   //Version check is not done by core in GLPI < 9.2 but has to be delegated to core in GLPI >= 9.2.
   $version = preg_replace('/^((\d+\.?)+).*$/', '$1', GLPI_VERSION);
   if (version_compare($version, '9.2', '<')) {
      echo "This plugin requires GLPI >= 9.2";
      return false;
   }
   return true;
}

/**
 * Check configuration process
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 *
 * @return boolean
 */
function plugin_docuseal_check_config($verbose = false) {
   return true;
}

function t_docuseal($str) {
   return __($str, 'docuseal');
}
