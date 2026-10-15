<?php

session_start();
require_once __DIR__ . "/config/database.php";

/* If already logged in, go directly to dashboard */
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = is_string($_POST["username"] ?? null) ? trim($_POST["username"]) : "";
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

    if ($username === "" || $password === "") {
        $error = "Please enter your username and password.";
    } else {
        try {
            $stmt = mysqli_prepare($conn, "SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user["password"])) {
                session_regenerate_id(true);
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];

                header("Location: dashboard.php");
                exit();
            }

            $error = "Incorrect username or password.";
        } catch (mysqli_sql_exception $exception) {
            error_log("Login database operation failed (code " . $exception->getCode() . ").");
            $error = "Unable to log in. Please try again later.";
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

    <title>Login | Welcome BSIT
         </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body class="login-page">

    <div class="login-container">

        <div class="login-header">

            <h1>Data Entry System</h1>

            <p>
                Sign in to continue
            </p>

        </div>


        <?php if ($error != ""): ?>

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
                    placeholder="Enter username"
                    required
                    autocomplete="username"
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
                    placeholder="Enter password"
                    required
                    autocomplete="current-password"
                >

            </div>


            <button
                type="submit"
                class="btn login-btn"
            >
                Login
            </button>

        </form>

<div class="signup-option">
    <span>Don't have an account?</span>

    <a href="signup.php">
        Sign Up
    </a>
</div>
        <div class="login-footer">

            <p>
                Data Entry Management System
            </p>

        </div>

    </div>

</body>

</html>