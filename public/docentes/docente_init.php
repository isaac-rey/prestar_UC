<?php
// docente_init.php
session_start();
require __DIR__ . '../../../config/db.php';

function doc_logged_in(): bool {
  return isset($_SESSION['doc']);
}

function doc() {
  return $_SESSION['doc'] ?? null;
}

function require_doc_login() {
  if (!doc_logged_in()) {
    header('Location: /prestar_uc/auth/login_docente.php');
    exit;
  }
}
