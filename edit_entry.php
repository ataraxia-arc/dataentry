<?php
require_once __DIR__ . "/includes/student.php";

$id = record_id();
$entry = null;
$errors = [];
try {
    $entry = find_student_entry($conn, $id);
    if (!$entry) {
        records_redirect("The requested record could not be found.", "error");
    }
} catch (mysqli_sql_exception $exception) {
    $errors["form"] = database_error($exception);
}
if ($entry) {
    $values = student_values($entry);
    $values["full_name"] = $values["full_name"] !== "" ? $values["full_name"] : (string) $entry["name"];
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
                $name = mb_substr($values["full_name"], 0, 100, "UTF-8");
                $entryDate = $values["entry_date"] !== "" ? $values["entry_date"] : null;
                $stmt = $conn->prepare("UPDATE entries SET name = ?, description = ?, category = ?, entry_date = ?, full_name = ?, year_level = ?, section = ?, course = ?, address = ? WHERE id = ?");
                $stmt->bind_param("sssssssssi", $name, $values["description"], $values["category"], $entryDate, $values["full_name"], $values["year_level"], $values["section"], $values["course"], $values["address"], $id);
                $stmt->execute();
                $stmt->close();
                records_redirect("Student record updated successfully.");
            } catch (mysqli_sql_exception $exception) {
                $errors["form"] = database_error($exception);
            }
        }
    }
}
$pageTitle = "Edit Student";
$activePage = "view_entries.php";
$submitLabel = "Update Student Record";
require __DIR__ . "/includes/header.php";
?>
<main class="container">
    <div class="form-box student-form-box">
        <div class="form-header">
            <h1>Edit Student Record</h1>
            <p>Update the student's information. Complete any missing details for older records.</p>
        </div>
        <?php if ($entry): ?>
            <?php require __DIR__ . "/includes/student_form.php"; ?>
        <?php else: ?>
            <div class="error" role="alert"><?= escape_html($errors["form"]) ?></div>
            <a href="view_entries.php" class="btn btn-secondary">Back to Records</a>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . "/includes/footer.php"; ?>
