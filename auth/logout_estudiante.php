<?php
// auth/logout.php
session_start();
session_destroy();
header('Location: /prestar_UC-main/auth/login.php');
