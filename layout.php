<form action="auth/logout.php" method="POST">
    <?= csrf_field() ?>

    <button type="submit">
        Logout
    </button>
</form>