"""Real HTTP + database integration checks. Creates and removes its own test data.

Run from the project root after applying the SQL migration:
    python tests/student_workflow.py --allow-test-data
Only Python's standard library and the installed XAMPP PHP CLI are required.
"""
import argparse
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request


PHP_HELPER = r"""<?php
require getenv('G2_PROJECT_ROOT') . '/config/database.php';
$conn->set_charset('utf8mb4');
$request = json_decode(getenv('G2_TEST_PAYLOAD'), true, 512, JSON_THROW_ON_ERROR);
switch ($request['action']) {
    case 'snapshot':
        $entries = $conn->query('SELECT * FROM entries ORDER BY id')->fetch_all(MYSQLI_ASSOC);
        $users = $conn->query('SELECT * FROM users ORDER BY id')->fetch_all(MYSQLI_ASSOC);
        $output = ['entries' => count($entries), 'users' => count($users),
            'entries_hash' => hash('sha256', serialize($entries)),
            'users_hash' => hash('sha256', serialize($users))];
        break;
    case 'schema':
        $output = $conn->query('SHOW FULL COLUMNS FROM entries')->fetch_all(MYSQLI_ASSOC);
        break;
    case 'user':
        $stmt = $conn->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->bind_param('s', $request['username']);
        $stmt->execute();
        $output = $stmt->get_result()->fetch_assoc();
        break;
    case 'entry':
        $stmt = $conn->prepare('SELECT * FROM entries WHERE id = ?');
        $stmt->bind_param('i', $request['id']);
        $stmt->execute();
        $output = $stmt->get_result()->fetch_assoc();
        break;
    case 'find':
        $stmt = $conn->prepare('SELECT * FROM entries WHERE created_by = ? AND full_name = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('is', $request['user_id'], $request['full_name']);
        $stmt->execute();
        $output = $stmt->get_result()->fetch_assoc();
        break;
    case 'admin':
        $hash = password_hash($request['password'], PASSWORD_DEFAULT);
        $role = 'admin';
        $stmt = $conn->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $request['username'], $request['email'], $hash, $role);
        $stmt->execute();
        $output = ['id' => $conn->insert_id];
        break;
    case 'cleanup':
        $conn->begin_transaction();
        foreach ($request['accounts'] as $account) {
            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? AND email = ?');
            $stmt->bind_param('ss', $account['username'], $account['email']);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user) {
                $stmt = $conn->prepare('DELETE FROM entries WHERE created_by = ?');
                $stmt->bind_param('i', $user['id']);
                $stmt->execute();
                $stmt = $conn->prepare('DELETE FROM users WHERE id = ? AND username = ? AND email = ?');
                $stmt->bind_param('iss', $user['id'], $account['username'], $account['email']);
                $stmt->execute();
            }
        }
        $conn->commit();
        $output = ['cleaned' => true];
        break;
    default:
        throw new RuntimeException('Unknown test helper action');
}
echo json_encode($output, JSON_THROW_ON_ERROR);
"""


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    def __init__(self, base):
        self.base = base.rstrip("/") + "/"
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.cookies), NoRedirect()
        )

    def request(self, path, data=None):
        encoded = urllib.parse.urlencode(data).encode() if data is not None else None
        request = urllib.request.Request(self.base + path, data=encoded)
        try:
            response = self.opener.open(request, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read().decode("utf-8")
            for signature in ("Fatal error:", "Warning:", "Deprecated:", "Stack trace:"):
                assert signature not in body, "PHP error in HTTP response: " + signature
            return response.status, response.headers, body

    def token(self, path):
        status, _, body = self.request(path)
        assert status == 200, (path, status)
        match = re.search(r'name="csrf_token" value="([a-f0-9]+)"', body)
        assert match, "Missing CSRF field"
        return match.group(1)

    def session_id(self):
        return next((c.value for c in self.cookies if c.name == "PHPSESSID"), None)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--allow-test-data", action="store_true",
                        help="Allow temporary test accounts and records, with cleanup")
    parser.add_argument("--base-url", default="http://localhost/G2/")
    parser.add_argument("--php", default=r"C:\xampp\php\php.exe")
    args = parser.parse_args()
    if not args.allow_test_data:
        parser.error("--allow-test-data is required; this suite writes temporary test data")
    root = Path(__file__).resolve().parents[1]

    def db(action, **payload):
        environment = dict(os.environ, G2_PROJECT_ROOT=str(root),
                           G2_TEST_PAYLOAD=json.dumps(dict(action=action, **payload)))
        result = subprocess.run([args.php], input=PHP_HELPER, text=True,
                                capture_output=True, env=environment, cwd=root)
        if result.returncode:
            raise RuntimeError("PHP database helper failed: " + result.stderr)
        return json.loads(result.stdout)

    marker = secrets.token_hex(6)
    accounts = [
        {"username": "g2_test_" + marker, "email": marker + "@example.invalid"},
        {"username": "g2_admin_" + marker, "email": marker + ".admin@example.invalid"},
    ]
    password = secrets.token_urlsafe(20)
    before = db("snapshot")
    groups = []

    def passed(label):
        groups.append(label)
        print("PASS: " + label, flush=True)

    def has_record(body, record_id):
        return 'edit_entry.php?id=' + str(record_id) + '"' in body

    def login(client, account, role):
        status, _, _ = client.request("login.php")
        assert status == 200
        old_session = client.session_id()
        status, headers, _ = client.request("login.php", {
            "username": account["username"], "password": password
        })
        assert status == 302 and headers["Location"] == "dashboard.php"
        assert client.session_id() != old_session, "Login did not rotate the session"
        status, _, body = client.request("dashboard.php")
        assert status == 200 and "<strong>" + role + "</strong>" in body

    try:
        columns = {row["Field"]: row for row in db("schema")}
        for field in ("full_name", "year_level", "section", "course", "address"):
            assert field in columns and columns[field]["Null"] == "YES"
            assert columns[field]["Collation"].startswith("utf8mb4")
        for field in ("name", "description", "category", "entry_date", "created_by", "created_at"):
            assert field in columns
        passed("Schema supports all student fields and preserves legacy columns")

        guest = Client(args.base_url)
        for path in ("dashboard.php", "add_entry.php", "view_entries.php",
                     "edit_entry.php?id=1", "delete_entry.php?id=1"):
            status, headers, _ = guest.request(path)
            assert status == 302 and headers["Location"] == "login.php"
        passed("All student pages require an authenticated session")

        signup = Client(args.base_url)
        signup_data = dict(accounts[0], password=password, confirm_password=password, role="admin")
        invalid = dict(signup_data, email="invalid-email")
        status, _, body = signup.request("signup.php", invalid)
        assert status == 200 and "valid email" in body and db("user", username=accounts[0]["username"]) is None
        invalid = dict(signup_data, confirm_password="mismatch")
        status, _, body = signup.request("signup.php", invalid)
        assert "Passwords do not match" in body
        status, headers, _ = signup.request("signup.php", signup_data)
        assert status == 302 and headers["Location"] == "login.php?registered=1"
        user = db("user", username=accounts[0]["username"])
        assert user["role"] == "user" and user["password"] != password
        status, _, body = signup.request("signup.php", signup_data)
        assert "already registered" in body
        passed("Signup, validation, duplicate prevention, hashing, and default User role")

        client = Client(args.base_url)
        status, _, body = client.request("login.php", {
            "username": "' OR 1=1 --", "password": "wrong"
        })
        assert status == 200 and "Incorrect username or password" in body
        login(client, accounts[0], "user")
        passed("User login, session rotation, and invalid-login rejection")

        token = client.token("add_entry.php")
        values = {
            "full_name": marker + " José O'Neil <script>alert(1)</script> 🧑",
            "year_level": "2nd Year", "section": "QA_" + marker, "course": "BSIT",
            "address": "QA_ADDRESS_" + marker + "\n100% Test Street & Avenue, Quezon City",
            "description": "Legacy notes " + marker, "category": "QA category", "entry_date": "2026-10-15",
        }
        status, _, _ = client.request("add_entry.php", values)
        assert status == 403
        for change in (
            {"full_name": " ", "section": "", "address": ""},
            {"year_level": "5th Year", "course": "INVALID"},
            {"full_name": "N" * 201, "section": "S" * 101, "address": "A" * 5001},
            {"entry_date": "2026-02-30"},
            {"entry_date": "2026-10-15\0"},
        ):
            status, _, body = client.request("add_entry.php", dict(values, csrf_token=token, **change))
            assert status == 422 and 'role="alert"' in body, (list(change), status, body[:600])
        malformed = dict(values, csrf_token=token)
        del malformed["full_name"]
        malformed["full_name[]"] = "Array input"
        status, _, _ = client.request("add_entry.php", malformed)
        assert status == 422
        assert db("snapshot")["entries"] == before["entries"]
        passed("Required fields, dropdown allowlists, lengths, dates, malformed input, and CSRF")

        status, headers, _ = client.request("add_entry.php", dict(values, csrf_token=token))
        assert status == 303 and headers["Location"] == "view_entries.php"
        record = db("find", user_id=user["id"], full_name=values["full_name"])
        assert record
        record_id = record["id"]
        for field, value in values.items():
            assert record[field] == value, "Save mismatch in " + field
        assert record["created_by"] == user["id"]
        status, _, body = client.request("view_entries.php")
        assert status == 200 and has_record(body, record_id)
        assert "added successfully" in body and "&lt;script&gt;" in body
        assert "<script>alert(1)</script>" not in body
        table_body = html.unescape(body.split("<tbody>", 1)[1].split("</tbody>", 1)[0])
        for field, value in values.items():
            assert value in table_body, "Display mismatch in " + field
        status, _, body = client.request("view_entries.php")
        assert "added successfully" not in body, "Flash message was not consumed"
        passed("Actual insert, all fields round-trip, ownership, Unicode, escaping, and success notification")

        for term in (marker, "2nd Year", values["section"], "BSIT", "QA_ADDRESS_" + marker, "100%"):
            status, _, body = client.request("view_entries.php?" + urllib.parse.urlencode({"search": term}))
            assert status == 200 and has_record(body, record_id), "Search failed: " + term
        for filters in (
            {"year_level": "2nd Year"}, {"course": "BSIT"},
            {"search": marker, "year_level": "2nd Year", "course": "BSIT"},
        ):
            status, _, body = client.request("view_entries.php?" + urllib.parse.urlencode(filters))
            assert status == 200 and has_record(body, record_id)
        for filters in (
            {"search": marker, "year_level": "4th Year"},
            {"search": marker, "course": "BSCS"},
            {"search": "' OR 1=1 --"},
        ):
            status, _, body = client.request("view_entries.php?" + urllib.parse.urlencode(filters))
            assert status == 200 and not has_record(body, record_id) and "No student records matched" in body
        status, _, body = client.request("view_entries.php?course=INVALID")
        assert "valid year level and course filter" in body
        passed("Search across five student fields, literal wildcards, individual and combined filters")

        status, _, body = client.request("dashboard.php")
        assert '<h2>Total Records</h2><p class="stat-number">' + str(before["entries"] + 1) + "</p>" in body
        assert "Records by Year Level" in body and "Records by Course" in body
        assert '<h2>Complete Student Records</h2><p class="stat-number">0</p>' not in body
        passed("Dashboard totals and year/course distributions")

        edit_path = "edit_entry.php?id=" + str(record_id)
        edit_token = client.token(edit_path)
        status, _, body = client.request(edit_path)
        assert 'value="2nd Year" selected' in body and 'value="BSIT" selected' in body
        assert html.escape(values["section"]) in body and "&lt;script&gt;" in body
        updated = dict(values, full_name="Updated " + marker + " 李 Santos", year_level="3rd Year",
                       section="NEW-" + marker, course="Other", address="Updated Address\nManila")
        status, _, _ = client.request(edit_path, updated)
        assert status == 403 and db("entry", id=record_id)["full_name"] == values["full_name"]
        status, _, body = client.request(edit_path, dict(updated, csrf_token=edit_token, course="INVALID"))
        assert status == 422 and "Select a valid course" in body
        status, _, _ = client.request(edit_path, dict(updated, csrf_token=edit_token))
        assert status == 303
        edited = db("entry", id=record_id)
        for field, value in updated.items():
            assert edited[field] == value, "Edit mismatch in " + field
        assert edited["created_by"] == record["created_by"] and edited["created_at"] == record["created_at"]
        status, _, body = client.request("view_entries.php")
        assert "updated successfully" in body and updated["full_name"] in body
        table_body = html.unescape(body.split("<tbody>", 1)[1].split("</tbody>", 1)[0])
        for field, value in updated.items():
            assert value in table_body, "Updated display mismatch in " + field
        for bad_id in ("", "-1", "1.5", "not-a-number", "999999999"):
            for page in ("edit_entry.php", "delete_entry.php"):
                status, headers, _ = client.request(page + "?id=" + bad_id)
                assert status == 302 and headers["Location"] == "view_entries.php"
        passed("Actual edit of every student field, prefilled form, validation, metadata, and bad IDs")

        db("admin", **accounts[1], password=password)
        admin = Client(args.base_url)
        login(admin, accounts[1], "admin")
        for path in ("view_entries.php", edit_path, "delete_entry.php?id=" + str(record_id)):
            status, _, _ = admin.request(path)
            assert status == 200
        passed("Admin login and the existing shared record permissions")

        delete_path = "delete_entry.php?id=" + str(record_id)
        delete_token = client.token(delete_path)
        assert db("entry", id=record_id), "GET confirmation deleted the record"
        status, _, body = client.request(delete_path, {})
        assert status == 403 and db("entry", id=record_id)
        status, _, body = client.request(delete_path)
        assert updated["full_name"] in body and "Yes, Delete Record" in body
        status, headers, _ = client.request(delete_path, {"csrf_token": delete_token})
        assert status == 303 and headers["Location"] == "view_entries.php"
        assert db("entry", id=record_id) is None
        status, _, body = client.request("view_entries.php")
        assert "deleted successfully" in body and not has_record(body, record_id)
        passed("Delete confirmation, CSRF rejection, actual deletion, and notification")

        for logged_in in (client, admin):
            status, headers, _ = logged_in.request("logout.php")
            assert status == 302 and headers["Location"] == "login.php"
            status, headers, _ = logged_in.request("dashboard.php")
            assert status == 302 and headers["Location"] == "login.php"
        passed("Logout invalidates both User and Admin sessions")
    finally:
        db("cleanup", accounts=accounts)
        after = db("snapshot")
        assert before == after, "Existing database data changed or temporary test data remains"
        print("PASS: Temporary test data removed; all pre-test records and accounts unchanged", flush=True)
    print(str(len(groups) + 1) + " integration check groups passed.", flush=True)


if __name__ == "__main__":
    main()
