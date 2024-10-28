<?php 
/**
	Admin Page Framework v3.8.23 by Michael Uno 
	Generated by PHP Class Files Script Generator <https://github.com/michaeluno/PHP-Class-Files-Script-Generator>
	<http://en.michaeluno.jp/audienceplayer>
	Copyright (c) 2013-2020, Michael Uno; Licensed under MIT <http://opensource.org/licenses/MIT> */
class AudiencePlayer_AdminPageFramework_Form_View___FieldsetTableRow extends AudiencePlayer_AdminPageFramework_Form_View___Section_Base {
    public $aFieldset = array();
    public $aSavedData = array();
    public $aFieldErrors = array();
    public $aFieldTypeDefinitions = array();
    public $aCallbacks = array();
    public $oMsg;
    public function __construct() {
        $_aParameters = func_get_args() + array($this->aFieldset, $this->aSavedData, $this->aFieldErrors, $this->aFieldTypeDefinitions, $this->aCallbacks, $this->oMsg,);
        $this->aFieldset = $_aParameters[0];
        $this->aSavedData = $_aParameters[1];
        $this->aFieldErrors = $_aParameters[2];
        $this->aFieldTypeDefinitions = $_aParameters[3];
        $this->aCallbacks = $_aParameters[4];
        $this->oMsg = $_aParameters[5];
    }
    public function get() {
        $aFieldset = $this->aFieldset;
        if (!$this->isNormalPlacement($aFieldset)) {
            return '';
        }
        $_oFieldrowAttribute = new AudiencePlayer_AdminPageFramework_Form_View___Attribute_Fieldrow($aFieldset, array('id' => 'fieldrow-' . $aFieldset['tag_id'], 'valign' => 'top', 'class' => 'audienceplayer-fieldrow',));
        return $this->_getFieldByContainer($aFieldset, array('open_container' => "<tr " . $_oFieldrowAttribute->get() . ">", 'close_container' => "</tr>", 'open_title' => "<th>", 'close_title' => "</th>", 'open_main' => "<td " . $this->getAttributes(array('colspan' => $aFieldset['show_title_column'] ? 1 : 2, 'class' => $aFieldset['show_title_column'] ? null : 'audienceplayer-field-td-no-title',)) . ">", 'close_main' => "</td>",));
    }
    protected function _getFieldByContainer(array $aFieldset, array $aOpenCloseTags) {
        $aOpenCloseTags = $aOpenCloseTags + array('open_container' => '', 'close_container' => '', 'open_title' => '', 'close_title' => '', 'open_main' => '', 'close_main' => '',);
        $_oFieldTitle = new AudiencePlayer_AdminPageFramework_Form_View___FieldTitle($aFieldset, '', $this->aSavedData, $this->aFieldErrors, $this->aFieldTypeDefinitions, $this->aCallbacks, $this->oMsg);
        $_aOutput = array();
        if ($aFieldset['show_title_column']) {
            $_aOutput[] = $aOpenCloseTags['open_title'] . $_oFieldTitle->get() . $aOpenCloseTags['close_title'];
        }
        $_aOutput[] = $aOpenCloseTags['open_main'] . $this->getFieldsetOutput($aFieldset) . $aOpenCloseTags['close_main'];
        return $aOpenCloseTags['open_container'] . implode(PHP_EOL, $_aOutput) . $aOpenCloseTags['close_container'];
    }
    }
    class AudiencePlayer_AdminPageFramework_Form_View___FieldsetRow extends AudiencePlayer_AdminPageFramework_Form_View___FieldsetTableRow {
        public $aFieldset = array();
        public $aSavedData = array();
        public $aFieldErrors = array();
        public $aFieldTypeDefinitions = array();
        public $aCallbacks = array();
        public $oMsg;
        public function __construct() {
            $_aParameters = func_get_args() + array($this->aFieldset, $this->aSavedData, $this->aFieldErrors, $this->aFieldTypeDefinitions, $this->aCallbacks, $this->oMsg,);
            $this->aFieldset = $_aParameters[0];
            $this->aSavedData = $_aParameters[1];
            $this->aFieldErrors = $_aParameters[2];
            $this->aFieldTypeDefinitions = $_aParameters[3];
            $this->aCallbacks = $_aParameters[4];
            $this->oMsg = $_aParameters[5];
        }
        public function get() {
            $aFieldset = $this->aFieldset;
            if (!$this->isNormalPlacement($aFieldset)) {
                return '';
            }
            $_oFieldrowAttribute = new AudiencePlayer_AdminPageFramework_Form_View___Attribute_Fieldrow($aFieldset);
            return $this->_getFieldByContainer($aFieldset, array('open_main' => "<div " . $_oFieldrowAttribute->get() . ">", 'close_main' => "</div>",));
        }
    }
    