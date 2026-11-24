<?php
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok($_POST['csrf'] ?? '')) {
  $_SESSION = [];
  session_destroy();
}
header('Location: index.php');
