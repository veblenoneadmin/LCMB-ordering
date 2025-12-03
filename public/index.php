<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

ob_start();
?>

<h1 class="text-xl font-bold mb-4">Calendar Test Page</h1>

<!-- 1) FULLCALENDAR -->
<h2 class="text-lg font-semibold mt-6 mb-2 text-blue-600">1. FullCalendar Test</h2>
<div id="fcCalendar" class="border rounded-lg" style="height: 700px;"></div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    try {
        let fcEl = document.getElementById('fcCalendar');
        let fc = new FullCalendar.Calendar(fcEl, {
            initialView: 'dayGridMonth',
            height: "auto"
        });
        fc.render();
        console.log("FullCalendar Loaded");
    } catch (e) {
        console.error("FullCalendar ERROR:", e);
    }
});
</script>


<!-- 2) TUI CALENDAR -->
<h2 class="text-lg font-semibold mt-6 mb-2 text-green-600">2. TUI Calendar Test</h2>

<link rel="stylesheet" href="https://uicdn.toast.com/tui.calendar/latest/tui-calendar.min.css">
<script src="https://uicdn.toast.com/tui.calendar/latest/tui-calendar.min.js"></script>

<div id="tuiCalendar" style="height: 800px; border: 1px solid #ddd; border-radius: 10px;"></div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    try {
        const tuiCal = new tui.Calendar('#tuiCalendar', {
            defaultView: 'month',
            usageStatistics: false
        });
        console.log("TUI Calendar Loaded");
    } catch (e) {
        console.error("TUI Calendar ERROR:", e);
    }
});
</script>


<!-- 3) SIMPLE HTML CALENDAR -->
<h2 class="text-lg font-semibold mt-6 mb-2 text-purple-600">3. Simple HTML Calendar Test</h2>

<style>
.simple-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
}
.simple-grid div {
    padding: 10px;
    text-align: center;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
}
</style>

<div id="simpleCalendar" class="simple-grid"></div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    try {
        let container = document.getElementById("simpleCalendar");
        let date = new Date();
        let year = date.getFullYear();
        let month = date.getMonth();
        let firstDay = new Date(year, month, 1).getDay();
        let numDays = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < firstDay; i++) container.appendChild(document.createElement("div"));
        for (let d = 1; d <= numDays; d++) {
            let cell = document.createElement("div");
            cell.textContent = d;
            container.appendChild(cell);
        }
        console.log("Simple Calendar Loaded");
    } catch (e) {
        console.error("Simple Calendar ERROR:", e);
    }
});
</script>


<!-- 4) FLATPICKR INLINE -->
<h2 class="text-lg font-semibold mt-6 mb-2 text-red-600">4. Flatpickr Inline Calendar Test</h2>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<input id="flatCal" />

<script>
document.addEventListener("DOMContentLoaded", function () {
    try {
        flatpickr("#flatCal", { inline: true, defaultDate: "today" });
        console.log("Flatpickr Loaded");
    } catch (e) {
        console.error("Flatpickr ERROR:", e);
    }
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Calendar Tests", $content);
?>
