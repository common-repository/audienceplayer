<?php

$content = $content ?? '';

if ($AudiencePlayerWordpressPlugin->isAuthenticated() && \is_user_logged_in()) {

    $content .= '<div class="audienceplayer-device-pairing ' . ($args['class'] ?? '') . '">';
    $content .= '<div class="wrapper">';

    /* ### claim device ############################################################################################## */
    if ($args['show_claim_device']) {

        $content .=
            '<div class="claim-device section">' .
            '<div class="wrapper">' .

            '<div class="label">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_pair_device') . '</div>' .

            '<div class="image"></div>' .

            '<div class="description">' . $AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_claim_device_explanation') . '</div>' .

            '<div class="pairing-code">' .
            '<div class="input"><input id="audienceplayer-device-pairing-claim-code-input" /></div>' .
            '<div class="button"><button class="claim-device-button" id="audienceplayer-device-pairing-claim-code-button">' . $AudiencePlayerWordpressPlugin->fetchTranslations('button_activate') . '</button></div>' .
            '</div>' .

            '</div>' .
            '</div>';

        $content .= '<script>jQuery(function($){' .
            '$("#audienceplayer-device-pairing-claim-code-button").click(function(){' .
            'window.AudiencePlayerCore.claimDevicePairingCode(' .
            'this,' .
            '$("#audienceplayer-device-pairing-claim-code-input").val()' .
            ');' .
            '});' .
            '});</script>';
    }


    /* ### user devices ############################################################################################## */
    if ($args['show_user_devices']) {

        $result = $AudiencePlayerWordpressPlugin->api()->query
            ->DeviceList()
            ->execute();

        if ($result->isSuccessful() && ($devices = ($result->getData(true)->items))) {

            $content .=
                '<div class="user-devices section">' .
                '<div class="wrapper">' .
                '<div class="label">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_paired_devices') . '</div>';

            $content .=
                '<table class="table" cellpadding="0" cellspacing="0" border="0">' .
                '<tr class="header">' .
                '<td>' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_name') . '</td>' .
                '<td>' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_id') . '</td>' .
                '<td>' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_date') . '</td>' .
                '<td></td>' .
                '</tr>';

            foreach ($devices as $device) {

                $removeLink = '<button class="remove-paired-device-button" onclick="window.AudiencePlayerCore.removePairedDevice(this,'
                    . $device->id . ',\'' . $AudiencePlayerWordpressPlugin->helper->sanitiseJsString($device->name) . '\');">' .
                    $AudiencePlayerWordpressPlugin->fetchTranslations('link_unpair_device') . '</button>';

                $content .=
                    '<tr class="body">' .
                    '<td class="name">' . $device->name . '</td>' .
                    '<td class="id">' . $device->uuid . '</td>' .
                    '<td class="date">' . $AudiencePlayerWordpressPlugin->parseDateTimeValue($device->created_at) . '</td>' .
                    '<td class="remove">' . $removeLink . '</td>' .
                    '</tr>';
            }

            $content .= '</table>';
            $content .= '</div>';
            $content .= '</div>';
        }
    }

    /* ############################################################################################################### */

    $content .= '</div>';
    $content .= '</div>';

    $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_device_pairing]', $AudiencePlayerWordpressPlugin->api()->fetchLastOperationQuery(true), $AudiencePlayerWordpressPlugin->api()->fetchLastOperationResult());

} else {

    $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_device_pairing]', 'user is not authenticated');
}
