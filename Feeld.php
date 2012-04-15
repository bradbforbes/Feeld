<?php

namespace Feeld;

require_once 'Field.php';
require_once 'gump.class.php';

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

    /**
     * A boolean to track if the client has initiated validation
     * the form yet.
     *
     * @var bool
     */
    private $hasRanValidation = false;

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
     * Returns a JSON data string that can be directly passed to Foorm.js
     * or validate.js to populate its field data with our registered fields.
     *
     * @return string
     */
    public function getValidateJsData() {

        // Create an array of associate arrays, containing
        // only the data pairs that validate.js recognizes.
        $data = array();
        foreach ($this->fields as $field) {
            $data[] = array(
                'name' => $field->getName(),
                'display' => $field->getLabel(),
                'rules' => $field->getValidateJsRules()
            );
        }

        // Encode the multi-dimensional array as JSON and return the string.
        return json_encode($data);
    }

    /**
     * Adds an error to our error log.
     *
     * @param $fieldName
     * @param $rule
     */
    public function addError(Field $field, $rule) {
        $this->errors[] = new FeeldError($field, $rule);
    }

    /**
     *
     */
    public function getErrorsHTML() {
        $html = '<div class="form-errors">';
        $html .= '<ul class="form-errors-list">';
        foreach ($this->getErrorsRaw() as $error) {
            $html .= '<li class="form-errors-list-item">' . $error->getMessage() . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }

    /**
     * Returns our error log as an associate array.
     *
     * The keys are individual field names, the values are the rules broken, ex: 'validate_min_len'
     *
     * @return array|FeeldError[]
     */
    public function getErrorsRaw() {

        // Throw an exception if the client tries to get errors before they
        // told us to validate the form.
        if (!$this->hasRanValidation)
            throw new \Exception('Error log queried before form was validated');

        return $this->errors;
    }

    /**
     * Returns true if we have even one error, false if no errors.
     *
     * @return bool
     */
    public function hasErrors() {
        return (count($this->getErrorsRaw()) > 0);
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
     * Validates all fields, adding broken validation rules to our error log.
     */
    public function validate() {

        // Tell ourselves that the client has initiated validation.
        $this->hasRanValidation = true;

        // Sanitize and filter all of our field data.
        $this->filterFields();

        // Validate each field individually.
        foreach ($this->fields as $field) {
            $fieldError = $field->validate();

            // Add any errors to our error log.
            if ($fieldError) {
                $this->addError($field, $fieldError);
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
    public function pass(array $data) {
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
     * The attributes array is for adding HTML element attributes directly
     * into the printed element, such as 'readonly', 'maxlength', 'rows', or 'cols'.
     * Ex: array('readonly' => 'readonly', 'maxlength' => '140')
     *
     * @param $name
     * @param null $classes
     * @param array|null $attributes
     * @return mixed
     */
    public function getHTML($name, $classes = null, array $attributes = null) {
        foreach ($this->fields as $field) {
            if ($field->getName() == $name) {
                return $field->write($classes, $attributes);
            }
        }
    }

    /**
     * Wraps around getHTML() to save the client from having to
     * explicitly echo.
     *
     * @param $name
     * @param null $classes
     * @param array|null $attributes
     */
    public function write($name, $classes = null, array $attributes = null) {
        echo $this->getHTML($name, $classes, $attributes);
    }

    /**
     * Sanitizes and filters all field values.
     *
     * Called immediately before validating fields.
     *
     * Modifies the field values directly, so nothing is returned.
     */
    private function filterFields() {

        // Initialize an associate array of our field name and value pairs.
        $values = array();

        // Initialize an associate array of our field name and filters pairs.
        $filters = array();

        // Populate the arrays.
        foreach ($this->fields as $field) {
            if ($field->getFilters()) {
                $values[$field->getName()] = $field->getValue();
                $filters[$field->getName()] = $field->getFilters();
            }
        }

        // Load an instance of GUMP to use to sanitize and filter our field values.
        $gump = new \GUMP();

        // Sanitize the field values.
        $values = $gump->sanitize($values);

        // Pass the arrays to GUMP and let it do the heavy-lifting.
        $values = $gump->filter($values, $filters);

        // Set the values of all fields to their filtered values.
        foreach ($values as $name => $value) {
            $this->passValue($name, $value);
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


class FeeldError
{
    /**
     * The offending Field object.
     *
     * @var Field
     */
    var $field;

    /**
     * The specific rule broken, such as 'validate_min_len'
     *
     * @var string
     */
    var $rule;

    /**
     * The parameter attached to the rule.  GUMP strips this,
     * so we need to fetch it manually for when creating error messages.
     *
     * @var string
     */
    var $parameter;

    // TODO: This list may be incomplete or flawed right now.
    // Taken directly from validate.js source.
    static $messages = array(
        'required' => 'The %s field is required.',
        'matches' => 'The %s field does not match the %s field.',
        'valid_email' => 'The %s field must contain a valid email address.',
        'valid_emails' => 'The %s field must contain all valid email addresses.',
        'min_len' => 'The %s field must be at least %s characters in length.',
        'max_len' => 'The %s field must not exceed %s characters in length.',
        'exact_len' => 'The %s field must be exactly %s characters in length.',
        'greater_than' => 'The %s field must contain a number greater than %s.',
        'less_than' => 'The %s field must contain a number less than %s.',
        'alpha' => 'The %s field must only contain alphabetical characters.',
        'alpha_numeric' => 'The %s field must only contain alpha-numeric characters.',
        'alpha_dash' => 'The %s field must only contain alpha-numeric characters, underscores, and dashes.',
        'numeric' => 'The %s field must contain only numbers.',
        'integer' => 'The %s field must contain an integer.',
        'decimal' => 'The %s field must contain a decimal number.',
        'is_natural' => 'The %s field must contain only positive numbers.',
        'is_natural_no_zero' => 'The %s field must contain a number greater than zero.',
        'valid_ip' => 'The %s field must contain a valid IP.',
        'valid_base64' => 'The %s field must contain a base64 string.'
    );

    public function __construct(Field $field, $rule) {

        // The field with an error.
        $this->field = $field;

        // The specific rule that was broken.
        $this->rule = $rule;

        // Determine the parameter for this rule, if it has one.
        $this->parameter = $this->determineRuleParameter();


    }

    /**
     * Returns the error message.
     *
     * Retrieves the message template from static::$messages and injects
     * the field label and parameter.
     *
     * @return string
     */
    public function getMessage() {

        // Remove the 'validate_' prefix that GUMP adds to all broken rules.
        $key = str_replace('validate_', '', $this->rule);

        // Get the message template for this rule.
        $template = self::$messages[$key];

        // Inject our field label, and if this field has a parameter, inject that too.
        $message = preg_replace(array("/(%s)/", "/(%s)/"), array($this->field->getLabel(), $this->parameter), $template, 1);

        // Return the message.
        return $message;
    }

    private function determineRuleParameter() {
        $rules = $this->field->getGumpRules();

        $offendingRule = str_replace('validate_', '', $this->rule);

        $rules = explode('|', $rules);
        foreach ($rules as $rule) {
            if (strpos($rule, $offendingRule) !== false) {
                $rulePieces = explode(',', $rule);
                $parameter = $rulePieces[1];
            }
        }
        $this->parameter = $parameter;
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
