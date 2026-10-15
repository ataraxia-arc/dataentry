<?php
require_once __DIR__ . "/includes/student.php";

$values = student_values([]);
$errors = [];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $values = student_values($_POST);
    $errors = validate_student($values);
    if (!valid_csrf()) {
        $errors["form"] = "Your form session expired. Please try again.";
        http_response_code(403);
    } elseif ($errors) {
        http_response_code(422);
    } else {
        try {
            // Retain a compatible legacy name while storing the complete student name.
            $name = mb_substr($values["full_name"], 0, 100, "UTF-8");
            $entryDate = $values["entry_date"] !== "" ? $values["entry_date"] : null;
            $createdBy = (int) $_SESSION["user_id"];
            $stmt = $conn->prepare("INSERT INTO entries (name, description, category, entry_date, created_by, full_name, year_level, section, course, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssisssss", $name, $values["description"], $values["category"], $entryDate, $createdBy, $values["full_name"], $values["year_level"], $values["section"], $values["course"], $values["address"]);
            $stmt->execute();
            $stmt->close();
            records_redirect("Student record added successfully.");
        } catch (mysqli_sql_exception $exception) {
            $errors["form"] = database_error($exception);
        }
    }
}
$pageTitle = "Add Student";
$activePage = "add_entry.php";
$submitLabel = "Save Student Record";
require __DIR__ . "/includes/header.php";
?>
<main class="container">
    <div class="form-box student-form-box">
        <div class="form-header">
            <h1>Add Student Record</h1>
            <p>Enter the student's information below.</p>
        </div>
        <?php require __DIR__ . "/includes/student_form.php"; ?>
    </div>
</main>
<?php require __DIR__ . "/includes/footer.php"; ?>
