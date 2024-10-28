<?php
global $wpdb;

$logData = $logData ?? [];

$logContent = '<div class="audienceplayer-admin">';

$logContent .= '<p>This page contains the plugin log files in order to assist during debugging/troubleshooting efforts.</p>';

// CSV Download + Log Clear
$logContent .= '<p>';
$logContent .= '&nbsp;&nbsp;&nbsp;<button id="audienceplayer_admin_download_logs_button" class="button button-primary">Download CSV &nbsp; <i class="audienceplayer-fa excel"></i></button>';
$logContent .= '&nbsp;&nbsp;&nbsp;<button id="audienceplayer_admin_clean_logs_button" class="button">Clear logs</button><span class="spinner" />';
$logContent .= '</p>';


// Table overview
$logContent .= '<table cellspacing="3" cellpadding="3" border="0" class="audienceplayer-admin-table">';

$logContent .= '<tr class="header">';
$logContent .= '<td>Log name</td>';
$logContent .= '<td>Description</td>';
$logContent .= '<td>IP</td>';
$logContent .= '<td>Date (UTC)</td>';
$logContent .= '<td>Properties</td>';
$logContent .= '</tr>';

foreach ($logData as $item) {

    $logContent .= '<tr class="body">';
    $logContent .= '<td>' . $item->log_name . '</td>';
    $logContent .= '<td>' . $item->description . '</td>';
    $logContent .= '<td>' . $item->ip . '</td>';
    $logContent .= '<td>' . $item->created_at . '</td>';
    if ($item->properties) {
        $logContent .= '<td><i class="audienceplayer-fa info" onclick="alert(\'' .
            $this->helper->sanitiseJsAttributeText(json_encode(json_decode($item->properties), JSON_PRETTY_PRINT), true)
            . '\');"></i></td>';
    } else {
        $logContent .= '<td></td>';
    }
    $logContent .= '</tr>';
}
$logContent .= '</table>';

$logContent .= '</div>';

$content = ($content ?? '') . $logContent;
