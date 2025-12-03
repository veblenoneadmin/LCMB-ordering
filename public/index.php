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
$colors = ['#3b82f6','#9333ea','#f97316','#059669','#ef4444','#eab308','#8b5cf6','#0ea5e9']; // sample colors
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

// Convert PHP events array to JSON for JS
$eventsJson = json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

ob_start();
?>

<div class="bg-white p-4 rounded-2xl shadow-lg border border-gray-200">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-700">Booking Calendar</h2>
    </div>
    <div id="calendar" class="rounded-xl overflow-hidden"></div>
</div>

<!-- Dispatch Modal -->
<div id="dispatchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg p-6 w-80 max-w-sm relative">
        <h3 id="modalTitle" class="text-lg font-semibold text-gray-700 mb-2">Dispatch Info</h3>
        <p id="modalDate" class="text-gray-600 mb-1"></p>
        <p id="modalPersonnel" class="text-gray-600 mb-1"></p>
        <p id="modalHours" class="text-gray-600 mb-4"></p>
        <button id="closeModal" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700">
            <span class="material-icons">close</span>
        </button>
    </div>
</div>

<!-- FullCalendar CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

<style>
#calendar { height: 700px; }

.fc .fc-event {
    border-radius: 12px;
    color: #fff !important;
    padding: 4px 8px;
    font-weight: 500;
    font-size: 0.875rem;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    cursor: pointer;
    transition: transform 0.1s ease, box-shadow 0.2s ease;
}

.fc .fc-event:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.fc-toolbar { margin-bottom: 1rem; }

.fc-toolbar button {
    background-color: #EEF2F7;
    color: #475569;
    border-radius: 0.5rem;
    font-weight: 500;
    padding: 0.5rem 0.75rem;
    transition: background-color 0.2s ease;
}

.fc-toolbar button:hover {
    background-color: #4F46E5;
    color: #fff;
}

.fc-toolbar-title {
    font-weight: 600;
    font-size: 1.25rem;
}

/* Modal styling */
#dispatchModal .bg-white {
    animation: fadeIn 0.2s ease-in-out;
}
@keyframes fadeIn { from{opacity:0; transform: translateY(-10px);} to{opacity:1; transform: translateY(0);} }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    const modal = document.getElementById('dispatchModal');
    const closeModalBtn = document.getElementById('closeModal');

    const events = <?= $eventsJson ?>;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        themeSystem: 'standard',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: events,
        displayEventTime: false,
        editable: false,
        selectable: true,
        eventClick: function(info) {
            document.getElementById('modalTitle').textContent = info.event.title;
            document.getElementById('modalDate').textContent = "Date: " + info.event.extendedProps.date;
            document.getElementById('modalPersonnel').textContent = "Personnel: " + info.event.extendedProps.personnel;
            document.getElementById('modalHours').textContent = "Hours: " + info.event.extendedProps.hours;
            modal.classList.remove('hidden');
        }
    });

    calendar.render();

    closeModalBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    modal.addEventListener('click', (e) => {
        if(e.target === modal) modal.classList.add('hidden');
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Booking Calendar", $content);
?>
