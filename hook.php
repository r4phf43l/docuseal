<?php
/*
 -------------------------------------------------------------------------
 DocuSeal plugin for GLPI
 Copyright (C) 2024 by the DocuSeal Development Team.

 https://github.com/r4phf43l/docuseal-glpi
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

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_docuseal_install() {
   global $DB;

   if (!$DB->tableExists("glpi_plugin_docuseal_files")) {
      $query = "CREATE TABLE glpi.glpi_plugin_docuseal_files (
                  ticket_id int(11) NOT NULL,
                  request_date timestamp DEFAULT now() NOT NULL,
                  response_date timestamp DEFAULT NULL NULL,
                  user_id int(11) NOT NULL,
                  file_uuid varchar(36) NOT NULL
               )
               ENGINE=InnoDB
               DEFAULT CHARSET=utf8
               COLLATE=utf8_unicode_ci;";
      $DB->queryOrDie($query, $DB->error());
   }

   if (!$DB->tableExists('glpi_plugin_docuseal_configs')) {
      $query = "CREATE TABLE `glpi_plugin_docuseal_configs`(
                  `id` int(11) NOT NULL,
                  `docuseal_url`  VARCHAR(255) NULL,
                  `template_id`  int(11) NULL,
                  `token`  VARCHAR(255) NULL,
                  `default_display_name`  VARCHAR(255) NULL,
                  `default_filename`  VARCHAR(255) NULL,
                  `default_request_comment`  TEXT NULL,
                  `default_accept_comment`  TEXT NULL,
                  `system_user_id` int(11) NOT NULL DEFAULT 0,
                  `date_mod` datetime default NULL,
                  PRIMARY KEY  (`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, 'Error in creating glpi_plugin_docuseal_configs'.
                              "<br>".$DB->error());

      $DB->insertOrDie(
         'glpi_plugin_docuseal_configs', [
            'id' => 1,
            'docuseal_url' => '$DOMAIN/api/submissions/emails',
            'template_id' => 0,
            'token' => null,
            'default_display_name' => 'firstname',
            'default_filename' => t_docuseal('Accept'),
            'default_request_comment' => t_docuseal('Validate GLPI Ticket'),
            'default_accept_comment' => t_docuseal('Digitally signed on DocuSeal'),
            'system_user_id' => 0,
            'date_mod' => null
         ],
         'Error during update glpi_plugin_pdf_configs<br>' . $DB->error()
      );
   }
   return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_docuseal_uninstall() {
   global $DB;

   if ($DB->tableExists("glpi_plugin_docuseal_files")) {
      $query = "DROP TABLE `glpi_plugin_docuseal_files`";
      $DB->query($query) or die("error deleting glpi_plugin_docuseal_files");
   }
   if ($DB->tableExists('glpi_plugin_docuseal_configs')) {
      $query = "DROP TABLE `glpi_plugin_docuseal_configs`";
      $DB->query($query) or die("error deleting glpi_plugin_docuseal_configs");
   }

   return true;
}
