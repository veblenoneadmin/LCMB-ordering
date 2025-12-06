<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// --- Analytics Metrics ---
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalInstallations = $pdo->query("SELECT COUNT(*) FROM split_installation UNION SELECT COUNT(*) FROM ductedinstallations")->rowCount();
$totalClients = $pdo->query("SELECT COUNT(DISTINCT customer_email) FROM orders")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();

// --- Fetch dispatch data ---
$stmt = $pdo->query("SELECT d.id, d.date, d.hours, p.name AS personnel_name FROM dispatch d LEFT JOIN personnel p ON p.id = d.personnel_id");
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
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">

    <!-- Left: Calendar -->
    <div id="calendar" class="w-full h-[500px] border border-gray-200 rounded-lg"></div>

    <!-- Right Panel: Pending Orders -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-100 h-fit overflow-y-auto">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Pending Orders</h2>

    <?php
    $pendingList = $pdo->query("SELECT id, created_at, customer_name, total_amount FROM orders WHERE status='pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (empty($pendingList)): ?>
        <p class="text-gray-500 text-sm">No pending orders.</p>
    <?php else: ?>
        <?php foreach ($pendingList as $o): 
            $date = date('d M', strtotime($o['created_at']));
            $time = date('h:i A', strtotime($o['created_at']));
        ?>
        <div class="mb-4 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer pending-item" 
             data-id="<?= $o['id'] ?>" 
             data-customer="<?= htmlspecialchars($o['customer_name']) ?>" 
             data-total="<?= $o['total_amount'] ?>">
            <p class="text-lg font-bold text-gray-800">New Order #<?= $o['id'] ?></p>
            <p class="text-sm text-gray-500"><?= $date ?> <?= $time ?></p>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>


<!-- Right Panel: Pending Orders -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-100 h-fit overflow-y-auto max-w-xs ml-auto">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Pending Orders</h2>

    <?php
    $pendingList = $pdo->query("
        SELECT id, created_at, customer_name, total_amount 
        FROM orders 
        WHERE status='pending' 
        ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (empty($pendingList)): ?>
        <p class="text-gray-500 text-sm">No pending orders.</p>
    <?php else: ?>
        <?php foreach ($pendingList as $o): 
            $date = date('d M', strtotime($o['created_at']));
            $time = date('h:i A', strtotime($o['created_at']));
        ?>
        <div class="mb-4 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer pending-item"
             data-id="<?= $o['id'] ?>"
             data-customer="<?= htmlspecialchars($o['customer_name']) ?>"
             data-total="<?= $o['total_amount'] ?>">
            <p class="text-lg font-bold text-gray-800">New Order #<?= $o['id'] ?></p>
            <p class="text-sm text-gray-500"><?= $date ?> <?= $time ?></p>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>


<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let allEvents = <?= json_encode($events) ?>;
    let calendarEl = document.getElementById('calendar');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 650,
        headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: allEvents,
        displayEventTime: false
    });

    calendar.render();

    // Pending order modal logic
    const pendingModal = document.getElementById('pendingModal');
    const pmContent = document.getElementById('pendingModalContent');
    const pmClose = document.getElementById('pmClose');

    function openPendingModal(item) {
        const { id: orderId, customer, total } = item.dataset;

        document.getElementById('pmCustomer').innerText = 'Customer: ' + customer;
        document.getElementById('pmItems').innerText = 'Items: TBD'; // fetch from order_items table if needed
        document.getElementById('pmTotal').innerText = 'Total: ₱' + total;
        document.getElementById('pmOrderId').value = orderId;

        pendingModal.classList.remove('hidden');
        void pmContent.offsetWidth;
        pendingModal.classList.add('show');
    }

    // Add click listener to each pending order
    document.querySelectorAll('.pending-item').forEach(item => {
        item.addEventListener('click', () => openPendingModal(item));
    });

    // Close button
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