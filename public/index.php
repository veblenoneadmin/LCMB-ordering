<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

ob_start();
?>

<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-700">Booking Calendar</h2>

        <!-- Filter dropdown (will add dynamic events later) -->
        <select id="calendarFilter" class="p-2 border rounded-lg text-sm">
            <option value="orders">Orders</option>
            <option value="personnel">Personnel</option>
        </select>
    </div>

    <!-- Calendar container -->
    <div id="calendar" class="rounded-lg border"></div>
</div>

<!-- FullCalendar CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

<style>
    /* Tailwind + FullCalendar tweaks */
    #calendar {
        height: 700px; /* fixed height so it displays */
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        themeSystem: 'standard',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: [], // empty for now, just show the calendar
        eventColor: "#3b82f6",
        eventTextColor: "#fff",
        displayEventTime: false,
        editable: false,
        selectable: false
    });

    calendar.render();

    // optional: filter dropdown (no data yet)
    document.getElementById('calendarFilter').addEventListener('change', () => {
        // later you can fetch events based on this filter
        calendar.refetchEvents();
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Booking Calendar", $content);
?>
