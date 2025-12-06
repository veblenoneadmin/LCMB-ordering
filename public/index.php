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
");
$dispatch = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate events array for JS
$events = [];
$personnelColor = '#3b82f6';
foreach ($dispatch as $row) {
    $person = $row['personnel_name'] ?: 'Unknown';
    $events[] = [
        "id" => $row['id'],
        "title" => $person . " (" . $row['hours'] . "h)",
        "start" => $row['date'],
        "color" => $personnelColor,
        "extendedProps" => [
            "personnel" => $person,
            "hours" => $row['hours'],
            "date" => $row['date']
        ]
    ];
}

// Pending orders
$pendingList = $pdo->query("SELECT id, created_at FROM orders WHERE status='pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

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

<!-- Calendar + Pending Orders -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">

    <!-- Calendar -->
    <div class="bg-white p-4 rounded-xl shadow border border-gray-200 lg:col-span-3 h-[600px] relative">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
                <button id="prevBtn" class="px-2 py-1 rounded hover:bg-gray-100">&lt;</button>
                <span id="calendarTitle" class="font-medium text-gray-700">December 2025</span>
                <button id="nextBtn" class="px-2 py-1 rounded hover:bg-gray-100">&gt;</button>
            </div>
            <div class="flex items-center gap-2">
                <button class="calendar-view-btn" data-view="dayGridMonth"><span class="material-icons">calendar_month</span></button>
                <button class="calendar-view-btn" data-view="timeGridWeek"><span class="material-icons">calendar_view_week</span></button>
                <button class="calendar-view-btn" data-view="listWeek"><span class="material-icons">format_list_bulleted</span></button>
                <button id="fullscreenBtn"><span class="material-icons">fullscreen</span></button>
            </div>
        </div>
        <div class="mb-2">
            <label class="mr-2 font-medium">Filter by Personnel:</label>
            <select id="personnelFilter" class="p-2 border rounded-lg text-sm">
                <option value="all">All</option>
                <?php foreach(array_unique(array_column($dispatch,'personnel_name')) as $person): ?>
                <option value="<?= htmlspecialchars($person) ?>"><?= htmlspecialchars($person) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="calendar" class="rounded-lg border h-full"></div>
    </div>

    <!-- Pending Orders -->
    <div class="bg-white p-4 rounded-xl shadow border border-gray-200 h-[600px] overflow-y-auto lg:w-1/4">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Pending Orders</h2>
        <?php foreach ($pendingList as $o): 
            $date = date('d M h:i A', strtotime($o['created_at']));
        ?>
        <div class="mb-4 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer pending-order" data-order-id="<?= $o['id'] ?>">
            <p class="text-lg font-bold text-gray-800">New #<?= $o['id'] ?></p>
            <p class="text-sm text-gray-500"><?= $date ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Dispatch Modal -->
<div id="dispatchModal" class="fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm hidden flex items-center justify-center z-50 h-screen">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-2xl w-96 max-w-full mx-2 transform scale-95 opacity-0 transition-all duration-300 ease-out" id="dispatchModalContent">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4" id="modalTitle">Dispatch Details</h2>
        <div class="space-y-2 text-gray-700 dark:text-gray-300">
            <p id="modalDate" class="text-sm"></p>
            <p id="modalPersonnel" class="text-sm"></p>
            <p id="modalHours" class="text-sm"></p>
        </div>
        <div class="flex justify-end mt-6">
            <button id="closeModal" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition">Close</button>
        </div>
    </div>
</div>

<!-- Pending Order Modal -->
<div id="pendingOrderModal" class="fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm hidden flex items-center justify-center z-50 h-screen">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-2xl w-96 max-w-full mx-2 transform scale-95 opacity-0 transition-all duration-300 ease-out">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4" id="pendingModalTitle">Order Details</h2>
        <div class="space-y-2 text-gray-700 dark:text-gray-300">
            <p id="pendingCustomer" class="text-sm"></p>
            <p id="pendingItems" class="text-sm"></p>
            <p id="pendingTotal" class="text-sm"></p>
        </div>
        <div class="flex justify-end mt-6 gap-2">
            <button id="approveOrderBtn" class="px-4 py-2 rounded-lg bg-green-600 text-white font-medium hover:bg-green-700 transition">Approve</button>
            <button id="cancelOrderBtn" class="px-4 py-2 rounded-lg bg-red-600 text-white font-medium hover:bg-red-700 transition">Cancel</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>

<style>
#dispatchModal.show #dispatchModalContent,
#pendingOrderModal.show div {
    transform: scale(1);
    opacity: 1;
}
#calendar {
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.fc-event {
    background-color: #3b82f6 !important;
    border: none;
    border-radius: 8px;
    color: #fff !important;
    padding: 4px 6px;
    font-weight: 500;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.fc-event:hover { transform: scale(1.05); }
#calendar.fullscreen {
    position: fixed !important;
    top: 0; left:0; width:100vw !important; height:100vh !important;
    background:#f4f6f8; z-index:9999; padding:20px; box-sizing:border-box;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let allEvents = <?= json_encode($events) ?>;
    let calendarEl = document.getElementById('calendar');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: '100%',
        headerToolbar: false,
        events: allEvents,
        displayEventTime: false,
        eventContent: function(arg){ return { html: '<b>'+arg.event.extendedProps.personnel+'</b>' }; },
        eventClick: function(info){
            const e = info.event.extendedProps;
            document.getElementById('modalTitle').innerText = info.event.title;
            document.getElementById('modalDate').innerText = "Date: "+e.date;
            document.getElementById('modalPersonnel').innerText = "Personnel: "+e.personnel;
            document.getElementById('modalHours').innerText = "Hours: "+e.hours;
            const modal = document.getElementById('dispatchModal');
            modal.classList.remove('hidden'); void modal.offsetWidth; modal.classList.add('show');
        }
    });
    calendar.render();

    // Update month title
    const calendarTitle = document.getElementById('calendarTitle');
    function updateTitle(){ 
        const view = calendar.view; 
        const date = view.currentStart; 
        const month = date.toLocaleString('default',{month:'long'}); 
        const year = date.getFullYear(); 
        calendarTitle.textContent = month+' '+year;
    }
    updateTitle();
    calendar.on('datesSet',updateTitle);

    // Prev/Next buttons
    document.getElementById('prevBtn').addEventListener('click',()=>calendar.prev());
    document.getElementById('nextBtn').addEventListener('click',()=>calendar.next());

    // View icons
    document.querySelectorAll('.calendar-view-btn').forEach(btn=>{
        btn.addEventListener('click',()=>calendar.changeView(btn.dataset.view));
    });

    // Fullscreen
    const fsBtn = document.getElementById('fullscreenBtn');
    let isFs=false;
    fsBtn.addEventListener('click',()=>{
        isFs=!isFs;
        if(isFs) calendarEl.classList.add('fullscreen');
        else calendarEl.classList.remove('fullscreen');
        calendar.updateSize();
    });

    // Filter by personnel
    document.getElementById('personnelFilter').addEventListener('change',function(){
        const val=this.value;
        let filteredEvents = val==='all'? allEvents: allEvents.filter(ev=>ev.extendedProps.personnel===val);
        calendar.removeAllEvents(); calendar.addEventSource(filteredEvents);
    });

    // Pending Orders Modal
    const pendingOrders = document.querySelectorAll('.pending-order');
    const pendingModal = document.getElementById('pendingOrderModal');
    const cancelOrderBtn = document.getElementById('cancelOrderBtn');
    const approveOrderBtn = document.getElementById('approveOrderBtn');
    let currentOrderId=null;

    pendingOrders.forEach(el=>{
        el.addEventListener('click',()=>{
            currentOrderId=el.dataset.orderId;
            fetch(`get_order_details.php?order_id=${currentOrderId}`)
            .then(res=>res.json())
            .then(data=>{
                document.getElementById('pendingModalTitle').innerText="Order #"+currentOrderId;
                document.getElementById('pendingCustomer').innerText="Customer: "+data.customer_name;
                document.getElementById('pendingItems').innerText="Items Ordered: "+data.items_count;
                document.getElementById('pendingTotal').innerText="Total: $"+data.total_amount;
                pendingModal.classList.remove('hidden'); void pendingModal.offsetWidth; pendingModal.querySelector('div').classList.add('show');
            });
        });
    });
    cancelOrderBtn.addEventListener('click',()=>{ pendingModal.classList.remove('show'); setTimeout(()=>pendingModal.classList.add('hidden'),300); });
    approveOrderBtn.addEventListener('click',()=>{
        if(!currentOrderId) return;
        fetch('update_status.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`order_id=${currentOrderId}&status=approved`})
        .then(res=>res.json()).then(resp=>{
            if(resp.success){ alert("Order approved!"); location.reload(); } 
            else alert("Failed to approve order.");
        });
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("LCMB Dashboard", $content);
?>
