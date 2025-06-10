<?php
session_start();
if (isset($_SESSION['user'])) {
    // langsung masuk halaman task jika sudah login
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '/todo_list/tasks.php';
    header("Location: $redirect");
    exit();
}

// Koneksi ke database
$host = 'localhost';
$db   = 'todo_list'; // rubah sesuai nama database kita
$user = 'root';
$pass = '';
$conn = @new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("<div style='color:red;text-align:center;margin-top:30px;'>Database connection failed: " . htmlspecialchars($conn->connect_error) . "<br>Pastikan database <b>$db</b> sudah dibuat di MySQL.</div>");
}

// Only set error message on POST, not on GET/refresh
// $error_message = '';
// Ambil redirect dari GET atau POST, default ke path absolut
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : '/todo_list/tasks.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Query user dari database
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $hashed_password);
        $stmt->fetch();
        // Jika password di database sudah di-hash, gunakan password_verify
        if (password_verify($password, $hashed_password)) {
            $_SESSION['user'] = $username;
            $_SESSION['user_id'] = $user_id; // Set user_id ke session
            header("Location: $redirect");
            exit();
        } else {
            $_SESSION['flash_error'] = 'Invalid username or password.';
        }
    } else {
        $_SESSION['flash_error'] = 'Invalid username or password.';
    }
    $stmt->close();
    header("Location: login.php" . (isset($_GET['redirect']) ? "?redirect=" . urlencode($_GET['redirect']) : ""));
    exit();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
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
            color: #ffe066;
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
        .login-container {
            background: rgba(24, 29, 56, 0.97);
            padding: 36px 30px 30px 30px;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(44,62,80,0.25), 0 0 24px #3a1c71 inset;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            border: 2px solid #3a1c71;
        }
        .login-icon {
            font-size: 3.2rem;
            color: #ffe066;
            display: block;
            margin: 0 auto 12px auto;
            filter: drop-shadow(0 2px 8px #0008);
            text-shadow: 0 0 12px #ffe066, 0 0 2px #fff;
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
        .error, .alert-danger {
            color: #ff6b6b !important;
            background: transparent;
            border: none;
        }
        .alert-success {
            background: #232a36;
            color: #ffe066;
            border: 1.5px solid #3a1c71;
        }
        .space-link {
            color: #ffe066;
            text-decoration: underline;
            font-weight: 600;
            text-shadow: 0 0 4px #3a1c71;
        }
        .space-link:hover {
            color: #fff;
        }
        .planet {
            position: absolute;
            width: 48px;
            height: 48px;
            top: -24px;
            right: -24px;
            z-index: 2;
            opacity: 0.85;
            animation: floatPlanet 4s ease-in-out infinite alternate;
        }
        @keyframes floatPlanet {
            from { transform: translateY(0);}
            to { transform: translateY(18px);}
        }
        @media (max-width: 500px) {
            .login-container { padding: 18px 6vw 18px 6vw; }
        }
    </style>
</head>
<body>
    <div class="d-flex justify-content-center align-items-center" style="min-height:100vh;">
        <div class="login-container shadow-lg">
            <i class="fa-solid fa-user-astronaut login-icon"></i>
            <img src="https://cdn-icons-png.flaticon.com/512/616/616554.png" class="planet" alt="planet" />
            <h2 class="text-center mb-4" style="letter-spacing:1px; text-shadow:0 0 12px #ffe066, 0 0 2px #fff;">Login</h2>
            <?php
            if (isset($_SESSION['flash_error'])) {
                echo '<p class="error text-center">' . htmlspecialchars($_SESSION['flash_error']) . '</p>';
                unset($_SESSION['flash_error']);
            }
            if (isset($_GET['message']) && $_GET['message'] == 'registration_success') {
                echo "<div class='alert alert-success'>Registration successful. Please log in.</div>";
            }
            ?>
            <form method="POST" action="">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 px-2">
                            <i class="fa fa-user-astronaut text-warning" style="transition:filter 0.2s;" id="icon-username"></i>
                        </span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required
                            onfocus="document.getElementById('icon-username').style.filter='drop-shadow(0 0 8px #ffe066)';"
                            onblur="document.getElementById('icon-username').style.filter='';">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 px-2">
                            <i class="fa fa-moon text-warning" style="transition:filter 0.2s;" id="icon-password"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required
                            onfocus="document.getElementById('icon-password').style.filter='drop-shadow(0 0 8px #ffe066)';"
                            onblur="document.getElementById('icon-password').style.filter='';">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 shadow-sm">Login</button>
            </form>
            <p class="text-center mt-3">Don't have an account? <a href="register.php" class="space-link">Register here</a>.</p>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>