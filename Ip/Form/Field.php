<?php
/**
 * @package ImpressPages
 *
 */

namespace Ip\Form;

/**
 * Web page form field
 * @package Ip\Form
 */
abstract class Field{
    //layouts define how field should be treated in the view
    const LAYOUT_DEFAULT = 'default';
    const LAYOUT_BLANK = 'blank';
    
    //types define how field values should be used in controller. Eg. 'system' fields
    //should not be sent by email as form post data. They are just helpers to deliver
    //form to the controller (eg. hidden fields, submit button, captcha).
    const TYPE_REGULAR = 'regular';
    const TYPE_SYSTEM = 'system'; 
    
    protected $label;
    protected $note;
    protected $hint;
    protected $name;
    protected $dbField; //where in db this value should be stored by the method writeToDatabase
    protected $value;
    protected $validators;
    protected $attributes;
    protected $classes; // CSS classes to be added to input field
    protected $environment;
    
    public function __construct($options = array()) {
        $this->validators = array();
        
        if (!empty($options['validators'])) {
            foreach($options['validators'] as $validatorKey => $validator) {
                $this->addValidator($validator);
            }
        }
        
        if (!empty($options['label'])) {
            $this->setLabel($options['label']);
        }
        if (!empty($options['note'])) {
            $this->setNote($options['note']);
        }
        if (!empty($options['hint'])) {
            $this->setHint($options['hint']);
        }
        if (!empty($options['name'])) {
            $this->setName($options['name']);
        }
         if (!empty($options['value'])) {
            $this->setValue($options['value']);
        }
        if (!empty($options['css'])) {
            if (is_array($options['css'])) {
                $this->setCssClasses($options['css']);
            } else {
                $this->classes = array($options['css']);
            }
        } else {
            $this->classes = array();
        }
        if (!empty($options['attributes'])) {
            $this->setAttributes($options['attributes']);
        } else {
            $this->setAttributes(array());
        }
        if (!isset($this->attributes['id'])) {
            $this->addAttribute('id', 'field_'.rand(1, PHP_INT_MAX));
        }
        
        
    }

    /**
     * @param $doctype \Ip\View doctype constant
     * @return string
     */
    public abstract function render($doctype);

    /**
     * Set form environment. Depending on that public or admin translations and layout will be chosen.
     * ImpressPages tries to detect environment automatically based on current controller. You can set manually the right mode if needed.
     * @param $environment
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
    }

    /**
     * Get form environment. Depending on the environment public or admin translations and layout will be chosen.
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @ignore
     * @return string
     */
    public function __toString() {
        return $this->render(ipConfig()->getRaw('DEFAULT_DOCTYPE'));
    }
    
    public function getLayout() {
        return self::LAYOUT_DEFAULT;
    }
    
    public function getType() {
        return self::TYPE_REGULAR;
    }
    
    public function getAttributesStr($doctype) {
        $answer = '';
        foreach ($this->getAttributes() as $attributeKey => $attributeValue) {
            $answer .= ' '.htmlspecialchars($attributeKey).'="'.htmlspecialchars($attributeValue).'"';
        }
        return $answer;
    }
    
    /**
     * @param array $values all posted form values
     * @param string $valueKey this field name
     */
    public function getValueAsString($values, $valueKey) {
        if (isset($values[$valueKey])) {
            return $values[$valueKey];
        } else {
            return '';
        }
    }
    
    
    public function getValidators() {
        return $this->validators;
    }

    /**
     * Check if the field is required
     * @return bool
     */
    public function isRequired() {
        $validators = $this->getValidators();
        foreach($validators as $validator) {
            if (get_class($validator) == 'Ip\Form\Validator\Required') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * Check if field passes validation
     *
     * Validate field
     * @param array $data all data posted. Usually array of string. But some elements could be null or even array (eg. password confirmation field, or multiple file upload field)
     * @param string $valueKey This value key could not exist in values array.
     * @return string string on error or false on success
     */

    /**
     * @param $values
     * @param $valueKey
     * @param $environment \Ip\Form::ENVIRONMENT_ADMIN or \Ip\Form::ENVIRONMENT_PUBLIC
     * @return bool | string
     */
    public function validate($values, $valueKey, $environment) {
        $validators = $this->getValidators();
        foreach($validators as $validator) {
            $error = $validator->getError($values, $valueKey, $environment);
            if ($error) {
                return $error;
            }
        }
        return false;
    }
    
    /**
     * Add a validator to a field.
     *
     * Available validators are located at Ip/Form/Field/Validator folder. \
     * E.g., to add required field validator use addValidator('Required') method.
     *
     * @param string $validator
     */
    public function addValidator($validator) {
        if (!preg_match('/^[a-z0-9]+$/i', $validator)) {
            throw new \Ip\Form\Exception("Unknown validator: '".$validator."'", \Ip\Form\Exception::UNKNOWN_VALIDATOR);
        }
        $validatorClass = '\\Ip\\Form\\Validator\\' . $validator;
        $validator = new $validatorClass;
        
        $this->validators[] = $validator;
        
    }

    /**
     * Remove field validator
     *
     * @param $validator
     */
    public function removeValidator($validator) {
        $validatorClass = 'Modules\\developer\\form\\Validator\\' . $validator;
        $newValidatorsArray = array();
        foreach($this->validators as $validator) {
            if (get_class($validator) != $validatorClass) {
                $newValidatorsArray[] = $validator;
            }
        }
        $this->validators = $newValidatorsArray;
    }

    /**
     * Add custom validator
     * @param Validator\Validator $validator
     */
    public function addCustomValidator(\Ip\Form\Validator\Validator $validator) {
        $this->validators[] = $validator;
    }
    
    /**
     * 
     * Add HTML attribute to input field. Alternative way to setAttributes method.
     *
     * @param string $name Attribute name
     * @param string $value Attribute value
     *
     */
    public function addAttribute($name, $value) {
        $this->attributes[$name] = $value;
    }

    /**
     * Remove HTML attribute
     *
     * @param $name
     */
    public function removeAttribute($name) {
        unset($this->attributes[$name]);
    }

    /**
     * Get validator HTML attributes
     * Needed for JavaScript validator.
     *
     * @param $doctype
     * @return string
     */
    public function getValidationAttributesStr($doctype) {
        $attributesStr = '';
        foreach($this->getValidators() as $validator) {
            $tmpArgs = $validator->validatorAttributes();
            if ($tmpArgs != '') {
                $attributesStr .= ' '.$tmpArgs;
            }
        }
        return $attributesStr;
    }
    
    /**
     * CSS class that should be applied to surrounding element of this field. By default empty. Extending classes should specify their constant value.
     * This field is not used to identify fields by their type. So each extending class should return its own unique and constant string.
     */
    public function getTypeClass() {
        return '';
    }
    
    
    /* GETTERS AND SETTERS  */

    /**
     * Get field label
     *
     * @return string Field label
     */

    public function getLabel() {
        return $this->label;
    }

    /**
     * Set field label
     * @param string $label
     */
    public function setLabel($label) {
        $this->label = $label;
    }

    /**
     * Get field input hint text
     * @return string Hint
     */
    public function getHint() {
        return $this->hint;
    }

    /**
     * Set field input hint text
     * @param string $hint Hint
     */
    public function setHint($hint) {
        $this->hint = $hint;
    }

    /**
     * Get field note text
     * @return string Text note
     */
    public function getNote() {
        return $this->note;
    }

    /**
     * Set field not text
     * @param string $note Note text
     */
    public function setNote($note) {
        $this->note = $note;
    }

    /**
     * Get field name attribute
     * @return string Field name
     */
    public function getName() {
        return $this->name;
    }

    /**
     * If your input has many input fields. Eg. field[id], field[code], ... Return the name of input that should hold error message
     * @return string
     */
    public function getValidationInputName(){
        return $this->name;
    }

    /**
     * Set field name attribute
     * @param string $name Field name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     *
     * @return mixed
     */
    public function getDbField() {
        return $this->dbField;
    }

    /**
     *
     * @param $dbField
     */
    public function setDbField($dbField) {
        $this->dbField = $dbField;
    }
    
    public function getValue() {
        return $this->value;
    }
    
    public function setValue($value) {
        $this->value = $value;
    }
    
    public function getAttribute($attribute) {
        if (isset($this->attributes[$attribute])) {
            return $this->attributes[$attribute];
        } else {
            return false;
        }
    }
    
    public function getAttributes() {
        return $this->attributes;
    }
    
    public function setAttributes($attributes) {
        $this->attributes = $attributes;
    }
    
    public function getId() {
        return $this->getAttribute('id');
    }
    
    
    /**
    *
    * Add CSS class to the input
    * @param string $cssClass
    */
    public function addClass($cssClass) {
        $this->classes[$cssClass] = 1;
    }
    
    public function removeClass($cssClass) {
        unset($this->classes[$cssClass]);
    }

    /**
     * Get a list of HTML classes used
     * @return array
     */
    public function getClasses() {
        return array_keys($this->classes);
    }

    /**
     * Get class attributes as a string
     *
     * @return string
     */
    public function getClassesStr() {
        $answer = '';
        foreach ($this->getClasses() as $class) {
            $answer .= ' '.$class;
        }
        return 'class="'.$answer.'"';
    }    
}