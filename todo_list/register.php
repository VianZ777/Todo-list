<?php
// Start session
session_start();

// Include database connection
require_once 'config.php';

// Ensure $mysqli is defined
if (!isset($mysqli) || !$mysqli) {
    die("Database connection error. Please check your configuration.");
}

// Initialize variables
$username = $password = $confirm_password = "";
$username_err = $password_err = $confirm_password_err = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST["username"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm your password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords do not match.";
        }
    }

    // Check input errors before inserting into database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err)) {
        $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ss", $param_username, $param_password);
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Hash password
            if ($stmt->execute()) {
                // Redirect to login.php with a success message
                header("location: login.php?message=registration_success");
                exit();
            } else {
                echo "Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }

    $mysqli->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
        }
        body {
            background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a1a 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            position: relative;
            overflow-x: hidden;
        }
        /* Efek bintang */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            pointer-events: none;
            z-index: 0;
            background: transparent url('https://raw.githubusercontent.com/JulianLaval/canvas-space-background/master/images/stars.png') repeat;
            opacity: 0.25;
            animation: moveStars 120s linear infinite;
        }
        @keyframes moveStars {
            0% { background-position: 0 0; }
            100% { background-position: 1000px 1000px; }
        }
        .card {
            background: rgba(24, 29, 56, 0.97);
            border-radius: 18px;
            box-shadow: 0 4px 32px 0 rgba(44, 62, 80, 0.25), 0 0 24px #3a1c71 inset;
            border: 2px solid #3a1c71;
            position: relative;
            z-index: 1;
        }
        .card-header {
            background: linear-gradient(90deg, #3a1c71 0%, #5e3be1 100%) !important;
            color: #ffe066 !important;
            border-radius: 16px 16px 0 0 !important;
            box-shadow: 0 0 12px #3a1c71;
        }
        .card-header h2 {
            font-weight: 700;
            text-shadow: 0 0 12px #ffe066, 0 0 2px #fff;
        }
        .card-body {
            color: #ffe066;
        }
        .form-label {
            color: #ffe066;
            font-weight: 600;
        }
        .form-control {
            background: #232a36;
            color: #ffe066;
            border: 1.5px solid #3a1c71;
        }
        .form-control:focus {
            border-color: #ffe066;
            box-shadow: 0 0 8px #ffe06688;
            background: #232a36;
            color: #ffe066;
        }
        .btn-primary {
            background: linear-gradient(90deg, #3a1c71 0%, #5e3be1 100%);
            border: none;
            color: #ffe066;
            font-weight: 700;
            box-shadow: 0 0 8px #3a1c71;
        }
        .btn-primary:hover {
            background: #ffe066;
            color: #3a1c71;
        }
        .btn-secondary {
            background: #232a36;
            color: #ffe066;
            border: 1.5px solid #3a1c71;
        }
        .btn-secondary:hover {
            background: #ffe066;
            color: #3a1c71;
        }
        .card-footer {
            background: #232a36;
            color: #ffe066;
            border-radius: 0 0 16px 16px;
            border-top: 1.5px solid #3a1c71;
        }
        .card-footer a {
            color: #ffe066;
            font-weight: 600;
            text-shadow: 0 0 4px #3a1c71;
        }
        .card-footer a:hover {
            color: #fff;
        }
        /* Scrollbar angkasa */
        ::-webkit-scrollbar {
            width: 10px;
            background: #232a36;
        }
        ::-webkit-scrollbar-thumb {
            background: #3a1c71;
            border-radius: 8px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #5e3be1;
        }
        /* Efek glow pada judul */
        .card-header h2 {
            text-shadow: 0 0 12px #ffe066, 0 0 2px #fff;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header text-center">
                        <h2>Register</h2>
                        <p>Please fill this form to create an account.</p>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" name="username" id="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                                <div class="invalid-feedback"><?php echo $username_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                                <div class="invalid-feedback"><?php echo $password_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                                <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Submit</button>
                                <button type="reset" class="btn btn-secondary">Reset</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <p>Already have an account? <a href="login.php">Login here</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>