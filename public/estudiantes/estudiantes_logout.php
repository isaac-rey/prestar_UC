<?php
// public/estudiantes_logout.php
session_start();
unset($_SESSION['est']);
header('Location: /prestar_uc/auth/login.php');
