<?php

namespace Feeld;


/**
 * The base class for all Fields.
 *
 * Employs the Template Method design pattern for printing HTML.
 */
class Field
{

    /**
     * The unique name of the field provided when registered.
     *
     * @var string
     */
    protected $name;

    /**
     * The label of the field provided when registered.
     *
     * This is displayed in validation messages, and is printed automatically
     * next to radio buttons.
     *
     * @var string
     */
    protected $label;

    /**
     * The input type of the field provided when registered.
     *
     * @var string
     */
    protected $type;

    /**
     * The pipe-separated list of validation rules provided when registered.
     *
     * This will be translated to GUMP and validate.js dialects then never used.
     *
     * @var string
     */
    protected $rules;

    /**
     * The pipe-separated list of sanitization rules provided when registered.
     *
     * @var string
     */
    protected $filters;

    /**
     * The current value of the field, as passed in by the client.
     *
     * @var string
     */
    protected $value;

    /**
     * An associate array of options to use for select menus and radio series,
     * and passed in when registered.
     *
     * @var array
     */
    protected $options;

    /**
     * The translated rule set for the field to be passed directly to GUMP.
     *
     * @var string
     */
    protected $gumpRules;

    /**
     * The translated rule set for the field to be passed directly to validate.js.
     *
     * @var string
     */
    protected $validateJsRules;

    /**
     * An instance of GUMP to use when validating.
     *
     * @var \GUMP
     */
    protected $gump;

    public function __construct($name, $label, $type, $rules = null, $filters = null, $options = null) {

        $this->name = $name;
        $this->label = $label;
        $this->type = $type;
        $this->rules = $rules;
        $this->filters = $filters;
        $this->options = $options;

        $this->translateRules($rules);

        $this->gump = new \GUMP();
    }

    /**
     * Returns the unique name of the field.
     *
     * @return string
     */
    public function getName() { return $this->name; }
    public function getLabel() { return $this->label; }
    public function getValue() { return $this->value; }
    public function getFilters() { return $this->filters; }
    public function getGumpRules() { return $this->gumpRules; }
    public function getValidateJsRules() { return $this->validateJsRules; }

    /**
     * Sets the value of the field.
     *
     * For text and password input types, this is the direct 'value' attribute.
     * For text areas, this should be the current content of the area.
     * For checkboxes, this should be a true or false value.
     * For select menus, this should be the value of the option to select.
     * For radio series, this should be the name of the radio button to select.
     *
     * @param $value string|array
     */
    public function setValue($value) {
        if (isset($value)) {
            $this->value = $value;
        }
    }

    /**
     * Returns the HTML for an input field.
     *
     * Employs the Template Method design pattern.  Each child has
     * a doWrite() method we call after processing the attributes.
     *
     * @param string|null $classes
     * @param array|null $attributes
     * @return string
     */
    public function write($classes = null, array $attributes = null) {

        // Initialize a string to add all HTML attributes to.
        // We'll pass this to the child, and they can paste
        // it in where needed.
        $atts = '';

        $atts .= 'id="' . $this->getName() . '" name="' . $this->getName() . '" ';

        // Copy in classes string if provided.
        if ($classes && is_string($classes))
            $atts .= 'class="' . $classes . '" ';

        // Copy in all, if any, provided attributes.
        if (is_array($attributes) && !empty($attributes)) {
            foreach ($attributes as $name => $value) {
                $atts .= $name . '="' . $value . '" ';
            }

            // Remove trailing space, just to be tidy.
            $atts = substr($atts, 0, -1);
        }

        // Delegate to the children, passing them
        // the atts string they can use as needed.
        return $this->doWrite($atts);
    }

    /**
     * Validate this Field using its value and rules.
     *
     * Delegates process entirely to GUMP.
     *
     * GUMP is sructured to validate many fields at once.
     * For our uses here, however, we only validate one field
     * at a time.  Therefore, if errors are returned, we know
     * that it is a one-key array and go ahead and extract
     * the string from the array and just return the single string.
     *
     * @return string|bool
     */
    public function validate() {
        $errors = $this->gump->validate(array($this->value), array($this->gumpRules));

        // TODO: Handle custom validations, such as 'matches' and 'valid_emails' validate.js support.

        if (is_array($errors)) {
            return $errors[0]['rule'];
        } else {
            return true;
        }
    }

    /**
     * Translates a pipe-separate string of rules into separate
     * GUMP and validate.js ruleset strings.
     *
     * Each individual rule can actually be either part of the
     * GUMP or validate.js rulesets, and we'll handle any differences.
     *
     * Also, any GUMP rule that doesn't exist in validate.js or vice versa
     * will be manually conducted by us, or by Foorm.js.
     *
     * @param $string The pipe-separated string of rules
     */
    private function translateRules($string) {

        // Do not translate empty rule strings.
        if (empty($string)) {
            $this->gumpRules = false;
            $this->validateJsRules = false;
            return;
        }

        // Translate each rule individually thoroughly,
        // attempting to find a recognizable match in either
        // the GUMP ruleset or validate.js ruleset.
        $rules = explode('|', $string);
        foreach ($rules as $rule) {
            $this->translateRule($rule);
        }

        // Remove the trailing pipes from our rulesets.
        $this->gumpRules = substr($this->gumpRules, 0, -1);
        $this->validateJsRules = substr($this->validateJsRules, 0, -1);
    }

    /**
     * Validates an individual rule, as provided by translateRules().
     *
     * @param $string
     * @return bool
     * @throws \Exception
     */
    private function translateRule($string) {

        // Translate 'required' rule.
        // No work needed here, actually.
        if ($string == 'required') {
            $this->addGumpRule('required');
            $this->addValidateJsRule('required');
            return true;
        }

        // TODO: This isn't right.
        // Translate 'matches' rule.
        // GUMP doesn't natively support a 'matches' rule.
        // We can handle this ourselves when we're validating.
        if (substr($string, 0, 6) === 'matches') {
            if (preg_match("/matches,\d+/", $string)) {

            }
            else if (preg_match("/matches\[\d+\]/", $string)) {

            }
        }

        // Translate 'valid_email' rule.
        // No work needed here.
        if ($string == 'valid_email') {
            $this->addGumpRule('valid_email');
            $this->addValidateJsRule('valid_email');
            return true;
        }

        // Translate 'valid_emails' rule.
        // GUMP doesn't natively support this rule.
        // We can handle this ourselves when we're validating.
        if ($string == 'valid_emails') {
            $this->addGumpRule('valid_emails');
            $this->addValidateJsRule('valid_emails');
            return true;
        }

        // Translate 'min_length' rule.
        if ($this->translateParameterRule($string, 'min_len', 'min_length'))
            return true;

        // Translate 'max_length' rule.
        if($this->translateParameterRule($string, 'max_len', 'max_length'))
            return true;

        // Translate 'exact_length' rule.
        if($this->translateParameterRule($string, 'exact_len', 'exact_length'))
            return true;

        // Translate 'greater_than' rule.
        //
        // GUMP doesn't have native 'greater_than' support,
        // but we pretend that it does here, and we'll manually
        // handle validation ourselves later.
        if ($this->translateParameterRule($string, 'greater_than', 'greater_than'))
            return true;

        // Translate 'less_than' rule.
        //
        // GUMP doesn't have native 'less_than' support,
        // but we pretend that it does here, and we'll manually
        // handle validation ourselves later.
        if ($this->translateParameterRule($string, 'less_than', 'less_than'))
            return true;

        // Translate 'alpha' rule.
        if ($string == 'alpha') {
            $this->addGumpRule('alpha');
            $this->addValidateJsRule('alpha');
            return true;
        }

        // Translate 'alpha_numeric' rule.
        if ($string == 'alpha_numeric') {
            $this->addGumpRule('alpha_numeric');
            $this->addValidateJsRule('alpha_numeric');
            return true;
        }

        // Translate 'alpha_dash' rule.
        if ($string == 'alpha_dash') {
            $this->addGumpRule('alpha_dash');
            $this->addValidateJsRule('alpha_dash');
            return true;
        }

        // Translate 'numeric' rule.
        if ($string == 'numeric') {
            $this->addGumpRule('numeric');
            $this->addValidateJsRule('numeric');
            return true;
        }

        // Translate 'integer' rule.
        if ($string == 'integer') {
            $this->addGumpRule('integer');
            $this->addValidateJsRule('integer');
            return true;
        }

        // Translate 'boolean' rule.
        //
        // Validate.js doesn't natively support the boolean rule, but we'll
        // check for it manually later.
        if ($string == 'boolean') {
            $this->addGumpRule('boolean');
            $this->addValidateJsRule('boolean');
            return true;
        }

        // Translate 'float' (GUMP) / 'decimal' (validate.js) rule.
        if ($string == 'float' || $string == 'decimal') {
            $this->addGumpRule('float');
            $this->addValidateJsRule('decimal');
            return true;
        }

        // Translate 'is_natural' rule.
        //
        // GUMP doesn't have native 'is_natural' support,
        // but we pretend that it does here, and we'll manually
        // handle validation ourselves later.
        if ($string == 'is_natural') {
            $this->addGumpRule('is_natural');
            $this->addValidateJsRule('is_natural');
            return true;
        }

        // Translate 'is_natural_no_zero' rule.
        //
        // GUMP doesn't have native 'is_natural_no_zero' support,
        // but we pretend that it does here, and we'll manually
        // handle validation ourselves later.
        if ($string == 'is_natural_no_zero') {
            $this->addGumpRule('is_natural_no_zero');
            $this->addValidateJsRule('is_natural_no_zero');
            return true;
        }

        // Translate 'valid_ip' rule.
        if ($string == 'valid_ip') {
            $this->addGumpRule('valid_ip');
            $this->addValidateJsRule('valid_ip');
            return true;
        }

        // Translate 'valid_base64' rule.
        //
        // GUMP doesn't have native 'valid_base64' support,
        // but we pretend that it does here, and we'll manually
        // handle validation ourselves later.
        if ($string == 'valid_base64') {
            $this->addGumpRule('valid_base64');
            $this->addValidateJsRule('valid_base64');
            return true;
        }

        // Translate 'valid_cc' rule.
        //
        // Validate.js doesn't natively support the 'valid_cc' rule, but we'll
        // check for it manually later.
        if ($string == 'valid_cc') {
            $this->addGumpRule('valid_cc');
            $this->addValidateJsRule('valid_cc');
            return true;
        }

        // Translate 'valid_url' rule.
        //
        // Validate.js doesn't natively support the 'valid_url' rule, but we'll
        // check for it manually later.
        if ($string == 'valid_url') {
            $this->addGumpRule('valid_url');
            $this->addValidateJsRule('valid_url');
            return true;
        }

        // Translate 'valid_name' rule.
        //
        // Validate.js doesn't natively support the 'valid_name' rule, but we'll
        // check for it manually later.
        if ($string == 'valid_name') {
            $this->addGumpRule('valid_name');
            $this->addValidateJsRule('valid_name');
            return true;
        }

        // Translate 'url_exists' rule.
        //
        // Validate.js doesn't natively support the 'url_exists' rule, but we'll
        // check for it manually later.
        if ($string == 'url_exists') {
            $this->addGumpRule('url_exists');
            $this->addValidateJsRule('url_exists');
            return true;
        }

        throw new \Exception("The rule '$string' is not recognized by GUMP or validate.js");
    }

    /**
     * Takes a rule that accepts a single parameter, determines if it is in
     * GUMP or validate.js dialect, and translates it as needed to whichever
     * dialect it was not provided in.
     *
     * @param $string
     * @param $gumpPrefix
     * @param $validateJsPrefix
     * @return bool
     */
    private function translateParameterRule($string, $gumpPrefix, $validateJsPrefix) {

        // Check for a GUMP pattern match.
        if (preg_match("/\A" . $gumpPrefix . ",\d+\z/", $string)) {

            // GUMP rule match found, add it to our GUMP rule set.
            $this->addGumpRule($string);

            // Change out the brackets for a comma, replace the
            // GUMP prefix with the validate.js prefix, and add
            // this rule to the validate.js rule set.
            $validateJsString = str_replace(',', '[', $string) . ']';
            $validateJsString = str_replace($gumpPrefix, $validateJsPrefix, $validateJsString);
            $this->addValidateJsRule($validateJsString);

            // Return that we found a match.
            return true;
        }

        // Check for a validate.js pattern match.
        if (preg_match("/\A" . $validateJsPrefix . ",\d+\z/", $string)) {

            // validate.js match found, add it to our validate.js rule set.
            $this->addValidateJsRule($string);

            // Change out the comma for a opening bracket, add a
            // closing bracket to the end, replace the validate.js prefix
            // with the GUMP prefix, and add it to the GUMP rule set
            $gumpString = str_replace('[', ',', $string);
            $gumpString = str_replace(']', '', $gumpString);
            $gumpString = str_replace($validateJsPrefix, $gumpPrefix, $gumpString);
            $this->addGumpRule($gumpString);

            // Return that we found a match.
            return true;
        }

        // Return that we couldn't find a match.
        return false;
    }

    /**
     * Adds a string to our GUMP rule set.
     *
     * @param $string An individual rule to add to GUMP, excluding the pipe suffix
     */
    private function addGumpRule($string) {
        $this->gumpRules .= $string . '|';
    }

    /**
     * Adds a string to our validate.js rule set.
     *
     * @param $string An individual rule to add to validate.js, excluding the pipe suffix
     */
    private function addValidateJsRule($string) {
        $this->validateJsRules .= $string . '|';
    }
}

/**
 * Extends Field to provide text field-specific functionality.
 */
class Text extends Field
{
    public function __construct($name, $label, $type, $rules, $sanitize, $options = null) {
        parent::__construct($name, $label, $type, $rules, $sanitize, $options);
    }

    /**
     * Returns the HTML element markup for this text field.
     *
     * @param null|array $attributes
     * @return string
     */
    public function doWrite($attributes) {
        return '<input type="text" ' . $attributes . ' value="' . $this->value . '" />';
    }
}


/**
 * Extends Field to provide password field-specific functionality.
 */
class Password extends Field
{
    public function __construct($name, $label, $type, $rules, $sanitize, $options = null) {
        parent::__construct($name, $label, $type, $rules, $sanitize, $options);
    }

    /**
     * Returns the HTML element markup for this password field.
     *
     * @param null|array $attributes
     * @return string
     */
    public function doWrite($attributes) {
        return '<input type="password" ' . $attributes . ' value="' . $this->value . '" />';
    }
}


/**
 * Extends Field to provide checkbox-specific functionality.
 */
class Checkbox extends Field
{
    public function __construct($name, $label, $type, $rules, $sanitize, $options = null) {
        parent::__construct($name, $label, $type, $rules, $sanitize, $options);
    }

    /**
     * Returns the HTML element markup for this checkbox field.
     *
     * @param null|array $attributes
     * @return string
     */
    public function doWrite($attributes) {

        // Check this checkbox if its value is true.
        ($this->value) ? $checked = 'checked' : $checked = '';

        // Return the HTML markup for this single checkbox.
        return '<input type="checkbox" ' . $attributes . ' ' . $checked . ' />';
    }
}


/**
 * Extends Field to provide select menu-specific functionality.
 */
class SelectMenu extends Field
{
    public function __construct($name, $label, $type, $rules, $sanitize, $options = null) {
        parent::__construct($name, $label, $type, $rules, $sanitize, $options);
    }

    /**
     * Returns the HTML markup for an entire select menu.
     *
     * @param $attributes
     * @return string
     */
    public function doWrite($attributes) {

        // Open select menu markup.
        $html = '<select ' . $attributes . ' >';

        // Print each option element.
        foreach ($this->options as $name => $label) {

            // Select this option if it's name matches the Field's value.
            ($name == $this->value) ? $selected = 'selected' : $selected = '';

            // Add this option element to the markup.
            $html .= '<option name="' . $name . '" ' . $selected . '>' . $label . '</option>';
        }

        // Close and return select menu markup.
        $html .= '</select>';
        return $html;
    }
}


/**
 * Extends Field to provide radio series-specific functionality.
 */
class RadioSeries extends Field
{
    public function __construct($name, $label, $type, $rules, $sanitize, $options = null) {
        parent::__construct($name, $label, $type, $rules, $sanitize, $options);
    }

    /**
     * Returns the HTML markup for this entire series of radio buttons.
     *
     * Each radio buttons is printed along with its label.
     *
     * Each pair of label/input elements is wrapped in a div container
     * with the class 'radio-pair'.  Each label element is given
     * the class 'radio-pair-label'.
     *
     * @param $attributes
     * @return string
     */
    public function doWrite($attributes) {

        // Initialize a string to hold all of our
        // HTML markup as we concatenate.
        $html = '';

        // Add each radio pair to the series' markup.
        foreach ($this->options as $name => $label) {

            // Open the container div.
            $html .= '<div class="radio-pair">';

            // Print the radio button's label.
            $html .= '<label class="radio-pair-label" />';

            // Check the radio button if it's name matches the Field's value.
            ($name == $this->value) ? $checked = 'checked' : $checked = '';

            // Print the radio button.
            $html .= '<input type="radio" ' . $attributes . ' ' . $checked . ' /> ' . $label . '</label>';

            // Close the container div.
            $html .= '</div>';
        }

        // Return the HTML markup.
        return $html;
    }
}


/**
 * Extends Field to provide text area-specific functionality.
 */
class Textarea extends Field
{
    public function __construct($name, $label, $type, $rules, $sanitize, $options = null) {
        parent::__construct($name, $label, $type, $rules, $sanitize, $options);
    }

    /**
     * Returns the HTML markup for this textarea element.
     *
     * Columns and rows should be included in the attributes string.
     *
     * @param $attributes
     * @return string
     */
    public function doWrite($attributes) {
        return '<textarea ' . $attributes . ' />' . $this->value . '</textarea>';
    }
}


/**
 * Extends Field to provide upload field-specific functionality.
 */
class FileUpload extends Field
{
    public function __construct($name, $label, $type, $rules, $sanitize, $options = null) {
        parent::__construct($name, $label, $type, $rules, $sanitize, $options);
    }

    /**
     * Returns the HTML element markup for this upload field
     *
     * @param null|array $attributes
     * @return string
     */
    public function doWrite($attributes) {
        return '<input type="upload" ' . $attributes . ' />';
    }
}