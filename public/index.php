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

    <div id="calendar"></div>
</div>

<!-- FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let calendarEl = document.getElementById('calendar');

    let calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        selectable: false,
        themeSystem: 'standard',

        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },

        events: function(fetchInfo, success, fail) {
            let filter = document.getElementById('calendarFilter').value;

            fetch("public/fetch_" + filter + ".php")
                .then(r => r.json())
                .then(data => success(data))
                .catch(err => fail(err));
        },

        eventColor: "#3b82f6",
        eventTextColor: "#fff",
        displayEventTime: false,
    });

    calendar.render();

    document.getElementById('calendarFilter').addEventListener('change', () => {
        calendar.refetchEvents();
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Calendar", $content);
?>
