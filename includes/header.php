<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape_html($pageTitle) ?> | Data Entry System</title>
    <link rel="stylesheet" href="css/style.css?v=student-1">
</head>
<body>
<nav class="navbar" aria-label="Main navigation">
    <h2>Data Entry System</h2>
    <div class="nav-links">
        <?php foreach (["dashboard.php" => "Dashboard", "add_entry.php" => "Add Entry", "view_entries.php" => "Records", "logout.php" => "Logout"] as $href => $label): ?>
            <a href="<?= $href ?>"<?= ($activePage ?? "") === $href ? ' aria-current="page"' : "" ?>><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</nav>
