<?php
// index.php (Root)
require_once 'config/app.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('auth/login.php');
}
