<?php

session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/database.php";
// Keep the existing authentication connection behavior; use Unicode for records.
mysqli_set_charset($conn, "utf8mb4");

const STUDENT_YEARS = ["1st Year", "2nd Year", "3rd Year", "4th Year"];
const STUDENT_COURSES = ["BSIT", "BSCS", "BSIS", "BSEd", "BEEd", "BSBA", "Other"];

function escape_html($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function text_input(array $source, string $key): string
{
    // Trim ordinary whitespace while retaining null bytes for explicit rejection.
    return is_string($source[$key] ?? null) ? trim($source[$key], " \t\n\r\x0B") : "";
}

function csrf_token(): string
{
    if (!isset($_SESSION["student_csrf"])) {
        $_SESSION["student_csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["student_csrf"];
}

function valid_csrf(): bool
{
    return is_string($_POST["csrf_token"] ?? null)
        && hash_equals(csrf_token(), $_POST["csrf_token"]);
}

function student_values(array $source): array
{
    $values = [];
    foreach (["full_name", "year_level", "section", "course", "address", "description", "category", "entry_date"] as $field) {
        $values[$field] = text_input($source, $field);
    }
    return $values;
}

function validate_student(array $values): array
{
    $errors = [];
    foreach (["full_name" => ["Full name", 200], "section" => ["Section", 100], "address" => ["Address", 5000]] as $field => [$label, $limit]) {
        if ($values[$field] === "") {
            $errors[$field] = "$label is required.";
        } elseif (!mb_check_encoding($values[$field], "UTF-8") || strpos($values[$field], "\0") !== false) {
            $errors[$field] = "$label contains unsupported characters.";
        } elseif (mb_strlen($values[$field], "UTF-8") > $limit) {
            $errors[$field] = "$label must be $limit characters or fewer.";
        }
    }
    if (!in_array($values["year_level"], STUDENT_YEARS, true)) {
        $errors["year_level"] = "Select a valid year level.";
    }
    if (!in_array($values["course"], STUDENT_COURSES, true)) {
        $errors["course"] = "Select a valid course.";
    }
    foreach (["description" => 10000, "category" => 50] as $field => $limit) {
        if (!mb_check_encoding($values[$field], "UTF-8") || strpos($values[$field], "\0") !== false) {
            $errors[$field] = ucfirst($field) . " contains unsupported characters.";
        } elseif (mb_strlen($values[$field], "UTF-8") > $limit) {
            $errors[$field] = ucfirst($field) . " must be $limit characters or fewer.";
        }
    }
    if ($values["entry_date"] !== "") {
        $date = strpos($values["entry_date"], "\0") === false
            ? DateTimeImmutable::createFromFormat("!Y-m-d", $values["entry_date"]) : false;
        if (!$date || $date->format("Y-m-d") !== $values["entry_date"] || $values["entry_date"] < "1000-01-01") {
            $errors["entry_date"] = "Enter a valid record date.";
        }
    }
    return $errors;
}

function field_attributes(string $field, array $errors): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . $field . '-error"' : "";
}

function field_error(string $field, array $errors): void
{
    if (isset($errors[$field])) {
        echo '<p class="field-error" id="' . $field . '-error">' . escape_html($errors[$field]) . '</p>';
    }
}

function record_id(): int
{
    $id = filter_var(text_input($_GET, "id"), FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($id === false) {
        records_redirect("The requested record could not be found.", "error");
    }
    return $id;
}

function find_student_entry(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM entries WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $entry = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $entry;
}

function records_redirect(string $message, string $type = "message"): void
{
    $_SESSION["student_flash"] = ["text" => $message, "type" => $type];
    header("Location: view_entries.php", true, $_SERVER["REQUEST_METHOD"] === "POST" ? 303 : 302);
    exit();
}

function database_error(mysqli_sql_exception $exception): string
{
    error_log("Student record operation failed (code " . $exception->getCode() . "). Check the student information migration if columns are missing.");
    http_response_code(503);
    return "Student records are temporarily unavailable. Please try again later or contact your administrator.";
}
