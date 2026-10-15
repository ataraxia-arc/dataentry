<?php if ($errors): ?>
    <div class="error" role="alert">
        <?= escape_html($errors["form"] ?? "Please correct the highlighted fields and try again.") ?>
    </div>
<?php endif; ?>
<form method="POST" class="student-form">
    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
    <p class="form-help">All student information fields are required.</p>
    <div class="form-grid">
        <div class="form-group full-width">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" placeholder="e.g. Maria Santos" autocomplete="name" maxlength="200" required value="<?= escape_html($values["full_name"]) ?>"<?= field_attributes("full_name", $errors) ?>>
            <?php field_error("full_name", $errors); ?>
        </div>
        <div class="form-group">
            <label for="year_level">Year Level</label>
            <select id="year_level" name="year_level" required<?= field_attributes("year_level", $errors) ?>>
                <option value="">Select year level</option>
                <?php foreach (STUDENT_YEARS as $year): ?>
                    <option value="<?= escape_html($year) ?>"<?= $values["year_level"] === $year ? " selected" : "" ?>><?= escape_html($year) ?></option>
                <?php endforeach; ?>
            </select>
            <?php field_error("year_level", $errors); ?>
        </div>
        <div class="form-group">
            <label for="section">Section</label>
            <input type="text" id="section" name="section" placeholder="e.g. IT-2A" maxlength="100" required value="<?= escape_html($values["section"]) ?>"<?= field_attributes("section", $errors) ?>>
            <?php field_error("section", $errors); ?>
        </div>
        <div class="form-group full-width">
            <label for="course">Course</label>
            <select id="course" name="course" required<?= field_attributes("course", $errors) ?>>
                <option value="">Select course</option>
                <?php foreach (STUDENT_COURSES as $course): ?>
                    <option value="<?= escape_html($course) ?>"<?= $values["course"] === $course ? " selected" : "" ?>><?= escape_html($course) ?></option>
                <?php endforeach; ?>
            </select>
            <?php field_error("course", $errors); ?>
        </div>
        <div class="form-group full-width">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="4" placeholder="House number, street, barangay, city or municipality, province" autocomplete="street-address" maxlength="5000" required<?= field_attributes("address", $errors) ?>><?= escape_html($values["address"]) ?></textarea>
            <?php field_error("address", $errors); ?>
        </div>
    </div>
    <details class="additional-details"<?= array_intersect_key($errors, array_flip(["description", "category", "entry_date"])) ? " open" : "" ?>>
        <summary>Additional record details (optional)</summary>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3" maxlength="10000" placeholder="Additional notes about this record"<?= field_attributes("description", $errors) ?>><?= escape_html($values["description"]) ?></textarea>
            <?php field_error("description", $errors); ?>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="category">Category</label>
                <input id="category" name="category" maxlength="50" placeholder="Optional category" value="<?= escape_html($values["category"]) ?>"<?= field_attributes("category", $errors) ?>>
                <?php field_error("category", $errors); ?>
            </div>
            <div class="form-group">
                <label for="entry_date">Record Date</label>
                <input type="date" id="entry_date" name="entry_date" min="1000-01-01" max="9999-12-31" value="<?= escape_html($values["entry_date"]) ?>"<?= field_attributes("entry_date", $errors) ?>>
                <?php field_error("entry_date", $errors); ?>
            </div>
        </div>
    </details>
    <div class="form-actions">
        <button type="submit" class="btn"><?= escape_html($submitLabel) ?></button>
        <a href="view_entries.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>
