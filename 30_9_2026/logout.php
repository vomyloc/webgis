<?php
require_once "ketnoi.php";
session_unset();
session_destroy();
header("Location: login.php");
exit;
