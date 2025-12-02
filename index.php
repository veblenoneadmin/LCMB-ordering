<?php
require_once __DIR__.'/layout.php';
require_once config.php;

// Fetch personnel
$personnel = $pdo->query("SELECT id, name FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch booked orders for calendar
$events = $pdo->query("SELECT d.id, d.personnel_id, d.date, d.hours, o.customer_name, o.order_number
                       FROM dispatch d
                       JOIN orders o ON d.order_id = o.id")->fetchAll(PDO::FETCH_ASSOC);

// Transform for FullCalendar
$fcEvents = [];
foreach($events as $ev){
    $fcEvents[] = [
        'title' => "Order: {$ev['order_number']} ({$ev['hours']}h)",
        'start' => $ev['date'],
        'extendedProps' => [
            'personnel_id' => $ev['personnel_id'],
            'customer_name' => $ev['customer_name'],
        ]
    ];
}

ob_start();
?>

<div class="p-6 bg-gray-100 min-h-screen">
    <div class="mb-4 flex items-center gap-4">
        <select id="filterPersonnel" class="border rounded p-2">
            <option value="">All Personnel</option>
            <?php foreach($personnel as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <input type="date" id="filterDate" class="border rounded p-2">
        <button id="filterBtn" class="bg-blue-600 text-white px-4 py-2 rounded">Filter</button>
    </div>

    <div id="calendar"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let calendarEl = document.getElementById('calendar');

    let calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        themeSystem: 'standard',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: <?= json_encode($fcEvents) ?>,
        eventDidMount: function(info){
            tippy(info.el, {
                content: info.event.extendedProps.customer_name,
                placement: 'top'
            });
        },
        dateClick: function(info){
            let personnel = prompt("Enter Personnel ID to book:");
            let hours = prompt("Enter hours:");
            let date = info.dateStr;

            if(personnel && hours){
                fetch('book_personnel.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({personnel_id: personnel, date: date, hours: hours})
                }).then(res => res.json())
                  .then(res => {
                      if(res.success) {
                          alert('Booked successfully!');
                          location.reload();
                      } else alert('Error: ' + res.message);
                  });
            }
        }
    });

    calendar.render();

    document.getElementById('filterBtn').addEventListener('click', function(){
        let personnelId = document.getElementById('filterPersonnel').value;
        let date = document.getElementById('filterDate').value;

        calendar.getEvents().forEach(ev => ev.remove());

        let allEvents = <?= json_encode($fcEvents) ?>;

        let filtered = allEvents.filter(ev => {
            return (!personnelId || ev.extendedProps.personnel_id == personnelId)
                && (!date || ev.start == date);
        });

        filtered.forEach(ev => calendar.addEvent(ev));
    });
});
</script>

<?php
$content = ob_get_clean();
echo renderLayout($content);
?>
