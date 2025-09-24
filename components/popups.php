<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['popup_message'])) {
    echo '<div class="popup-message">'
        . htmlspecialchars($_SESSION['popup_message'])
        . '</div>';
    unset($_SESSION['popup_message']);
}
?>
<style>
.popup-message {
    position: fixed;
    top: 87px; /* adjust based on your navbar height */
    left: 50%;
    transform: translateX(-50%);
    background-color: #252525;
    color: #f3961c;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0px 4px 10px rgba(172, 172, 172, 0.2);
    z-index: 9999;
    cursor: pointer;
    animation: fadeIn 0.3s ease;
    opacity: 2;
    transition: opacity 0.5s ease;
}
.popup-message.hide {
    opacity: 0;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translate(-50%, -10px); }
    to { opacity: 1; transform: translate(-50%, 0); }
}

</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const popup = document.querySelector('.popup-message');
    if (popup) {
        setTimeout(() => {
            popup.classList.add('hide');
            setTimeout(() => popup.remove(), 500);
        }, 3000); // 3 seconds before it starts hiding
        popup.addEventListener('click', () => popup.remove());
    }
});
</script>
