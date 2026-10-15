<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "dataentryg2";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($host, $user, $password, $database);
} catch (mysqli_sql_exception $exception) {
    error_log("Database connection failed (code " . $exception->getCode() . ").");
    http_response_code(503);
    exit("Database connection unavailable. Please try again later.");
}
