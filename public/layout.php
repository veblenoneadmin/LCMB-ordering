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
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@20..48,200,0,0" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body { background: #f8f9fa; font-family: "Roboto", sans-serif; }
        .sidebar-link { display:flex; align-items:center; padding:5px 8px; gap:6px;
                        font-weight:500; color:#64748B; font-size:0.875rem; transition:0.25s; }
        .sidebar-link .material-symbols-sharp { font-variation-settings:'wght' 300; font-size:20px; color:#484b4f; }
        .sidebar-link:hover { background:#0f1d88; color:#fff; }
        .sidebar-link:hover .material-symbols-sharp { color:#fff; }
        .sidebar-active { background:#0f1d88; color:#fff !important; }
        .sidebar-active .material-symbols-sharp { color:white !important; }

        .dropdown-show { display:block !important; animation:fadeIn .15s ease-in-out; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(-3px);} to{opacity:1;transform:translateY(0);} }

        table th { height:42px; line-height:42px; text-align:center; font-size:15px !important; }
        table td { font-size:14px; vertical-align:middle; text-align:center; }
        table th:first-child, table td:first-child { text-align:left; padding-left:.75rem; }
        .qty-input, .installation-qty { width: 42px; padding: 2px; text-align: center; }
        .plus-btn, .minus-btn { font-size:12px; padding:2px 6px; }
        .qty-wrapper { display: flex; align-items: center; gap: 4px !important; }
        .pers-check { transform:scale(0.9); }
        #appointment_date::placeholder { color:#999; opacity:1; }
        #calendar { height: 700px; background-color: #ffffff; border-radius: 12px; padding: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 10px; }
    </style>
</head>

<body class="flex">

<!-- SIDEBAR -->
<aside class="w-52 bg-white shadow-md p-5 flex flex-col gap-3 rounded-2xl sticky top-4 h-[calc(100vh-2rem)] ml-4 text-sm">
    <h2 class="text-gray-700 text-lg font-semibold mb-4 flex items-center gap-2">
        <span class="material-symbols-sharp">grid_view</span> LCMB
    </h2>

    <!-- User Dropdown -->
    <div class="w-full mb-2">
        <button id="userToggleBtn" class="w-full flex items-center justify-between py-2 rounded-lg hover:bg-gray-100 transition">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 bg-indigo-600 text-white flex items-center justify-center rounded-full font-medium text-sm">
                    <?= $initial ?>
                </div>
                <p class="font-semibold text-gray-500"><?= $user ?></p>
            </div>
            <span class="material-symbols-sharp">expand_more</span>
        </button>
        <div id="userMenu" class="hidden mt-1 rounded-lg">
            <a href="profile.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
                <span class="material-symbols-sharp text-gray-500 text-base">person</span> Profile
            </a>
           
            <a href="logout.php" class="flex items-center gap-3 px-1.5 py-2 text-red-600 rounded hover:bg-gray-100">
                <span class="material-symbols-sharp text-red-500 text-base">logout</span> Logout
            </a>
        </div>
    </div>

    <div class="border-b border-gray-200 mt-1"></div>
<br>
    <!-- NAV ITEMS -->
<?php
$navItems = array(
    array('href'=>'index.php','icon'=>'dashboard','label'=>'home','page'=>'index'),
    array('href'=>'create_order.php','icon'=>'inventory','label'=>'Create Order','page'=>'create_order'),
    array('href'=>'orders.php','icon'=>'content_paste_go','label'=>'Orders','page'=>'orders'),
    array('href'=>'personnel.php','icon'=>'people_alt','label'=>'Personnel','page'=>'personnel'),
    array('href'=>'materials.php','icon'=>'deployed_code','label'=>'Materials','page'=>'materials')
);

foreach($navItems as $item) {
    $active = $activePage==$item['page'] ? 'sidebar-active' : '';
    echo '<a href="'.$item['href'].'" class="sidebar-link '.$active.'">
            <span class="material-symbols-sharp">'.$item['icon'].'</span> '.$item['label'].'
          </a>';
}
?>

<!-- Installation Dropdown -->
<div class="w-full">
    <button id="installToggleBtn" class="w-full flex items-center justify-between py-2 rounded-lg hover:bg-gray-100 transition">
        <div class="flex items-center gap-1">
            <span class="material-symbols-sharp">construction</span>
            <p class="font-medium text-gray-500">Installation</p>
        </div>
        <span class="material-symbols-sharp">expand_more</span>
    </button>
    <div id="installMenu" class="hidden mt-1 rounded-lg">
        <a href="ducted_installations.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
            <span class="material-symbols-sharp text-gray-500 text-base">view_in_ar</span> Ducted Installation
        </a>
        <a href="split_installations.php" class="flex items-center gap-3 px-1.5 py-2 text-gray-700 rounded hover:bg-gray-100">
            <span class="material-symbols-sharp text-gray-500 text-base">ac_unit</span> Split Installation
        </a>
    </div>
</div>

</aside>

<script>
document.getElementById("userToggleBtn").onclick = () => {
    document.getElementById("userMenu").classList.toggle("hidden");
}
document.getElementById("installToggleBtn").onclick = () => {
    document.getElementById("installMenu").classList.toggle("hidden");
}
</script>

<!-- MAIN CONTENT -->
<main class="flex-1 p-4 md:p-8 overflow-auto">
    <header class="flex items-center justify-between mb-4">
        <div>
            <div class="text-sm text-gray-500 flex items-center gap-1">
                <span class="material-symbols-sharp text-gray-400 text-base">dashboard</span>
                Dashboard / <span class="text-gray-800 font-medium"><?= $titleEsc ?></span>
            </div>
            <h1 class="text-2xl font-semibold text-gray-800"><?= $titleEsc ?></h1>
        </div>
        <div class="flex items-center gap-4">
            <div class="relative">
                <input type="text" placeholder="Search here" class="border rounded-lg px-4 py-2 pl-10 w-48 md:w-64 bg-transparent shadow-sm focus:outline-none focus:border-indigo-500">
                <span class="material-symbols-sharp absolute left-3 top-2 text-gray-400 text-base">search</span>
            </div>
        
            <span class="material-symbols-sharp text-gray-600 hover:text-gray-900 cursor-pointer">settings</span>
            <span class="material-symbols-sharp text-gray-600 hover:text-gray-900 cursor-pointer">notifications</span>
        </div>
    </header>

    <div>
        <?= $content ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>
</body>
</html>
<?php
}
?>
