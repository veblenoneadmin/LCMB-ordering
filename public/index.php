<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch dispatch data once
$stmt = $pdo->query("
    SELECT d.id, d.date, d.hours, p.name AS personnel_name
    FROM dispatch d
    LEFT JOIN personnel p ON p.id = d.personnel_id
");
$dispatch = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare JS events array
$events = [];
foreach ($dispatch as $row) {
    $events[] = [
        "title" => $row['personnel_name'] . " (" . $row['hours'] . "h)",
        "start" => $row['date']
    ];
}

ob_start();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@3.10.5/dist/fullcalendar.min.css">
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@3.10.5/dist/fullcalendar.min.js"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>

<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mb-6">
    <h2 class="text-xl font-semibold text-gray-700 mb-2">Calendar 3 (FullCalendar v3)</h2>
    <div id="calendar3"></div>
</div>

<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
    <h2 class="text-xl font-semibold text-gray-700 mb-2">Calendar 4 (FullCalendar v5)</h2>
    <div id="calendar4"></div>
</div>

<script>
const dispatchEvents = <?= json_encode($events); ?>;

// -------------------------
// Calendar 3 (FullCalendar v3)
// -------------------------
$('#calendar3').fullCalendar({
    header: {
        left: 'prev,next today',
        center: 'title',
        right: ''
    },
    height: 650,
    editable: false,
    events: dispatchEvents,
    eventColor: '#2563eb',
    eventTextColor: '#fff'
});

// -------------------------
// Calendar 4 (FullCalendar v5)
// -------------------------
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar4');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 650,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        events: dispatchEvents,
        eventColor: '#9333ea',
        eventTextColor: '#fff',
        displayEventTime: false
    });

    calendar.render();
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Dispatch Calendars", $content);
?>
