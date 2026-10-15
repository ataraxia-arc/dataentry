<?php

session_start();
require_once __DIR__ . "/config/database.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$username = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = is_string($_POST["username"] ?? null) ? trim($_POST["username"]) : "";
    $email = is_string($_POST["email"] ?? null) ? trim($_POST["email"]) : "";
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";
    $confirm_password = is_string($_POST["confirm_password"] ?? null) ? $_POST["confirm_password"] : "";

    if ($username === "" || $email === "" || $password === "" || $confirm_password === "") {
        $error = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } elseif (strlen($username) > 50) {
        // The existing latin1 connection stores each submitted byte as one character.
        $error = "Username is too long. Please use a shorter username.";
    } elseif (strlen($email) > 100) {
        $error = "Email address is too long.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (strlen($password) > 72 || strpos($password, "\0") !== false) {
        $error = "Password is too long or contains an unsupported character.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "ss", $username, $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $alreadyRegistered = mysqli_num_rows($result) > 0;
            mysqli_stmt_close($stmt);

            if ($alreadyRegistered) {
                $error = "Username or email is already registered.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $role = "user";
                $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hashedPassword, $role);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                header("Location: login.php?registered=1");
                exit();
            }
        } catch (mysqli_sql_exception $exception) {
            if ($exception->getCode() === 1062) {
                $error = "Username or email is already registered.";
            } else {
                error_log("Sign up database operation failed (code " . $exception->getCode() . ").");
                $error = "Unable to create account. Please try again later.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sign Up | Data Entry System</title>

    <link
        rel="stylesheet"
        href="/G2/css/style.css?v=10"
    >

</head>

<body class="auth-page">

    <div class="auth-container">

        <div class="auth-header">

            <h1>
                Create Account
            </h1>

            <p>
                Register to use the Data Entry System
            </p>

        </div>


        <?php if ($error !== ""): ?>

            <div class="error">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Choose a username"
                    value="<?php echo htmlspecialchars($username); ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Minimum 6 characters"
                    required
                >

            </div>


            <div class="form-group">

                <label for="confirm_password">
                    Confirm Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Enter password again"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn auth-btn"
            >
                Create Account
            </button>

        </form>


        <div class="auth-switch">

            <p>
                Already have an account?
                <a href="login.php">
                    Login
                </a>
            </p>

        </div>


        <div class="auth-back">

            <a href="index.php">
                ← Back to Home
            </a>

        </div>

    </div>

</body>

</html>