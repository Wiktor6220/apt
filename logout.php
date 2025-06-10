<?php
require_once 'config.php';

// Zniszcz sesję
session_destroy();

// Przekieruj do strony logowania
header("Location: login.php");
exit();
?> 