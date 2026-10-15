<?php
require_once __DIR__ . "/includes/student.php";

$id = record_id();
$entry = null;
$error = "";
try {
    $entry = find_student_entry($conn, $id);
    if (!$entry) {
        records_redirect("The requested record could not be found.", "error");
    }
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (!valid_csrf()) {
            $error = "Your form session expired. Please review the record and try again.";
            http_response_code(403);
        } else {
            $stmt = $conn->prepare("DELETE FROM entries WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows === 1;
            $stmt->close();
            records_redirect($deleted ? "Student record deleted successfully." : "The record was already removed.");
        }
    }
} catch (mysqli_sql_exception $exception) {
    $error = database_error($exception);
}
$pageTitle = "Delete Student";
$activePage = "view_entries.php";
require __DIR__ . "/includes/header.php";
?>
<main class="container">
    <div class="delete-box">
        <div class="delete-icon" aria-hidden="true">!</div>
        <h1>Delete Student Record</h1>
        <p class="delete-warning">Confirm the student record below before deleting.</p>
        <p class="delete-description">This action cannot be undone.</p>
        <?php if ($error !== ""): ?>
            <div class="error" role="alert"><?= escape_html($error) ?></div>
        <?php endif; ?>
        <?php if ($entry): ?>
            <dl class="delete-details">
                <?php foreach (["full_name" => "Full Name", "year_level" => "Year Level", "section" => "Section", "course" => "Course", "address" => "Address"] as $field => $label): ?>
                    <div class="detail-row">
                        <dt><?= $label ?></dt>
                        <dd><?= escape_html(($field === "full_name" ? ($entry[$field] ?? $entry["name"]) : ($entry[$field] ?? "")) ?: "Not provided") ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <div class="delete-actions">
                    <button type="submit" class="btn btn-danger">Yes, Delete Record</button>
                    <a href="view_entries.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <a href="view_entries.php" class="btn btn-secondary">Back to Records</a>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . "/includes/footer.php"; ?>
