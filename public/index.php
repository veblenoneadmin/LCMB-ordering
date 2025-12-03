<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch dispatch data
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>

<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mb-6">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Dispatch Calendar</h2>

    <div class="mb-4 flex items-center gap-3">
        <label class="font-medium">Filter by Personnel:</label>
        <select id="personnelFilter" class="p-2 border rounded-lg text-sm">
            <option value="all">All</option>
            <?php foreach(array_unique(array_column($dispatch,'personnel_name')) as $person): ?>
            <option value="<?= htmlspecialchars($person) ?>"><?= htmlspecialchars($person) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="calendar" class="rounded-lg border"></div>
</div>

<!-- Modal -->
<div id="dispatchModal" class="fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-2xl w-96 max-w-full mx-2 animate-fadeIn">
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

<style>
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
.animate-fadeIn { animation: fadeIn 0.2s ease-out; }
#calendar { height: 650px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let allEvents = <?= json_encode($events) ?>;
    let calendarEl = document.getElementById('calendar');

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
            document.getElementById('dispatchModal').classList.remove('hidden');
        }
    });

    calendar.render();

    document.getElementById('closeModal').addEventListener('click', () => {
        document.getElementById('dispatchModal').classList.add('hidden');
    });

    // Personnel filter
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
renderLayout("Dispatch Calendar", $content);
?>
