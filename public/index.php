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

<!-- Dispatch Calendar -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mb-6">
    <h2 class="text-xl font-semibold text-gray-700 mb-2">Dispatch Board</h2>
    <div class="mb-2">
        <label class="mr-2 font-medium">Filter by Personnel:</label>
        <select id="personnelFilter" class="p-2 border rounded-lg text-sm">
            <option value="all">All</option>
            <?php foreach(array_unique(array_column($dispatch,'personnel_name')) as $person): ?>
            <option value="<?= htmlspecialchars($person) ?>"><?= htmlspecialchars($person) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div id="calendar" class="rounded-lg border"></div>
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
/* Fade + Scale In Animation */
#dispatchModal.show #dispatchModalContent {
    transform: scale(1);
    opacity: 1;
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
            void modalContent.offsetWidth; // trigger reflow
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
});
</script>

<?php
$content = ob_get_clean();
renderLayout("LCMB Dashboard", $content);
?>
