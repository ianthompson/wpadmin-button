<?php
// Lets includes/menu-logic.php load outside WordPress for unit tests.
define( 'WPADMIN_BUTTON_TESTING', true );
require_once __DIR__ . '/../includes/menu-logic.php';
