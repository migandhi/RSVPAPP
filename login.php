<?php
session_start(); // Start session to check for errors
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="stylesheet" href="style.css">
    <style>
        .login-container { max-width: 400px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; background-color: #f9f9f9; }
        .login-container h1 { text-align: center; margin-bottom: 20px; }
        .login-container .form-group { margin-bottom: 15px; }
        .login-container label { display: block; margin-bottom: 5px; font-weight: bold; }
        .login-container input[type=text],
        .login-container input[type=password] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
        .login-container button { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 16px; }
        .login-container button:hover { background-color: #0056b3; }
        .error-message { color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 10px; border-radius: 3px; margin-bottom: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Admin Login</h1>

        <?php
        // Display login errors if any
        if (isset($_SESSION['login_error'])) {
            echo '<div class="error-message">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
            unset($_SESSION['login_error']); // Clear error after displaying
        }
        ?>

        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <p style="text-align: center; margin-top: 20px;"><a href="index.php">Back to RSVP Form</a></p>
    </div>
</body>
</html>