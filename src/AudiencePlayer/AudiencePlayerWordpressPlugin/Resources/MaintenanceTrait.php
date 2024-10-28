<?php
/**
 * Copyright (c) 2020, AudiencePlayer
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @license     Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @author      AudiencePlayer <support@audienceplayer.com>
 * @copyright   AudiencePlayer
 * @link        https://www.audienceplayer.com
 */

namespace AudiencePlayer\AudiencePlayerWordpressPlugin\Resources;

use AudiencePlayer\AudiencePlayerWordpressPlugin\Config\Constants;

trait MaintenanceTrait
{
    private $currentDbMigrationVersion;

    public function checkStatus(bool $isInstallEvent)
    {
        if (version_compare($this->fetchCurrentDbMigrationVersion(), Constants::PLUGIN_DB_MIGRATION_VERSION, '<')) {
            $this->runMigrations();
        }

        if ($isInstallEvent) {
            $this->runConfigMigrations();
        }
    }

    private function runMigrations()
    {
        $migrations = $this->fetchMigrations();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($migrations as $migration) {

            if (version_compare($this->fetchCurrentDbMigrationVersion(), $migration['version'], '<')) {

                // execute migration
                \dbDelta($migration['sql']);

                // update version
                $this->updateCurrentDbMigrationVersion($migration['version']);

            }
        }
    }

    private function fetchMigrations()
    {
        global $wpdb;

        $dbTablePrefix = $wpdb->prefix;
        $dbCollation = $wpdb->get_charset_collate();

        return [
            // initial setup of log table
            [
                'version' => '1.0.0',
                'sql' => 'CREATE TABLE ' . $dbTablePrefix . 'audienceplayer_logs (
                  id bigint(20) NOT NULL AUTO_INCREMENT,
                  log_level varchar(191) NULL DEFAULT NULL,
                  log_name varchar(191) NULL DEFAULT NULL,
                  description mediumtext NULL DEFAULT NULL,
                  properties mediumtext NULL DEFAULT NULL,
                  ip varchar(191) NULL DEFAULT NULL,
                  wordpress_user_id int(10) NULL DEFAULT NULL,
                  audienceplayer_user_id int(10) NULL DEFAULT NULL,
                  project_id int(10) NOT NULL,
                  oauth_client_id int(10) NOT NULL,
                  api_base_url varchar(191) NULL DEFAULT NULL,
                  status smallint(5) NOT NULL DEFAULT 0,
                  created_at datetime DEFAULT "0000-00-00 00:00:00" NOT NULL,
                  PRIMARY KEY  (id)
                ) ' . $dbCollation . ';',
            ],
        ];
    }

    private function fetchCurrentDbMigrationVersion($forceReload = false)
    {
        if ($this->currentDbMigrationVersion && false === $forceReload) {
            return $this->currentDbMigrationVersion;
        } else {
            return $this->currentDbMigrationVersion = \get_site_option(Constants::PLUGIN_DB_MIGRATION_VERSION_OPTION_TAG, '');
        }
    }

    private function updateCurrentDbMigrationVersion($version)
    {
        if ($this->fetchCurrentDbMigrationVersion()) {
            \update_option(Constants::PLUGIN_DB_MIGRATION_VERSION_OPTION_TAG, $version);
        } else {
            \add_option(Constants::PLUGIN_DB_MIGRATION_VERSION_OPTION_TAG, $version);
        }

        $this->fetchCurrentDbMigrationVersion(true);
    }

    private function runConfigMigrations()
    {
        // Upgrading from version < 4.0.0
        if ($result = \get_option(\AudiencePlayer\AudiencePlayerWordpressPlugin\AudiencePlayerWordpressPlugin::class)) {

            if (isset($result['audienceplayer_config_general'])) {

                if (!isset($result['audienceplayer_config_integrations'])) {
                    // migrate general config
                    $result['audienceplayer_config_integrations'] = $result['audienceplayer_config_general'];
                }

                unset($result['audienceplayer_config_general']);

                \update_option(\AudiencePlayer\AudiencePlayerWordpressPlugin\AudiencePlayerWordpressPlugin::class, $result);
            }
        }
    }

}
