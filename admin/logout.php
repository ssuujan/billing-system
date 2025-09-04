<?php

session_name('ADMIN_SESSION');
session_start();
session_unset();
session_destroy();

// Redirect to index
header("Location: ../public/admin.php");
exit();
?>
