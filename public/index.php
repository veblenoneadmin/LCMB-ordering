<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// --- Analytics Metrics ---
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalInstallations = $pdo->query("SELECT COUNT(*) FROM split_installation UNION SELECT COUNT(*) FROM ductedinstallations")->rowCount();
$totalClients = $pdo->query("SELECT COUNT(DISTINCT customer_email) FROM orders")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();

// --- Fetch dispatch data ---
$stmt = $pdo->query("
    SELECT d.id, d.date, d.hours, p.name AS personnel_name
    FROM dispatch d
    LEFT JOIN personnel p ON p.id = d.personnel_id
");
$dispatch = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate events array for JS
$events = [];
$colors = ['#3b82f6','#9333ea','#f97316','#059669','#ef4444','#eab308','#8b5cf6','#0ea5e9'];
$personnelColors = [];
$colorIndex = 0;

foreach ($dispatch as $row) {
    $person = $row['personnel_name'] ?: 'Unknown';
    if (!isset($personnelColors[$person])) {
        $personnelColors[$person] = $colors[$colorIndex % count($colors)];
        $colorIndex++;
    }

    $events[] = [
        "id" => $row['id'],
        "title" => $person . " (" . $row['hours'] . "h)",
        "start" => $row['date'],
        "color" => $personnelColors[$person],
        "extendedProps" => [
            "personnel" => $person,
            "hours" => $row['hours'],
            "date" => $row['date']
        ]
    ];
}

ob_start();
?>

<!-- Google Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<!-- Analytics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Total Orders</h3> 
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $totalOrders ?></p>
    </div>

    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Installations</h3>
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $totalInstallations ?></p>
    </div>

    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Clients</h3>
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $totalClients ?></p>
    </div>

    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Pending Orders</h3>
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $pendingOrders ?></p>
    </div>
</div>

<!-- Calendar + Pending Orders Panel -->
<div class="grid grid-cols-1 lg:grid-cols-[70%_30%] gap-4 mb-6">

    <!-- Left: Calendar -->
    <div class="bg-white p-4 rounded-xl shadow border border-gray-100 relative col-span-1 pr-4" id="calendarContainer">

        <div class="flex items-center justify-between mb-2">
            <h2 class="text-lg font-semibold text-gray-700">Dispatch Board</h2>

            <!-- Fullscreen Button -->
            <button id="fullscreenBtn" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm flex items-center gap-1 hover:bg-indigo-700 transition">
                <span class="material-icons" id="fsIcon">fullscreen</span>
                <span id="fsText">Fullscreen</span>
            </button>
        </div>

        

        <div id="calendar" class="rounded-lg border border-gray-200 w-full h-[500px]"></div>
    </div>

    <!-- Right Panel: Pending Orders -->
    <div class="bg-white p-4 rounded-xl shadow border border-gray-100 h-[500px] overflow-y-auto">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Pending Orders</h2>

        <?php
        $pendingList = $pdo->query("SELECT id, created_at FROM orders WHERE status='pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <?php if (empty($pendingList)): ?>
            <p class="text-gray-500 text-sm">No pending orders.</p>
        <?php else: ?>
            <?php foreach ($pendingList as $o): 
                $dateTime = date('d M h:i A', strtotime($o['created_at']));
                $date = date('d M', strtotime($o['created_at']));
                $time = date('h:i A', strtotime($o['created_at']));
            ?>
            <div class="mb-4 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                <p class="text-xs text-indigo-600 font-semibold">New Order</p>
                <p class="text-lg font-bold text-gray-800">#<?= $o['id'] ?></p>

                <p class="text-sm text-gray-500"><?= $date ?></p>
                <p class="text-xs text-gray-400"><?= $time ?></p>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Modal (Modern Centered) -->
<div id="dispatchModal" class="fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm hidden flex items-center justify-center z-50 h-screen">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-2xl w-96 max-w-full mx-2 transform scale-95 opacity-0 transition-all duration-300 ease-out" id="dispatchModalContent">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4" id="modalTitle">Dispatch Details</h2>
        <div class="space-y-2 text-gray-700 dark:text-gray-300">
            <p id="modalDate" class="text-sm"></p>
            <p id="modalPersonnel" class="text-sm"></p>
            <p id="modalHours" class="text-sm"></p>
        </div>
        <div class="flex justify-end mt-6">
            <button id="closeModal" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition">Close</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>

<style>
#dispatchModal.show #dispatchModalContent {
    transform: scale(1);
    opacity: 1;
}

#calendar.fullscreen {
    position: fixed !important;
    top: 0;
    left: 0;
    width: 100vw !important;
    height: 100vh !important;
    background: white;
    z-index: 9999;
    padding: 20px;
    box-sizing: border-box;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let allEvents = <?= json_encode($events) ?>;
    let calendarEl = document.getElementById('calendar');
    const modal = document.getElementById('dispatchModal');
    const modalContent = document.getElementById('dispatchModalContent');
    const closeBtn = document.getElementById('closeModal');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 650,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: allEvents,
        displayEventTime: false,
        eventClick: function(info) {
            const e = info.event.extendedProps;
            document.getElementById('modalTitle').innerText = info.event.title;
            document.getElementById('modalDate').innerText = "Date: " + e.date;
            document.getElementById('modalPersonnel').innerText = "Personnel: " + e.personnel;
            document.getElementById('modalHours').innerText = "Hours: " + e.hours;
            
            modal.classList.remove('hidden');
            void modalContent.offsetWidth; 
            modal.classList.add('show');
        }
    });

    calendar.render();

    closeBtn.addEventListener('click', () => {
        modal.classList.remove('show');
        setTimeout(() => modal.classList.add('hidden'), 300);
    });

    document.getElementById('personnelFilter').addEventListener('change', function() {
        const val = this.value;
        let filteredEvents = val === 'all' ? allEvents : allEvents.filter(ev => ev.extendedProps.personnel === val);
        calendar.removeAllEvents();
        calendar.addEventSource(filteredEvents);
    });

    // Fullscreen Toggle
    const btn = document.getElementById("fullscreenBtn");
    const fsIcon = document.getElementById("fsIcon");
    const fsText = document.getElementById("fsText");
    let isFullscreen = false;

    btn.addEventListener("click", () => {
        isFullscreen = !isFullscreen;

        if (isFullscreen) {
            calendarEl.classList.add("fullscreen");
            fsIcon.textContent = "fullscreen_exit";
            fsText.textContent = "Exit Fullscreen";
        } else {
            calendarEl.classList.remove("fullscreen");
            fsIcon.textContent = "fullscreen";
            fsText.textContent = "Fullscreen";
        }

        calendar.updateSize();
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("LCMB Dashboard", $content);
?>
