# Student information update

Implemented directly in the existing G2 project. No GitHub push or commit was made.

## Behavior

- Add and edit forms collect Full Name, Year Level, Section, Course, and Address.
- All five student fields have server validation. Year Level and Course accept only the listed dropdown values.
- Full Name supports 200 characters, Section 100, and Address 5,000. Student data supports UTF-8, including accented names and emoji.
- Records displays the student fields, creator, creation time, and edit/delete actions. Addresses retain line breaks. The table scrolls horizontally on smaller screens.
- Search covers all five student fields plus the existing Description and Category. Year Level and Course filters combine with the search using AND.
- The dashboard shows total records, complete records, records missing student details, courses represented, and counts by year and course.
- Existing Description, Category, and Record Date remain available under optional additional details. The legacy name column remains; new saves mirror the first 100 characters of Full Name into it.
- All record mutations use prepared statements and a session CSRF token. Output is escaped, validation preserves submitted values, and successful saves redirect to Records with a notification.
- Login, signup, logout, password handling, sessions, and stored roles retain their existing implementation. Both Admin and User retain the existing ability to manage shared records.

## Files

Modified:

- [add_entry.php](add_entry.php)
- [view_entries.php](view_entries.php)
- [edit_entry.php](edit_entry.php)
- [delete_entry.php](delete_entry.php)
- [dashboard.php](dashboard.php)
- [css/style.css](css/style.css)

Added:

- [includes/student.php](includes/student.php): shared authentication guard, validation, CSRF, and record helpers.
- [includes/student_form.php](includes/student_form.php): shared add/edit form.
- [includes/header.php](includes/header.php) and [includes/footer.php](includes/footer.php): consistent navigation and page layout.
- [migrations/20261015_student_information.sql](migrations/20261015_student_information.sql).
- [tests/student_workflow.py](tests/student_workflow.py): repeatable HTTP/database integration checks.
- This document.

## Database migration and phpMyAdmin

The migration has already been applied to this local dataentryg2 database and successfully imported a second time to check repeatability. The inspected server is XAMPP MariaDB 10.4.32; the script uses MariaDB 10.4+ conditional ADD COLUMN syntax.

The migration adds nullable fields so older records remain compatible, backfills Full Name from the existing name, and preserves all other record values. It converts the existing name, description, and category columns to utf8mb4 without changing their lengths or nullability. It retains all record IDs, foreign keys, ownership, dates, and timestamps. It does not alter users, drop a table, or delete records.

To apply it through phpMyAdmin on a copy or another matching XAMPP installation:

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Open http://localhost/phpmyadmin and select dataentryg2.
3. Use Export to save a backup before making schema changes.
4. Open Import and select migrations/20261015_student_information.sql from this project.
5. Click Go. Check entries > Structure for full_name, year_level, section, course, and address.
6. Open http://localhost/G2/login.php and sign in.

There is no need to import it again on this workspace. Older records display missing student details as “Not provided”; editing them requires completing the five student fields.

A full pre-migration database backup was saved outside the web root:

`C:\Users\91460\AppData\Local\Temp\G2-student-fields-20261015-195454\dataentryg2-before.sql`

## Tests executed on October 15, 2026

- PHP 8.2.12 syntax checks passed for all 15 PHP files.
- Migration executed twice; all five columns and Unicode collations were verified against the running database.
- Hash comparisons confirmed that all original legacy record values and all original account values were preserved: one existing record and four existing accounts.
- All 13 HTTP/database integration check groups passed, including real insert/read/update/delete, every student field, Unicode, output escaping, validation, CSRF, all five search fields, combined filters, dashboard counts, signup validation and duplicates, User/Admin login, session rotation, shared permissions, and logout.
- Headless Microsoft Edge passed 20 page layout checks: dashboard, add, records, edit, and delete at widths of 320, 375, 768, and 1440 pixels. The browser workflow also exercised signup/login, CRUD, filters, Clear, deletion cancellation, and logout.
- Temporary accounts and student records created by the tests were removed. Database comparisons after cleanup confirmed the original data was unchanged.
- Git whitespace checks passed.

The browser audit script and screenshots were kept in the same temporary folder as the backup, outside the project.

## Repeat the checks

From PowerShell in C:\xampp\htdocs\G2, check PHP syntax:

```powershell
rg --files -g '*.php' | ForEach-Object { & 'C:\xampp\php\php.exe' -l $_ }
```

With Apache and MySQL running and the migration applied, run the integration suite:

```powershell
python tests/student_workflow.py --allow-test-data
```

The suite uses Python's standard library and XAMPP PHP, creates randomly named test accounts and records, and cleans up those accounts and their records in a finally block. It never needs an existing account password. It checks database hashes before and after the run. Alternate installations can use --base-url and --php arguments.

For a manual review:

1. Sign up with a new test account, then log in. Verify the dashboard and navigation.
2. Add a student with all five fields, including a multiline address; optionally enter Description, Category, and Record Date.
3. Verify every value in Records. Search separately by each field. Apply Year Level and Course individually and together, then use Clear.
4. Edit all student fields and confirm the updated values and dashboard counts.
5. Open Delete and use Cancel to keep the record. Open it again and confirm deletion.
6. Try missing required values and confirm the browser validation. The automated suite also submits invalid requests directly to verify server validation.
7. Log out and verify that protected pages redirect to Login. Repeat login with an Admin account.
8. Check the form and horizontally scrollable table in the browser's mobile viewport.
