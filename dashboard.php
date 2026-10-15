<?php
require_once __DIR__ . "/includes/student.php";

$stats = null;
$yearCounts = [];
$courseCounts = [];
$error = "";
try {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(NULLIF(TRIM(full_name), '') IS NOT NULL AND year_level IN ('1st Year', '2nd Year', '3rd Year', '4th Year') AND NULLIF(TRIM(section), '') IS NOT NULL AND course IN ('BSIT', 'BSCS', 'BSIS', 'BSEd', 'BEEd', 'BSBA', 'Other') AND NULLIF(TRIM(address), '') IS NOT NULL), 0) AS complete, COUNT(DISTINCT NULLIF(course, '')) AS courses FROM entries");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    foreach (["year_level", "course"] as $field) {
        // Identifiers come only from this fixed allowlist.
        $stmt = $conn->prepare("SELECT `$field` AS label, COUNT(*) AS total FROM entries GROUP BY `$field`");
        $stmt->execute();
        $counts = [];
        foreach ($stmt->get_result() as $row) {
            $counts[$row["label"] ?? ""] = (int) $row["total"];
        }
        $stmt->close();
        if ($field === "year_level") {
            $yearCounts = $counts;
        } else {
            $courseCounts = $counts;
        }
    }
} catch (mysqli_sql_exception $exception) {
    $stats = null;
    $error = database_error($exception);
}
$pageTitle = "Dashboard";
$activePage = "dashboard.php";
require __DIR__ . "/includes/header.php";
?>
<main class="container">
    <div class="welcome-box">
        <h1>Welcome, <?= escape_html($_SESSION["username"] ?? "") ?>!</h1>
        <p>You are logged in as <strong><?= escape_html($_SESSION["role"] ?? "user") ?></strong>.</p>
    </div>
    <?php if ($error !== ""): ?><div class="error" role="alert"><?= escape_html($error) ?></div><?php endif; ?>
    <?php if ($stats): ?>
        <div class="cards stats-grid" aria-label="Student record statistics">
            <?php foreach (["Total Records" => (int) $stats["total"], "Complete Student Records" => (int) $stats["complete"], "Missing Student Details" => (int) $stats["total"] - (int) $stats["complete"], "Courses Represented" => (int) $stats["courses"]] as $label => $count): ?>
                <div class="card stat-card"><h2><?= $label ?></h2><p class="stat-number"><?= $count ?></p></div>
            <?php endforeach; ?>
        </div>
        <div class="cards distribution-grid">
            <?php foreach (["Year Level" => [STUDENT_YEARS, $yearCounts, "year_level"], "Course" => [STUDENT_COURSES, $courseCounts, "course"]] as $label => [$options, $counts, $filter]): ?>
                <section class="card">
                    <h2>Records by <?= $label ?></h2>
                    <ul class="statistics-list">
                        <?php foreach ($options as $option): ?>
                            <li><a href="view_entries.php?<?= escape_html(http_build_query([$filter => $option])) ?>"><?= escape_html($option) ?></a><strong><?= $counts[$option] ?? 0 ?></strong></li>
                        <?php endforeach; ?>
                        <li><span>Not provided / unrecognized</span><strong><?= array_sum($counts) - array_sum(array_intersect_key($counts, array_flip($options))) ?></strong></li>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="cards">
        <div class="card"><h3>Add New Entry</h3><p>Create and save a student record.</p><a href="add_entry.php" class="btn">Add Entry</a></div>
        <div class="card"><h3>View Records</h3><p>Search, filter, and manage student information.</p><a href="view_entries.php" class="btn">View Records</a></div>
        <div class="card"><h3>Account</h3><p>Current user: <?= escape_html($_SESSION["username"] ?? "") ?></p><a href="logout.php" class="btn btn-danger">Logout</a></div>
    </div>
</main>
<?php require __DIR__ . "/includes/footer.php"; ?>
