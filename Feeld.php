<?php

namespace Feeld;

require_once 'Field.php';

/**
 * Manages, validates, and prints fields.
 *
 * Employs instances of Field to validate and print
 * individual Fields.
 */
class Feeld
{
    /**
     * An array of Field Objects to store our registered Fields.
     *
     * @var array|Field[]
     */
    private $fields = array();

    /**
     * An associate array of error messages.
     *
     * The keys are individual field names, the values are the rules broken, ex: 'validate_min_len'
     *
     * @var array
     */
    private $errors = array();

    public function __construct() {}

    /**
     * Registers a single Field to validate and print later.
     *
     * @param string $name A unique identifier string
     * @param string $label A string to print while displaying errors or printing form labels
     * @param string $type A string specifying the type of input field.  Use FeeldTypes
     * @param string $rules A string of rules recognizable by GUMP syntax and ruleset
     * @param string $sanitize A string of sanitization rules recognizable by GUMP synntax and ruleset
     * @param array $options Optional, an associate array of options to be used only by radio series and select menus
     */
    public function register($name, $label, $type, $rules = null, $sanitize = null, $options = null) {

        $type = $this->determineFieldType($type);

        $this->fields[] = new $type($name, $label, $type, $rules, $sanitize, $options);
    }

    /**
     * Registers a set of Fields to validate and print later.
     *
     * Array structure:
     * 'name' => A unique identifier string
     * 'label' => A string to print while displaying errors or printing form labels
     * 'type' => A string specifying the type of input field.  Use FeeldTypes
     * 'rules' => A string of rules recognizable by GUMP
     * 'sanitize' => A string of sanitization rules recognizable by GUMP
     * 'options' => Optional, an associate array of options to be used only by radio series and select menus
     *
     * @param array $fields
     */
    public function registerBulk(array $fields) {

        foreach ($fields as $field) {
            if (!isset($field[3]))
                $field[3] = '';
            if (!isset($field[4]))
                $field[4] = '';
            if (!isset($field[5]))
                $field[5] = '';

            $this->register($field[0], $field[1], $field[2], $field[3], $field[4], $field[5]);
        }
    }

    /**
     * Adds an error to our error log.
     *
     * @param $fieldName
     * @param $rule
     */
    public function addError($fieldName, $rule) {
        $this->errors[$fieldName] = $rule;
    }

    /**
     * Returns our error log as an associate array.
     *
     * The keys are individual field names, the values are the rules broken, ex: 'validate_min_len'
     *
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Validates a single field, directly returning an error message string if error.
     *
     * @param $name
     * @return bool|string
     */
    public function validateField($name) {
        foreach ($this->fields as $field) {
            if ($field->getName() == $name) {
                return $field->validate();
            }
        }
    }

    /**
     * Validates all fields, adding error messages to our error log.
     */
    public function validate() {
        foreach ($this->fields as $field) {
            $fieldError = $field->validate();
            if ($fieldError) {
                $this->addError($field->getName(), $fieldError);
            }
        }
    }

    /**
     * Passes a value to a Field.
     *
     * @param $name
     * @param $value
     */
    public function passValue($name, $value) {
        foreach ($this->fields as $field) {
            if ($field->getName() == $name) {
                $field->setValue($value);
            }
        }
    }

    /**
     * Passes a set of values to their Fields.  This is the ideal method to pass POST in.
     *
     * @param array $data
     */
    public function passValues(array $data) {
        foreach ($this->fields as $field) {
            $field->setValue($data[$field->getName()]);
        }
    }

    /**
     * Returns the HTML markup for a field.
     *
     * This delegates to the Field class, which delegates again to specific
     * Field children using the Template Method design pattern.
     *
     * @param $name
     * @param null $classes
     * @param array|null $attributes
     * @return mixed
     */
    public function write($name, $classes = null, array $attributes = null) {
        foreach ($this->fields as $field) {
            if ($field->getName() == $name) {
                return $field->write($classes, $attributes);
            }
        }
    }

    /**
     * Examines a client-provided field type parameter and determine
     * what recognizable field type they meant.
     *
     * Ideally, the FeeldType class of constants should be used to
     * specify field types.  However, for developers wanting a shorthand
     * solution, we can try to meet them half way.
     *
     * @param $string
     * @return bool|string
     */
    private function determineFieldType($string) {

        // Return the type as-is if found in FeeldTypes.
        if (in_array($string, FeeldTypes::$options))
            return $string;

        // Determine if the client just left the namespaces off.
        if (in_array('\\Feeld\\' . $string, FeeldTypes::$options))
            return '\\Feeld\\' . $string;

        // Try some reasonable alternates the client may be
        // thinking will work.
        switch($string) {
            case 'text':            return FeeldTypes::TEXT;
            case 'text-field':      return FeeldTypes::TEXT;
            case 'text_field':      return FeeldTypes::TEXT;
            case 'password':        return FeeldTypes::PASSWORD;
            case 'password-field':  return FeeldTypes::PASSWORD;
            case 'password_field':  return FeeldTypes::PASSWORD;
            case 'checkbox':        return FeeldTypes::CHECKBOX;
            case 'select':          return FeeldTypes::SELECTMENU;
            case 'select-menu':     return FeeldTypes::SELECTMENU;
            case 'select_menu':     return FeeldTypes::SELECTMENU;
            case 'dropmenu':        return FeeldTypes::SELECTMENU;
            case 'drop-menu':       return FeeldTypes::SELECTMENU;
            case 'drop_menu':       return FeeldTypes::SELECTMENU;
            case 'radio':           return FeeldTypes::RADIOSERIES;
            case 'textarea':        return FeeldTypes::TEXTAREA;
            case 'upload':          return FeeldTypes::FILEUPLOAD;
            case 'file':            return FeeldTypes::FILEUPLOAD;
        }

        return false;
    }
}


/**
 * A class of constants to use when specifying the input type of a field.
 */
class FeeldTypes
{
    const TEXT = '\Feeld\Text';
    const PASSWORD = '\Feeld\Password';
    const CHECKBOX = '\Feeld\Checkbox';
    const SELECTMENU = '\Feeld\SelectMenu';
    const RADIOSERIES = '\Feeld\RadioSeries';
    const TEXTAREA = '\Feeld\Textarea';
    const FILEUPLOAD = '\Feeld\FileUpload';

    /**
     * Used when validating client input.
     *
     * @var array
     */
    static $options = array(
        '\Feeld\Text', '\Feeld\Password', '\Feeld\Checkbox', '\Feeld\SelectMenu', '\Feeld\RadioSeries', '\Feeld\Textarea', '\Feeld\FileUpload'
    );
}
