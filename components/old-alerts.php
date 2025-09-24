<?php if(isset($_SESSION['success_msg']) || isset($_SESSION['warning_msg']) || isset($_SESSION['info_msg']) || isset($_SESSION['error_msg'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if(isset($_SESSION['success_msg'])): ?>
        <?php foreach($_SESSION['success_msg'] as $msg): ?>
            Swal.fire({
                icon: 'success',
                title: '<?= addslashes($msg) ?>',
                showConfirmButton: false,
                timer: 3000
            });
        <?php endforeach; ?>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['warning_msg'])): ?>
        <?php foreach($_SESSION['warning_msg'] as $msg): ?>
            Swal.fire({
                icon: 'warning',
                title: '<?= addslashes($msg) ?>',
                showConfirmButton: false,
                timer: 3000
            });
        <?php endforeach; ?>
        <?php unset($_SESSION['warning_msg']); ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['info_msg'])): ?>
        <?php foreach($_SESSION['info_msg'] as $msg): ?>
            Swal.fire({
                icon: 'info',
                title: '<?= addslashes($msg) ?>',
                showConfirmButton: false,
                timer: 3000
            });
        <?php endforeach; ?>
        <?php unset($_SESSION['info_msg']); ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['error_msg'])): ?>
        <?php foreach($_SESSION['error_msg'] as $msg): ?>
            Swal.fire({
                icon: 'error',
                title: '<?= addslashes($msg) ?>',
                showConfirmButton: false,
                timer: 3000
            });
        <?php endforeach; ?>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>
</script>
<?php endif; ?>