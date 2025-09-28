<?php
// auth/logout.php
session_start();
session_destroy();
header('Location: /prestar_uc/auth/login.php');
