<?php

// Only require this file.
require_once 'Feeld.php';

// Initialize Feeld.  Use this object for everything:
// to register fields, pass in data, validate data, and get errors.
$feeld = new Feeld\Feeld();

// Register our fields.  We could have used registerBulk().
$feeld->register('first-name', 'First name', 'text', 'required|min_len,5', 'trim');
$feeld->register('last-name', 'Last name', 'text', '', 'trim');
$feeld->register('email', 'Email address', 'text', 'required', 'trim');
$feeld->register('room-type', 'Room type', 'radio', 'required', '', array('smoking' => 'Smoking', 'non-smoking' => 'Non-Smoking'));
$feeld->register('departure-date', 'Departure date', 'select', 'required', '',
    array('today' => 'Today', 'tomorrow' => 'Tomorrow', 'next-week' => 'Next week'));

// Check if our form was submitted.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Give Feeld a copy of the form's user-submitted data.
    $feeld->pass($_POST);

    // Validate the form.  The form data will also be sanitized
    // and filtered during validation.
    $feeld->validate();

    // Check for any validation errors.
    if ($feeld->hasErrors()) {

        // Print errors to page.
        echo $feeld->getErrorsHTML();

    } else {

        // Process the form while being able to sleep at night.

    }
}

?>
<!doctype html>
<html>
    <head>
        <link rel="stylesheet" href="/lib/js/foorm-remote/Foorm.css" />
        <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
        <script type="text/javascript" src="/lib/js/foorm-remote/validate.js"></script>
        <script type="text/javascript" src="/lib/js/foorm-remote/Foorm.js"></script>

        <script type="text/javascript">
            $(document).ready(function(){
                var form = new Foorm('example', '<?php echo $feeld->getValidateJsData(); ?>');
            });
        </script>

    </head>
    <body>

        <!-- If we change the form's ID, we need to also change it
             while initializing Foorm. -->
        <form name="example" id="example" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">

            <!-- The write() method echoes the HTML directly instead of returning it.
                 If you want it returned instead, use getHTML(). -->
            First name: <?php $feeld->write('first-name'); ?><br /><br />
            Last name: <?php $feeld->write('last-name'); ?><br /><br />
            Email: <?php $feeld->write('email'); ?><br /><br />

            <!-- Notice that when registering this field, we passed Feeld an associate array of options.
                 Now Feeld will use that array to print the entire select menu for us. -->
            Departure date: <?php $feeld->write('departure-date'); ?><br /><br />

            <!-- Feeld also uses associate arrays of options for handling series of radio buttons.

                The array key will be used to populate the $_POST value, and the array value will
                be used as the string option to print for the user.

                 It also wraps each label/button pair in a div with the class 'radio-pair' and gives label
                 elements the class 'radio-pair-label'. -->
            Expected arrival date: <?php $feeld->write('room-type'); ?>

            <input type="submit" value="Submit" />

        </form>

    </body>
</html>
