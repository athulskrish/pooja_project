<?php
/**
 * AuthController - Handles Login, Register, Logout and RBAC Checks
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

class AuthController {
    public static function handleLogin(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                setFlash('danger', 'Please enter both email and password.');
                return;
            }

            $user = User::authenticate($email, $password);
            if ($user) {
                $_SESSION['user'] = $user;
                setFlash('success', "Welcome back, {$user['full_name']}!");

                $redirect = $_GET['redirect'] ?? 'index.php';
                header("Location: {$redirect}");
                exit;
            } else {
                setFlash('danger', 'Invalid email or password. Please try demo credentials.');
            }
        }
    }

    public static function handleRegister(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $phone    = trim($_POST['phone_number'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($fullName) || empty($email) || empty($phone) || empty($password)) {
                setFlash('danger', 'All fields are required.');
                return;
            }

            if (User::register($fullName, $email, $phone, $password)) {
                setFlash('success', 'Registration successful! You are now logged in.');
                header('Location: index.php');
                exit;
            } else {
                setFlash('danger', 'Email or Phone already exists in the system.');
            }
        }
    }

    public static function logout(): void {
        unset($_SESSION['user']);
        setFlash('info', 'You have been successfully logged out.');
        header('Location: login.php');
        exit;
    }

    public static function requireLogin(): void {
        if (!isset($_SESSION['user'])) {
            setFlash('warning', 'Please sign in to proceed.');
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }
}
