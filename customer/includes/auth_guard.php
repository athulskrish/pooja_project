<?php
require_once __DIR__ . '/../config/db.php';
Auth::requireAuth($_SERVER['REQUEST_URI'] ?? 'index.php');
