<?php
$title = "CINEM4 - Lupa Password";
include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';
?>

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success d-flex align-items-center gap-2">
<i class="bi bi-envelope-check"></i>
<span><?= $_SESSION['success']; ?></span>
</div>
<?php unset($_SESSION['success']); endif; ?>


<?php if(isset($_SESSION['error'])): ?>
<div class="alert alert-danger d-flex align-items-center gap-2">
<i class="bi bi-exclamation-circle"></i>
<span><?= $_SESSION['error']; ?></span>
</div>
<?php unset($_SESSION['error']); endif; ?>

<div class="container py-5" style="max-width:500px; min-height:calc(100vh - 140px);">

<h3 class="text-light mb-4">Lupa Password</h3>

<?php if(isset($_GET['success'])): ?>
<div class="alert alert-success">
Link reset password telah dikirim ke email Anda.
</div>
<?php endif; ?>

<form method="post" action="actions/forgot_password_action.php">

<div class="mb-3">
<label class="form-label text-light">Email</label>
<input type="email" name="email" class="form-control bg-dark text-light border-secondary" required>
</div>

<button class="btn btn-light w-100">
Kirim Link Reset
</button>

</form>

</div>

<?php include '../app/views/partials/public/footer.php'; ?>