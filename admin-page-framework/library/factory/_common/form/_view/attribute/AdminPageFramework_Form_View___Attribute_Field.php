<?php 
/**
	Admin Page Framework v3.8.23 by Michael Uno 
	Generated by PHP Class Files Script Generator <https://github.com/michaeluno/PHP-Class-Files-Script-Generator>
	<http://en.michaeluno.jp/audienceplayer>
	Copyright (c) 2013-2020, Michael Uno; Licensed under MIT <http://opensource.org/licenses/MIT> */
abstract class AudiencePlayer_AdminPageFramework_Form_View___Attribute_Base extends AudiencePlayer_AdminPageFramework_Form_Utility {
    public $sContext = '';
    public $aArguments = array();
    public $aAttributes = array();
    public function __construct() {
        $_aParameters = func_get_args() + array($this->aArguments, $this->aAttributes,);
        $this->aArguments = $_aParameters[0];
        $this->aAttributes = $_aParameters[1];
    }
    public function get() {
        return $this->getAttributes($this->_getFormattedAttributes());
    }
    protected function _getFormattedAttributes() {
        return $this->aAttributes + $this->_getAttributes();
    }
    protected function _getAttributes() {
        return array();
    }
    }
    abstract class AudiencePlayer_AdminPageFramework_Form_View___Attribute_FieldContainer_Base extends AudiencePlayer_AdminPageFramework_Form_View___Attribute_Base {
        protected function _getFormattedAttributes() {
            $_aAttributes = $this->uniteArrays($this->getElementAsArray($this->aArguments, array('attributes', $this->sContext)), $this->aAttributes + $this->_getAttributes());
            $_aAttributes['class'] = $this->getClassAttribute($this->getElement($_aAttributes, 'class', array()), $this->getElement($this->aArguments, array('class', $this->sContext), array()));
            return $_aAttributes;
        }
    }
    class AudiencePlayer_AdminPageFramework_Form_View___Attribute_Field extends AudiencePlayer_AdminPageFramework_Form_View___Attribute_FieldContainer_Base {
        public $sContext = 'field';
        protected function _getAttributes() {
            $_sFieldTypeSelector = $this->getAOrB($this->aArguments['type'], " audienceplayer-field-{$this->aArguments['type']}", '');
            $_sChildFieldSelector = $this->getAOrB($this->hasFieldDefinitionsInContent($this->aArguments), ' with-child-fields', ' without-child-fields');
            $_sNestedFieldSelector = $this->getAOrB($this->hasNestedFields($this->aArguments), ' with-nested-fields', ' without-nested-fields');
            $_sMixedFieldSelector = $this->getAOrB('inline_mixed' === $this->aArguments['type'], ' with-mixed-fields', ' without-mixed-fields');
            return array('id' => $this->aArguments['_field_container_id'], 'data-type' => $this->aArguments['type'], 'class' => "audienceplayer-field{$_sFieldTypeSelector}{$_sNestedFieldSelector}{$_sMixedFieldSelector}{$_sChildFieldSelector}" . $this->getAOrB($this->aArguments['attributes']['disabled'], ' disabled', '') . $this->getAOrB($this->aArguments['_is_sub_field'], ' audienceplayer-subfield', ''));
        }
    }
    