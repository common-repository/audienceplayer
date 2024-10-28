<?php
global $wpdb;

$logData = $logData ?? [];

$logContent = '';

$logContent .= '<p>This page contains the plugin log files in order to assist during debugging/troubleshooting efforts.</p>';


// CSV Download
$logContent .= '<p>';
$logContent .= '<a href="' . \home_url() . '/wp-admin/admin-ajax.php?action=audienceplayer_admin_download_file&target=audienceplayer_logs" target="_blank"><i class="audienceplayer-fa excel"></i></a>&nbsp;';
$logContent .= '<a href="' . \home_url() . '/wp-admin/admin-ajax.php?action=audienceplayer_admin_download_file&target=audienceplayer_logs" target="_blank">Download as CSV file</a>';
$logContent .= '</p>';


// Table overview
$logContent .= '<table cellspacing="3" cellpadding="3" border="0" class="audienceplayer-admin-table">';

$logContent .= '<tr class="header">';
$logContent .= '<td>Log name</td>';
$logContent .= '<td>Description</td>';
$logContent .= '<td>Date (UTC)</td>';
$logContent .= '</tr>';

foreach ($logData as $item) {
    $logContent .= '<tr class="body">';
    $logContent .= '<td>' . $item->log_name . '</td>';
    $logContent .= '<td>' . $item->description . '</td>';
    $logContent .= '<td>' . $item->created_at . '</td>';
    $logContent .= '</tr>';
}
$logContent .= '</table>';

$content = ($content ?? '') . $logContent;
