<<<<<<< HEAD

<?php

session_start();

require_once __DIR__ . '/config/app.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/auth/login.php');
}

=======
<?php
header('Location: app.php');
>>>>>>> 4197540e5e8fce389b5fc2d8f25bd2146863a132
exit;
