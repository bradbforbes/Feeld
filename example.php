<?php

require_once 'Feeld.php';

$feeld = new Feeld\Feeld();
$feeld->register('username', 'Username', 'text', 'required', 'sanitize_string');
$feeld->register('email', 'Email', 'text', 'required', 'sanitize_email');

$feeld->pass(array('username' => 'brad!*)!(#$@*&V<script></script>', 'email' => '_!@#)~@!#@@gmail.com'));

$feeld->validate();