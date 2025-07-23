<?php
session_name('STUDENT_SESSION');
session_start();
session_unset();
session_destroy();

// Redirect to index
header("Location: ../public/login.php");
exit();
?>
