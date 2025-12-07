<?php
function renderLayout(string $title, string $content, string $activePage = ""): void {
    $titleEsc = htmlspecialchars($title);
    $user = htmlspecialchars($_SESSION['name'] ?? 'Guest');
    $initial = strtoupper(substr($user, 0, 1));
    $role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $titleEsc ?></title>

    <!-- Google Fonts & Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@20..48,200,0,0" rel="stylesheet">
    
    <!-- TailwindCSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Custom Styles -->
    <style>
        body { background: #f8f9fa; font-family: "Roboto", sans-serif; }

        /* Sidebar */
        aside {
            font-size: 0.875rem; /* smaller font */
        }
        .sidebar-link {
            display:flex; align-items:center; gap:6px;
            font-weight:500; color:#64748B; transition:0.25s; font-size:0.875rem;
        }
        .sidebar-link .material-symbols-sharp { font-size:20px; color:#94A3B8; font-variation-settings: 'wght' 200; }
        .sidebar-link:hover { background:#1976d2; color:white; }
        .sidebar-link:hover .material-symbols-sharp { color:white; }
        .sidebar-active { background:#1976d2; color:white !important; }
        .sidebar-active .material-symbols-sharp { color:white !important; }

        /* Dropdown */
        .dropdown-show { display:block !important; animation:fadeIn .15s ease-in-out; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(-3px);} to{opacity:1;transform:translateY(0);} }

        /* Tables */
        table th { height:42px; line-height:42px; text-align:center; font-size:14px !important; }
        table td { font-size:13px; vertical-align:middle; text-align:center; }
        table th:first-child, table td:first-child { text-align:left; padding-left:.75rem; }

        .qty-input, .installation-qty { width: 42px; padding: 2px; text-align: center; }
        .plus-btn, .minus-btn { font-size:12px; padding:2px 6px; }
        .qty-wrapper { display: flex; align-items: center; gap: 4px !important; }
        .pers-check { transform:scale(0.9); }

        /* Input Material Style */
        .material-input {
            width: 100%;
            padding: 1rem 0.75rem 0.25rem 0.75rem;
            border: 1px solid #cbd5e0; border-radius: 0.5rem;
            background-color: transparent; outline: none; transition: all 0.2s ease-in-out;
        }
        .material-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .material-input + label {
            position: absolute; top:50%; left:0.75rem; transform:translateY(-50%);
            color:#9ca3af; pointer-events:none; transition: all 0.2s ease-in-out;
        }
        .material-input:focus + label,
        .material-input:not(:placeholder-shown) + label {
            top:0.25rem; left:0.75rem; font-size:0.75rem; color:#3b82f6;
            background:white; padding: 0 0.25rem;
        }

        /* FullCalendar container */
        #calendar {
            height: 700px;
            background-color: #ffffff;
            border-radius: 12px;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-top: 10px;
        }
    </style>

    <!-- FullCalendar -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>
</head>
<body class="flex">

<!-- SIDEBAR -->
<aside class="w-52 bg-white shadow-md p-5 flex flex-col gap-3 rounded-2xl sticky top-4 h-[calc(100vh-2rem)] ml-4">
    <h2 class="text-gray-700 text-lg font-semibold mb-4 flex items-center gap-2">
        <span class="material-symbols-sharp font-thin text-gray-500">grid_view</span> LCMB
    </h2>

    <!-- User Dropdown -->
    <div class="w-full mb-2">
        <button id="userToggleBtn" class="w-full flex items-center justify-between py-2 rounded-lg hover:bg-gray-100 transition">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 bg-indigo-600 text-white flex items-center justify-center rounded-full font-medium text-sm"><?= $initial ?></div>
                <p class="font-semibold text-gray-500"><?= $user ?></p>
            </div>
            <span class="material-symbols-sharp font-thin text-gray-500">expand_more</span>
        </button>
        <div id="userMenu" class="hidden mt-1 rounded-lg">
            <a href="profile.php" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100">
                <span class="material-symbols-sharp font-thin text-gray-500">person</span> Profile
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100">
                <span class="material-symbols-sharp font-thin text-gray-500">settings</span> Settings
            </a>
            <a href="logout.php" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100 text-red-600">
                <span class="material-symbols-sharp font-thin text-red-500">logout</span> Logout
            </a>
        </div>
    </div>

    <div class="border-b border-gray-200 mt-1"></div>
home
</span>
    <!-- NAV ITEMS -->
    <?php
    $navItems = [
        ['href'=>'index.php','icon'=>'dashboard','label'=>'home','page'=>'index'],
        ['href'=>'create_order.php','icon'=>'edit_square','label'=>'Create Order','page'=>'create_order'],
        ['href'=>'orders.php','icon'=>'inventory','label'=>'Orders','page'=>'orders']
        ['href'=>'personnel.php','icon'=>'group_add','label'=>'personnel','page'=>'personnel']
        ['href'=>'products.php','icon'=>'wysiwygg','label'=>'Materials','page'=>'materials']
    ];
    foreach($navItems as $nav):
        $active = $activePage==$nav['page']?'sidebar-active':'';
    ?>
        <a href="<?= $nav['href'] ?>" class="flex items-center w-full gap-3 px-3 py-2 rounded-lg font-medium transition-colors <?= $active ?> hover:bg-blue-600 hover:text-white">
            <span class="material-symbols-sharp font-thin <?= $active ? 'text-white':'text-gray-500' ?>"><?= $nav['icon'] ?></span>
            <span class="<?= $active ? 'text-white':'text-gray-700' ?>"><?= $nav['label'] ?></span>
        </a>
    <?php endforeach; ?>

    <!-- INSTALLATION DROPDOWN -->
    <div class="w-full">
        <button id="installationToggleBtn" class="flex items-center w-full justify-between px-3 py-2 rounded-lg font-medium text-gray-700 hover:bg-blue-600 hover:text-white transition-colors">
            <div class="flex items-center gap-3">
                <span class="material-symbols-sharp font-thin text-gray-500">engineering</span>
                Installation
            </div>
            <span class="material-symbols-sharp font-thin text-gray-500">expand_more</span>
        </button>
        <div id="installationMenu" class="hidden mt-1 flex flex-col gap-1">
            <a href="ducted_installations.php" class="flex items-center gap-3 px-5 py-2 rounded-lg text-gray-700 hover:bg-blue-600 hover:text-white transition-colors">
                <span class="material-symbols-sharp font-thin text-gray-500">view_in_ar</span> Ducted Installation
            </a>
            <a href="split_installations.php" class="flex items-center gap-3 px-5 py-2 rounded-lg text-gray-700 hover:bg-blue-600 hover:text-white transition-colors">
                <span class="material-symbols-sharp font-thin text-gray-500">ac_unit</span> Split Installation
            </a>
        </div>
    </div>
</aside>

<script>
document.getElementById("userToggleBtn").onclick = () => {
    document.getElementById("userMenu").classList.toggle("hidden");
};
document.getElementById("installationToggleBtn").onclick = () => {
    document.getElementById("installationMenu").classList.toggle("hidden");
};
</script>

<!-- MAIN CONTENT -->
<main class="flex-1 p-4 md:p-8 overflow-auto">
    <header class="flex items-center justify-between mb-4">
        <div>
            <div class="text-sm text-gray-500 flex items-center gap-1">
                <span class="material-symbols-sharp font-thin text-gray-400 text-base">dashboard</span>
                Dashboard / <span class="text-gray-800 font-medium"><?= $titleEsc ?></span>
            </div>
            <h1 class="text-2xl font-semibold text-gray-800"><?= $titleEsc ?></h1>
        </div>
        <div class="flex items-center gap-4">
            <div class="relative">
                <input type="text" placeholder="Search here"
                    class="border rounded-lg px-4 py-2 pl-10 w-48 md:w-64 bg-transparent shadow-sm focus:outline-none focus:border-indigo-500">
                <span class="material-symbols-sharp font-thin absolute left-3 top-2 text-gray-400 text-base">search</span>
            </div>
            <span class="material-symbols-sharp font-thin text-gray-600 hover:text-gray-900 cursor-pointer">account_circle</span>
            <span class="material-symbols-sharp font-thin text-gray-600 hover:text-gray-900 cursor-pointer">settings</span>
            <span class="material-symbols-sharp font-thin text-gray-600 hover:text-gray-900 cursor-pointer">notifications</span>
        </div>
    </header>

    <div><?= $content ?></div>
</main>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>
</body>
</html>
<?php
}
?>
