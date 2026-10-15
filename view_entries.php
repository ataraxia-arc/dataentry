<?php
require_once __DIR__ . "/includes/student.php";

$search = text_input($_GET, "search");
$yearFilter = text_input($_GET, "year_level");
$courseFilter = text_input($_GET, "course");
$error = "";
$result = null;
$flash = $_SESSION["student_flash"] ?? null;
unset($_SESSION["student_flash"]);
if (($yearFilter !== "" && !in_array($yearFilter, STUDENT_YEARS, true))
    || ($courseFilter !== "" && !in_array($courseFilter, STUDENT_COURSES, true))) {
    $error = "Select a valid year level and course filter.";
} elseif (!mb_check_encoding($search, "UTF-8") || mb_strlen($search, "UTF-8") > 200) {
    $error = "Use a search of 200 characters or fewer.";
} else {
    try {
        $conditions = [];
        $params = [];
        if ($search !== "") {
            $term = "%" . strtr($search, ["!" => "!!", "%" => "!%", "_" => "!_"]) . "%";
            $conditions[] = "(COALESCE(NULLIF(entries.full_name, ''), entries.name) LIKE ? ESCAPE '!' OR entries.year_level LIKE ? ESCAPE '!' OR entries.section LIKE ? ESCAPE '!' OR entries.course LIKE ? ESCAPE '!' OR entries.address LIKE ? ESCAPE '!' OR entries.description LIKE ? ESCAPE '!' OR entries.category LIKE ? ESCAPE '!')";
            $params = array_fill(0, 7, $term);
        }
        if ($yearFilter !== "") {
            $conditions[] = "entries.year_level = ?";
            $params[] = $yearFilter;
        }
        if ($courseFilter !== "") {
            $conditions[] = "entries.course = ?";
            $params[] = $courseFilter;
        }
        $sql = "SELECT entries.*, users.username FROM entries LEFT JOIN users ON entries.created_by = users.id";
        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $stmt = $conn->prepare($sql . " ORDER BY entries.id DESC");
        if ($params) {
            $stmt->bind_param(str_repeat("s", count($params)), ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } catch (mysqli_sql_exception $exception) {
        $error = database_error($exception);
    }
}
$hasFilters = $search !== "" || $yearFilter !== "" || $courseFilter !== "";
$pageTitle = "Student Records";
$activePage = "view_entries.php";
require __DIR__ . "/includes/header.php";
?>
<main class="container records-container">
    <div class="page-header">
        <div><h1>Student Records</h1><p>Search, review, and manage saved student information.</p></div>
        <a href="add_entry.php" class="btn">+ Add Entry</a>
    </div>
    <?php if ($flash): ?>
        <div class="<?= $flash["type"] === "error" ? "error" : "message" ?>" role="status"><?= escape_html($flash["text"]) ?></div>
    <?php endif; ?>
    <?php if ($error !== ""): ?>
        <div class="error" role="alert"><?= escape_html($error) ?></div>
    <?php endif; ?>
    <div class="search-box">
        <form method="GET" action="view_entries.php" class="search-form student-search">
            <div class="form-group search-field">
                <label for="search">Search student information</label>
                <input type="search" id="search" name="search" placeholder="Name, year, section, course, or address" maxlength="200" value="<?= escape_html($search) ?>">
            </div>
            <div class="form-group">
                <label for="filter_year">Year Level</label>
                <select id="filter_year" name="year_level">
                    <option value="">All year levels</option>
                    <?php foreach (STUDENT_YEARS as $year): ?>
                        <option value="<?= escape_html($year) ?>"<?= $yearFilter === $year ? " selected" : "" ?>><?= escape_html($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="filter_course">Course</label>
                <select id="filter_course" name="course">
                    <option value="">All courses</option>
                    <?php foreach (STUDENT_COURSES as $course): ?>
                        <option value="<?= escape_html($course) ?>"<?= $courseFilter === $course ? " selected" : "" ?>><?= escape_html($course) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn">Search</button>
                <?php if ($hasFilters): ?><a href="view_entries.php" class="btn btn-secondary">Clear</a><?php endif; ?>
            </div>
        </form>
    </div>
    <?php if ($result): ?>
        <p class="results-summary" role="status"><?= (int) $result->num_rows ?> record<?= $result->num_rows === 1 ? "" : "s" ?><?= $hasFilters ? " matched" : " saved" ?>. <span>Scroll the table horizontally to view all columns on smaller screens.</span></p>
        <div class="table-box">
            <div class="table-responsive" tabindex="0" role="region" aria-label="Student records table">
                <table class="student-table">
                    <caption class="visually-hidden">Saved student records and management actions</caption>
                    <thead><tr>
                        <?php foreach (["ID", "Full Name", "Year Level", "Section", "Course", "Address", "Record Details", "Created By", "Created At", "Actions"] as $heading): ?>
                            <th scope="col"><?= $heading ?></th>
                        <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                    <?php if ($result->num_rows): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int) $row["id"] ?></td>
                                <td class="student-name"><?= escape_html($row["full_name"] ?: $row["name"]) ?></td>
                                <td><?= escape_html($row["year_level"] ?: "Not provided") ?></td>
                                <td><?= escape_html($row["section"] ?: "Not provided") ?></td>
                                <td><span class="category-badge"><?= escape_html($row["course"] ?: "Not provided") ?></span></td>
                                <td class="student-address"><?= escape_html($row["address"] ?: "Not provided") ?></td>
                                <td class="record-details">
                                    <?php if ($row["description"] || $row["category"] || $row["entry_date"]): ?>
                                        <details><summary>View details</summary>
                                            <p><strong>Description:</strong> <?= escape_html($row["description"] ?: "Not provided") ?></p>
                                            <p><strong>Category:</strong> <?= escape_html($row["category"] ?: "Not provided") ?></p>
                                            <p><strong>Date:</strong> <?= escape_html($row["entry_date"] ?: "Not provided") ?></p>
                                        </details>
                                    <?php else: ?>No additional details<?php endif; ?>
                                </td>
                                <td><?= escape_html($row["username"] ?? "Unknown") ?></td>
                                <td><?= escape_html($row["created_at"]) ?></td>
                                <td><div class="table-actions">
                                    <a href="edit_entry.php?id=<?= (int) $row["id"] ?>" class="action-edit" aria-label="<?= escape_html('Edit ' . ($row['full_name'] ?: $row['name'])) ?>">Edit</a>
                                    <a href="delete_entry.php?id=<?= (int) $row["id"] ?>" class="action-delete" aria-label="<?= escape_html('Delete ' . ($row['full_name'] ?: $row['name'])) ?>">Delete</a>
                                </div></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="no-records"><?= $hasFilters ? "No student records matched your search and filters." : "No student records yet. Add an entry to get started." ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . "/includes/footer.php"; ?>
