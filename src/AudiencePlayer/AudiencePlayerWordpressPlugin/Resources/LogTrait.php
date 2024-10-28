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

trait LogTrait
{
    private $logTable = 'audienceplayer_logs';
    private $logLimit = 10000;

    /**
     * @param int $logLevel
     * @param $logName
     * @param $description
     * @param $properties
     * @param $projectId
     * @return string
     */
    public function writeLog(int $logLevel, $logName, $description, array $properties, $projectId = 0): string
    {
        global $wpdb, $wp_version;

        try {
            $properties = json_encode(array_merge([
                'last_operation_query' => $this->api()->fetchLastOperationQuery(),
                'last_operation_result' => $this->api()->fetchLastOperationResult(),
                'wordpress_version' => $wp_version,
                'plugin_version' => self::fetchPluginVersion(),
            ], $properties));
        } catch (\Exception $e) {
            $properties = '';
        }

        $ret = strval($properties);

        try {

            $wpdb->insert($wpdb->prefix . $this->logTable,
                [
                    'log_level' => $logLevel,
                    'log_name' => $logName,
                    'description' => $description,
                    'properties' => $properties,
                    'wordpress_user_id' => $properties['wordpress_user_id'] ?? null,
                    'audienceplayer_user_id' => $properties['audienceplayer_user_id'] ?? null,
                    'project_id' => $projectId ?: intval($this->loadConfig('project_id')),
                    'oauth_client_id' => intval($this->loadConfig('oauth_client_id')),
                    'api_base_url' => strval($this->loadConfig('api_base_url')),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?: $_SERVER['SERVER_ADDR'],
                    'status' => 1,
                    'created_at' => \current_time('mysql', true),
                ]
            );

        } catch (\Exception $e) {
            //
        }

        return $ret;
    }

    public function fetchLogs(int $limit = 1000)
    {
        global $wpdb;

        $tableName = $wpdb->prefix . $this->logTable;

        return $wpdb->get_results("SELECT * FROM $tableName ORDER BY id DESC LIMIT $limit", OBJECT);
    }

    public function exportLogs($limit = 0)
    {
        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename=' . date('Y-m-d') . '-audienceplayer-log.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $limit = intval($limit ?: $this->logLimit);
        $logData = $this->fetchLogs($limit);
        $handle = fopen('php://output', 'w');

        // write header
        if ($logData[0] ?? null) {
            fputcsv($handle, array_keys((array)$logData[0]));
        }

        // write data
        foreach ($logData as $item) {
            fputcsv($handle, (array)$item);
        }
        fclose($handle);
        exit;
    }

    public function cleanLogs(int $offset = 0)
    {
        global $wpdb;

        $tableName = $wpdb->prefix . $this->logTable;
        $sql =
            'DELETE t1 FROM ' . $tableName . ' AS t1 ' .
            'JOIN ( SELECT id FROM ' . $tableName . ' ORDER BY id DESC LIMIT 1 OFFSET ' . $offset . ') AS selection ON t1.id <= selection.id;';

        return $wpdb->get_results($sql, OBJECT);
    }

}
