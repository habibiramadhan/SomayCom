<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart'])) {
    $_SESSION['cart'] = json_decode($_POST['cart'], true);
    header('Location: checkout.php');
    exit;
}
header('Location: products.php');
exit; 