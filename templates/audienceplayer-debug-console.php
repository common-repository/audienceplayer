<?php
/*
 * Print debug logs to the browser inspector console
 */

if ($AudiencePlayerWordpressPlugin->isPublicJsConsoleDebug() && ($debugLog = $AudiencePlayerWordpressPlugin->fetchPublicDebugStack())) {

    //echo '<!-- DEBUG CONSOLE --->';
    echo '<script>' . PHP_EOL;

    echo 'jQuery(function($){' . PHP_EOL;
    echo 'console.log("### DEBUG CONSOLE ###");' . PHP_EOL;
    echo 'try {' . PHP_EOL;

    foreach ($debugLog as $entry) {

        $statement = '';

        if ($entry['header'] ?? null) {
            $statement .= 'console.log(\'>>> ' . $AudiencePlayerWordpressPlugin->helper()->sanitiseJsString($entry['header']) . '\');' . PHP_EOL;
        }

        if ($entry['msg'] ?? null) {
            $statement .= 'console.log(\'' . $AudiencePlayerWordpressPlugin->helper()->sanitiseJsString($entry['msg']) . '\');' . PHP_EOL;
        }

        if ($entry['vars'] ?? null) {
            $statement .= 'console.log(' . $AudiencePlayerWordpressPlugin->helper()->sanitiseJsString(json_encode($entry['vars'])) . ');' . PHP_EOL;
        }

        if ($statement) {
            echo $statement;
        }

    }

    echo '} catch (e) { console.log("Debug log error", e);}' . PHP_EOL;
    echo 'console.log("#####################");' . PHP_EOL;
    echo '});' . PHP_EOL;

    echo '</script>';

    $AudiencePlayerWordpressPlugin->clearPublicDebugStack();
}
