<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

ob_start();
?>

<div class="bg-white p-4 rounded-2xl shadow-lg border border-gray-200">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-700">Booking Calendar</h2>
        <select id="calendarFilter" class="p-2 border rounded-lg text-sm">
            <option value="dispatch">Dispatch</option>
        </select>
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
    background-color: #4F46E5 !important;
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
            fetch("fetch_dispatch.php") // Make sure this returns JSON events from dispatch DB
                .then(res => res.json())
                .then(data => success(data))
                .catch(err => fail(err));
        },
        displayEventTime: false,
        editable: false,
        selectable: true,
        eventClick: function(info) {
            document.getElementById('modalTitle').textContent = info.event.title;
            document.getElementById('modalDate').textContent = "Date: " + info.event.startStr;
            document.getElementById('modalPersonnel').textContent = "Personnel: " + (info.event.extendedProps.personnel || 'N/A');
            document.getElementById('modalHours').textContent = "Hours: " + (info.event.extendedProps.hours || '0');
            modal.classList.remove('hidden');
        }
    });

    calendar.render();

    document.getElementById('calendarFilter').addEventListener('change', () => {
        calendar.refetchEvents();
    });

    closeModalBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    // Close modal when clicking outside
    modal.addEventListener('click', (e) => {
        if(e.target === modal) modal.classList.add('hidden');
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Booking Calendar", $content);
?>
