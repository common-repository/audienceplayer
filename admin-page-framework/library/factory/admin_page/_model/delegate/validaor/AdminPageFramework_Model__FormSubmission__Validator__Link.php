<?php 
/**
	Admin Page Framework v3.8.23 by Michael Uno 
	Generated by PHP Class Files Script Generator <https://github.com/michaeluno/PHP-Class-Files-Script-Generator>
	<http://en.michaeluno.jp/audienceplayer>
	Copyright (c) 2013-2020, Michael Uno; Licensed under MIT <http://opensource.org/licenses/MIT> */
class AudiencePlayer_AdminPageFramework_Model__FormSubmission__Validator__Link extends AudiencePlayer_AdminPageFramework_Model__FormSubmission__Validator_Base {
    public $sActionHookPrefix = 'try_validation_before_';
    public $iHookPriority = 30;
    public $iCallbackParameters = 5;
    public function _replyToCallback($aInputs, $aRawInputs, array $aSubmits, $aSubmitInformation, $oFactory) {
        $_sLinkURL = $this->_getPressedSubmitButtonData($aSubmits, 'href');
        if (!$_sLinkURL) {
            return;
        }
        $this->goToURL($_sLinkURL);
    }
    }
    