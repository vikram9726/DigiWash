<?php 
require_once 'config.php'; 
$currentPage = basename($_SERVER['PHP_SELF']);
$isIndex = ($currentPage === 'index.php' || $currentPage === '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash — Laundry Reimagined</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; }</style>

    <!-- Firebase SDK (Compat version for simplicity) -->
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>
    
    <!-- GSAP for animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans selection:bg-indigo-500 selection:text-white overflow-x-hidden relative flex flex-col min-h-screen">

    <!-- Background Gradients -->
    <div class="fixed inset-0 overflow-hidden -z-10 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] rounded-full bg-indigo-300/30 blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[600px] h-[600px] rounded-full bg-blue-300/20 blur-[120px]"></div>
    </div>

    <!-- Navbar -->
    <nav class="fixed top-0 w-full z-50 transition-all duration-300 <?= $isIndex ? 'bg-transparent py-6' : 'bg-white/80 backdrop-blur-md shadow-sm border-b border-slate-200 py-4' ?>" id="navbar">
        <div class="max-w-7xl mx-auto px-6 md:px-12 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-blue-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/30">
                    <i class="material-icons-outlined">local_laundry_service</i>
                </div>
                <a href="index.php" class="text-xl font-bold tracking-tight text-slate-800 hover:text-indigo-600 transition-colors">DigiWash</a>
            </div>

            <div class="hidden md:flex items-center gap-6 font-medium text-slate-600">
                <a href="index.php#how" class="hover:text-indigo-600 transition-colors">How it works</a>
                <a href="index.php#features" class="hover:text-indigo-600 transition-colors">Features</a>
                <a href="about.php" class="<?= $currentPage === 'about.php' ? 'text-indigo-600' : 'hover:text-indigo-600' ?> transition-colors">About</a>
                <a href="contact.php" class="<?= $currentPage === 'contact.php' ? 'text-indigo-600' : 'hover:text-indigo-600' ?> transition-colors">Contact</a>
            </div>

            <div class="hidden md:flex">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="user/dashboard.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-full font-medium transition-transform hover:scale-105 active:scale-95 shadow-lg shadow-indigo-600/30">
                        Dashboard
                    </a>
                <?php else: ?>
                    <button class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-2.5 rounded-full font-medium transition-transform hover:scale-105 active:scale-95 shadow-lg shadow-slate-900/20" onclick="openAuthModal()">
                        Login / Signup
                    </button>
                <?php endif; ?>
            </div>
            
            <div class="md:hidden flex items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="user/dashboard.php" class="text-indigo-600 flex items-center">
                        <i class="material-icons-outlined">dashboard</i>
                    </a>
                <?php else: ?>
                    <button class="text-slate-800" onclick="openAuthModal()">
                        <i class="material-icons-outlined">login</i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Adjust layout for non-index pages so content isn't hidden under the fixed nav -->
    <?php if (!$isIndex): ?>
        <div class="pt-24 pb-8 flex-grow">
    <?php endif; ?>
