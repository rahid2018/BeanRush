
<?php
function create_unique_id() {
    // Using uniqid() with more entropy and additional randomness
    $prefix = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2);
    return $prefix . uniqid('', true) . mt_rand(1000, 9999);
}
?>
