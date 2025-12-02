<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

ob_start();
?>

<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-700">Booking Calendar</h2>

        <select id="calendarFilter" class="p-2 border rounded-lg text-sm">
            <option value="orders">Orders</option>
            <option value="personnel">Personnel</option>
        </select>
    </div>

    <div id="calendar" class="rounded-lg border"></div>
</div>

<!-- FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

<style>
    #calendar {
        height: 700px;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const calendarEl = document.getElementById('calendar');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        themeSystem: 'standard',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: function(fetchInfo, success, fail) {
            const filter = document.getElementById('calendarFilter').value;

            // Now relative to public folder
            fetch("fetch_" + filter + ".php")
                .then(res => res.json())
                .then(data => success(data))
                .catch(err => fail(err));
        },
        eventColor: "#3b82f6",
        eventTextColor: "#fff",
        displayEventTime: false,
        editable: false,
        selectable: false
    });

    calendar.render();

    document.getElementById('calendarFilter').addEventListener('change', () => {
        calendar.refetchEvents();
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Booking Calendar", $content);
?>
