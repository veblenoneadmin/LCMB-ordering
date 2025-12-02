<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

ob_start();
?>

<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Booking Calendar Test</h2>
    <div id="calendar" class="rounded-lg border"></div>
</div>

<!-- FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

<style>
    #calendar {
        height: 700px; /* important so calendar has visible height */
    }
</style>

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
        height: 'auto',
        events: [
            { title: 'Test Order', start: '2025-12-02' },
            { title: 'Test Personnel', start: '2025-12-05' }
        ],
        eventColor: '#3b82f6',
        eventTextColor: '#fff'
    });

    calendar.render();
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Calendar Test", $content);
?>
