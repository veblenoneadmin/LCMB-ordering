<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// --- Analytics Metrics ---
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalInstallations = $pdo->query("SELECT COUNT(*) FROM split_installation UNION SELECT COUNT(*) FROM ductedinstallations")->rowCount();
$totalClients = $pdo->query("SELECT COUNT(DISTINCT customer_email) FROM orders")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();

// --- Fetch dispatch data ---
$stmt = $pdo->query("
    SELECT d.id, d.date, d.hours, p.name AS personnel_name
    FROM dispatch d
    LEFT JOIN personnel p ON p.id = d.personnel_id
    WHERE d.status = 'approved'
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
    "title" => $person,  // <-- only name
    "start" => $row['date'],
    "color" => $personnelColors[$person],
    "extendedProps" => [
        "personnel" => $person,
        "hours" => $row['hours'],
        "date" => $row['date']
    ]
];

}

ob_start();
?>

<!-- Google Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<!-- Analytics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Total Orders</h3> 
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $totalOrders ?></p>
    </div>

    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Installations</h3>
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $totalInstallations ?></p>
    </div>

    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Clients</h3>
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $totalClients ?></p>
    </div>

    <div class="bg-white rounded-xl shadow p-5 flex flex-col">
        <h3 class="text-gray-500 font-medium text-sm">Pending Orders</h3>
        <p class="text-2xl font-bold text-gray-800 mt-2"><?= $pendingOrders ?></p>
    </div>
</div>

<!-- Calendar + Pending Orders Panel -->
<div class="grid grid-cols-1 lg:grid-cols-[3fr_1fr] gap-4 mb-6">

    <!-- Left: Calendar -->
    <div id="calendarContainer" class="w-full">
        <div id="calendar" class="rounded-lg border border-gray-200 w-full h-[500px]"></div>
    </div>

   <!-- Right Panel: Pending Orders -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-100 h-[500px] overflow-y-auto ml-auto"
     style="width: 260px;"> <!-- approximate width of one analytic card -->
    <h2 class="text-md font-semibold text-gray-600 mb-4">Pending Orders</h2>

    <?php
    $pendingList = $pdo->query("SELECT id, customer_name, total_amount, created_at FROM orders WHERE status='pending' ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (empty($pendingList)): ?>
        <p class="text-gray-500 text-sm">No pending orders.</p>
    <?php else: ?>
        <?php foreach ($pendingList as $o):
            $date = date('d M', strtotime($o['created_at']));
            $time = date('h:i A', strtotime($o['created_at']));
        ?>
        <div class="mb-4 p-3 border border-gray-100 rounded-lg hover:bg-gray-50 cursor-pointer pending-item"
             data-id="<?= $o['id'] ?>"
             data-customer="<?= htmlspecialchars($o['customer_name']) ?>"
             data-total="<?= number_format($o['total_amount'],2) ?>">
            <p class="text-md font-bold text-gray-500">New Order#<?= $o['id'] ?></p>
            <p class="text-sm text-gray-400"><?= $date ?><?= $time ?></p>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>


</div>

<!-- Pending Order Modal -->
<div id="pendingModal" class="fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm hidden flex items-center justify-center z-50 h-screen">
    <div class="bg-white p-6 rounded-2xl shadow-2xl w-96 transform scale-95 opacity-0 transition-all duration-300" id="pendingModalContent">
        <h2 class="text-xl font-bold text-gray-800 mb-3">Order Details</h2>
        <div class="space-y-2 text-gray-700">
            <p id="pmCustomer"></p>
            <p id="pmItems"></p>
            <p id="pmTotal"></p>
        </div>
        <div class="flex justify-between mt-6">
            <form method="POST" action="/partials/update_status1.php">
                <input type="hidden" name="order_id" id="pmOrderId">
                <button name="action" value="approve" class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">Approve</button>
            </form>
            <button id="pmClose" class="px-4 py-2 rounded-lg bg-gray-300 text-gray-700 hover:bg-gray-400">Close</button>
        </div>
    </div>
</div>

<!-- Calendar Event Modal -->
<div id="calendarModal" class="fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm hidden flex items-center justify-center z-50 h-screen">
    <div class="bg-white p-6 rounded-2xl shadow-2xl w-96 transform scale-95 opacity-0 transition-all duration-300" id="calendarModalContent">
        <h2 class="text-xl font-bold text-gray-800 mb-3" id="cmTitle">Event Details</h2>
        <div class="space-y-2 text-gray-700">
            <p id="cmDate"></p>
            <p id="cmPersonnel"></p>
            <p id="cmHours"></p>
        </div>
        <div class="flex justify-end mt-6">
            <button id="cmClose" class="px-4 py-2 rounded-lg bg-gray-300 text-gray-700 hover:bg-gray-400">Close</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>

<?php if (isset($_GET['approved']) && $_GET['approved'] == '1'): ?>
<!-- Approved Modal (centered) -->
<div id="approvedModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
  <!-- Background overlay -->
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

  <!-- Modal card -->
  <div class="relative bg-white rounded-2xl shadow-xl w-80 max-w-full p-6 text-center transform transition-transform scale-95 opacity-0 animate-modal-in">
    <!-- Icon / Success indicator -->
    <div class="flex items-center justify-center mb-4">
      <span class="material-icons text-green-500 text-5xl animate-bounce">check_circle</span>
    </div>
    
    <!-- Title -->
    <h2 class="text-xl font-semibold text-gray-800 mb-2">Success</h2>
    <p class="text-sm text-gray-600">Order has been approved!</p>

    <!-- Button -->
    <div class="mt-6">
      <button id="closeApprovedModal" class="px-6 py-2 bg-green-500 text-white font-medium rounded-xl shadow-md hover:bg-green-600 transition-colors duration-200">
        OK
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('approvedModal');
  const btn = document.getElementById('closeApprovedModal');
  if (!modal || !btn) return;
  btn.addEventListener('click', function () {
    modal.style.display = 'none';
    if (history && history.replaceState) {
      const url = new URL(window.location.href);
      url.searchParams.delete('approved');
      history.replaceState(null, '', url.pathname + url.search);
    }
  });
  btn.focus();
});
</script>
<?php endif; ?>

<style>
#calendarModal.show #calendarModalContent,
#pendingModal.show #pendingModalContent {
    transform: scale(1);
    opacity: 1;
}
@keyframes modalIn {
  0% { opacity: 0; transform: scale(0.95); }
  100% { opacity: 1; transform: scale(1); }
}

.animate-modal-in {
  animation: modalIn 0.2s ease-out forwards;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let allEvents = <?= json_encode($events) ?>;
    let calendarEl = document.getElementById('calendar');

    // --- Calendar ---
    const calendarModal = document.getElementById('calendarModal');
    const cmContent = document.getElementById('calendarModalContent');
    const cmClose = document.getElementById('cmClose');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 500,
        headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: allEvents,
        displayEventTime: false,
        eventClick: function(info) {
            const e = info.event.extendedProps;
            document.getElementById('cmTitle').innerText = info.event.title;
            document.getElementById('cmDate').innerText = "Date: " + e.date;
            document.getElementById('cmPersonnel').innerText = "Personnel: " + e.personnel;

            calendarModal.classList.remove('hidden');
            void cmContent.offsetWidth;
            calendarModal.classList.add('show');
        }
    });

    calendar.render();

    cmClose.addEventListener('click', () => {
        calendarModal.classList.remove('show');
        setTimeout(() => calendarModal.classList.add('hidden'), 300);
    });

    // --- Pending Orders Modal ---
    const pendingItems = document.querySelectorAll('.pending-item');
    const pendingModal = document.getElementById('pendingModal');
    const pmContent = document.getElementById('pendingModalContent');
    const pmClose = document.getElementById('pmClose');

    pendingItems.forEach(item => {
        item.addEventListener('click', () => {
            const orderId = item.getAttribute('data-id');
            const customerName = item.getAttribute('data-customer');
            const total = item.getAttribute('data-total');

            document.getElementById('pmCustomer').innerText = 'Customer: ' + customerName;
            document.getElementById('pmItems').innerText = 'Items: TBD';
            document.getElementById('pmTotal').innerText = 'Total: ₱' + total;
            document.getElementById('pmOrderId').value = orderId;

            pendingModal.classList.remove('hidden');
            void pmContent.offsetWidth;
            pendingModal.classList.add('show');
        });
    });

    pmClose.addEventListener('click', () => {
        pendingModal.classList.remove('show');
        setTimeout(() => pendingModal.classList.add('hidden'), 300);
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("LCMB Dashboard", $content);
?>
