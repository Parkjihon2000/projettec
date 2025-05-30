<?php
require_once '../include/connexion.php';
session_start();

// Destroy the session
session_destroy();

// Redirect to the login page
header('Location: ../landingpage/landing.html');
exit();
?>