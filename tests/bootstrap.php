<?php
/**
 * PHPUnit bootstrap file for unit tests.
 *
 * Uses Brain Monkey to mock WordPress functions.
 *
 * @package BLU\Tests
 */

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Brain Monkey (for WordPress function mocking).
require_once dirname( __DIR__ ) . '/vendor/brain/monkey/inc/api.php';
