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

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@20..48,300,0,0" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background: #f8f9fa; font-family: "Roboto", sans-serif; }

        /* Sidebar text slightly smaller + clearer icons */
        .sidebar-link {
            display:flex; align-items:center;
            padding:5px 8px; gap:6px;
            font-weight:500; color:#475569;
            font-size:0.82rem; transition:0.25s;
        }

        .sidebar-link .material-symbols-sharp {
            font-variation-settings:'wght' 300;
            font-size:20px; 
            color:#667085;
        }

        .sidebar-link:hover {
            background:#0f1d88;
            color:#fff;
        }

        .sidebar-link:hover .material-symbols-sharp {
            color:white;
        }

        .sidebar-active {
            background:#0f1d88;
            color:white !important;
        }

        .sidebar-active .material-symbols-sharp {
            color:white !important;
        }

        /* Move line closer under dropdown */
        .user-divider {
            margin-top: 2px;
            margin-bottom: 4px;
        }

        /* Top navbar clearer text + icons */
        header .material-symbols-sharp {
            font-variation-settings:'wght' 300;
            color:#475569 !important;
        }

        header input::placeholder {
            color:#6b7280;
            opacity:1;
        }

        header input {
            border-color:#cbd5e1 !important;
        }

        /* Sidebar title icon */
        .sidebar-title-icon {
            font-variation-settings:'wght' 300;
            font-size:22px;
            color:#475569;
        }

        /* Gap under Home */
        .nav-gap {
            margin-top: 6px;
        }
    </style>
</head>

<body class="flex">

<!-- SIDEBAR -->
<aside class="w-52 bg-white shadow-md p-5 flex flex-col gap-3 rounded-2xl sticky top-4 h-[calc(100vh-2rem)] ml-4 text-sm">

    <h2 class="text-gray-700 text-lg font-semibold mb-3 flex items-center gap-2">
        <span class="material-symbols-sharp sidebar-title-icon">grid_view</span> LCMB
    </h2>

    <!-- User Dropdown -->
    <div class="w-full mb-1">
        <button id="userToggleBtn" class="w-full flex items-center justify-between py-2 rounded-lg hover:bg-gray-100 transition">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 bg-indigo-600 text-white flex items-center justify-center rounded-full font-medium text-sm">
                    <?= $initial ?>
                </div>
                <p class="font-semibold text-gray-600"><?= $user ?></p>
            </div>
            <span class="material-symbols-sharp">expand_more</span>
        </button>
        <div id="userMenu" class="hidden mt-1 rounded-lg">
            <a href="profile.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
                <span class="material-symbols-sharp text-gray-500">person</span> Profile
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
                <span class="material-symbols-sharp text-gray-500">settings</span> Settings
            </a>
            <a href="logout.php" class="flex items-center gap-3 px-1.5 py-2 text-red-600 rounded hover:bg-gray-100">
                <span class="material-symbols-sharp text-red-500">logout</span> Logout
            </a>
        </div>
    </div>

    <!-- Line closer to dropdown -->
    <div class="border-b border-gray-200 user-divider"></div>

    <!-- NAV ITEMS -->
<?php
$navItems = array(
    array('href'=>'index.php','icon'=>'dashboard','label'=>'Home','page'=>'index'),

    /* Add gap after Home */
    array('separator'=>true),

    array('href'=>'create_order.php','icon'=>'add_shopping_cart','label'=>'Create Order','page'=>'create_order'),
    array('href'=>'orders.php','icon'=>'receipt_long','label'=>'Orders','page'=>'orders'),
    array('href'=>'personnel.php','icon'=>'group','label'=>'Personnel','page'=>'personnel'),
    array('href'=>'materials.php','icon'=>'inventory_2','label'=>'Materials','page'=>'materials')
);

foreach($navItems as $item) {

    if (isset($item['separator'])) {
        echo '<div class="nav-gap"></div>';
        continue;
    }

    $active = ($activePage == $item['page']) ? 'sidebar-active' : '';

    echo '<a href="'.$item['href'].'" class="sidebar-link '.$active.'">
            <span class="material-symbols-sharp">'.$item['icon'].'</span> '.$item['label'].'
          </a>';
}
?>

<!-- Installation Dropdown -->
<div class="w-full">
    <button id="installToggleBtn" class="w-full flex items-center justify-between py-2 rounded-lg hover:bg-gray-100 transition">
        <div class="flex items-center gap-3">
            <span class="material-symbols-sharp">construction</span>
            <p class="font-medium text-gray-600">Installation</p>
        </div>
        <span class="material-symbols-sharp">expand_more</span>
    </button>

    <div id="installMenu" class="hidden mt-1 rounded-lg">
        <a href="ducted_installations.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
            <span class="material-symbols-sharp text-gray-500">view_in_ar</span> Ducted Installation
        </a>
        <a href="split_installations.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
            <span class="material-symbols-sharp text-gray-500">ac_unit</span> Split Installation
        </a>
    </div>
</div>

</aside>

<script>
document.getElementById("userToggleBtn").onclick = () =>
    document.getElementById("userMenu").classList.toggle("hidden");

document.getElementById("installToggleBtn").onclick = () =>
    document.getElementById("installMenu").classList.toggle("hidden");
</script>

<!-- MAIN CONTENT -->
<main class="flex-1 p-4 md:p-8 overflow-auto">
    <header class="flex items-center justify-between mb-4">
        <div>
            <div class="text-sm text-gray-600 flex items-center gap-1">
                <span class="material-symbols-sharp text-gray-500">dashboard</span>
                Dashboard / <span class="text-gray-900 font-medium"><?= $titleEsc ?></span>
            </div>
            <h1 class="text-2xl font-semibold text-gray-800"><?= $titleEsc ?></h1>
        </div>

        <div class="flex items-center gap-4">
            <div class="relative">
                <input type="text" placeholder="Search here" class="border rounded-lg px-4 py-2 pl-10 w-48 md:w-64 bg-white shadow-sm focus:outline-none">
                <span class="material-symbols-sharp absolute left-3 top-2 text-gray-500">search</span>
            </div>
            <span class="material-symbols-sharp cursor-pointer">account_circle</span>
            <span class="material-symbols-sharp cursor-pointer">settings</span>
            <span class="material-symbols-sharp cursor-pointer">notifications</span>
        </div>
    </header>

    <div><?= $content ?></div>
</main>

</body>
</html>
<?php } ?>
