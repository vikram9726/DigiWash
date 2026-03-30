<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash | Premium Laundry & Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        .hero-gradient { background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

    <!-- NAVIGATION -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-2">
                    <i class="material-icons-outlined text-indigo-600 text-3xl">local_laundry_service</i>
                    <a href="index.php" class="font-black text-xl tracking-tight text-slate-900">DigiWash</a>
                </div>
                <div class="hidden md:flex space-x-8 font-semibold text-sm text-slate-600">
                    <a href="index.php" class="hover:text-indigo-600 transition">Home</a>
                    <a href="about.php" class="hover:text-indigo-600 transition">About</a>
                    <a href="contact.php" class="hover:text-indigo-600 transition">Contact</a>
                </div>
                <div>
                    <a href="index.php" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-md hover:bg-indigo-700 transition">Login / Register</a>
                </div>
            </div>
        </div>
    </nav>
