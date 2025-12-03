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

$eventsJson = json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
ob_start();
?>

<div class="p-4">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Dispatch Calendar</h2>

    <div id="calendar" class="rounded-xl border bg-white shadow-md"></div>
</div>

<!-- FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

<style>
    #calendar {
        height: 700px; /* Fixed height ensures FullCalendar renders */
        max-width: 100%;
        margin: auto;
    }

    /* Modern modal */
    .modal-bg {
        background: rgba(0,0,0,0.5);
    }

    .modal-card {
        background: white;
        border-radius: 0.75rem;
        max-width: 400px;
        width: 100%;
        padding: 1.5rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }
</style>

<!-- Modal -->
<div id="eventModal" class="hidden fixed inset-0 flex items-center justify-center modal-bg z-50">
    <div class="modal-card">
        <h3 class="text-lg font-semibold mb-2">Dispatch Details</h3>
        <p id="modalPersonnel" class="mb-1"></p>
        <p id="modalDate" class="mb-1"></p>
        <p id="modalHours" class="mb-2"></p>
        <button id="closeModal" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Close</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: <?= $eventsJson ?>,
        eventColor: '#3b82f6',
        eventTextColor: '#fff',
        displayEventTime: false,
        height: 'auto',
        eventClick: function(info) {
            document.getElementById('modalPersonnel').textContent = "Personnel: " + info.event.extendedProps.personnel;
            document.getElementById('modalDate').textContent = "Date: " + info.event.extendedProps.date;
            document.getElementById('modalHours').textContent = "Hours: " + info.event.extendedProps.hours;
            document.getElementById('eventModal').classList.remove('hidden');
        }
    });

    calendar.render();

    // Close modal
    document.getElementById('closeModal').addEventListener('click', () => {
        document.getElementById('eventModal').classList.add('hidden');
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Dispatch Calendar", $content);
?>
