<?php

session_start();

$isLoggedIn = isset($_SESSION["user_id"]);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Data Entry System</title>

    <link
        rel="stylesheet"
        href="/G2/css/style.css?v=7"
    >

</head>

<body>


    <!-- NAVIGATION -->

    <nav class="navbar">

        <h2>
            Data Entry System
        </h2>

        <div class="nav-links">

            <a href="index.php">
                Home
            </a>

            <?php if ($isLoggedIn): ?>

                <a href="dashboard.php">
                    Dashboard
                </a>

                <a href="logout.php">
                    Logout
                </a>

            <?php else: ?>

                <a href="login.php">
                    Login
                </a>

            <?php endif; ?>

        </div>

    </nav>


    <!-- HERO SECTION -->

    <main class="home-main">

        <section class="hero">

            <div class="hero-content">

                <span class="hero-label">
                    Simple • Organized • Secure
                </span>

                <h1>
                    Manage Your Data
                    <br>
                    With Ease
                </h1>

                <p>
                    A simple data entry management system for
                    creating, viewing, updating, and organizing
                    records in one place.
                </p>


                <div class="hero-buttons">

                    <?php if ($isLoggedIn): ?>

                        <a
                            href="dashboard.php"
                            class="btn hero-primary"
                        >
                            Go to Dashboard
                        </a>

                    <?php else: ?>

                        <a
                            href="login.php"
                            class="btn hero-primary"
                        >
                            Login to System
                        </a>

                    <?php endif; ?>


                    <a
                        href="#features"
                        class="btn hero-secondary"
                    >
                        Learn More
                    </a>

                </div>

            </div>

        </section>


        <!-- FEATURES -->

        <section
            class="home-features"
            id="features"
        >

            <div class="section-heading">

                <h2>
                    System Features
                </h2>

                <p>
                    Everything needed for basic data management.
                </p>

            </div>


            <div class="feature-grid">


                <div class="feature-card">

                    <div class="feature-number">
                        01
                    </div>

                    <h3>
                        Add Records
                    </h3>

                    <p>
                        Create and save new data entries
                        directly into the system.
                    </p>

                </div>


                <div class="feature-card">

                    <div class="feature-number">
                        02
                    </div>

                    <h3>
                        View & Search
                    </h3>

                    <p>
                        Browse stored records and quickly
                        search for the information you need.
                    </p>

                </div>


                <div class="feature-card">

                    <div class="feature-number">
                        03
                    </div>

                    <h3>
                        Edit Records
                    </h3>

                    <p>
                        Update existing information whenever
                        changes are required.
                    </p>

                </div>


                <div class="feature-card">

                    <div class="feature-number">
                        04
                    </div>

                    <h3>
                        Delete Records
                    </h3>

                    <p>
                        Remove unnecessary records using
                        a confirmation process.
                    </p>

                </div>


            </div>

        </section>

    </main>


    <footer class="home-footer">

        <p>
            Data Entry Management System
        </p>

    </footer>


</body>

</html>