<form method="POST">
    <label>Customer Name:</label>
    <input type="text" name="customer_name" required class="border p-2 rounded mb-2">

    <div id="itemsContainer">
        <div class="itemRow mb-2">
            <input type="text" name="items[0][name]" placeholder="Item Name" required class="border p-2 rounded">
            <input type="number" name="items[0][price]" placeholder="Price" required class="border p-2 rounded">
            <input type="number" name="items[0][qty]" placeholder="Quantity" required class="border p-2 rounded">
        </div>
    </div>

    <button type="button" id="addItemBtn" class="px-3 py-1 bg-blue-600 text-white rounded mb-3">Add Item</button>
    <br>
    <button type="submit" class="px-3 py-1 bg-green-600 text-white rounded">Save Order</button>
</form>

<script>
let index = 1;
document.getElementById('addItemBtn').onclick = () => {
    const container = document.getElementById('itemsContainer');
    const div = document.createElement('div');
    div.classList.add('itemRow', 'mb-2');
    div.innerHTML = `
        <input type="text" name="items[${index}][name]" placeholder="Item Name" required class="border p-2 rounded">
        <input type="number" name="items[${index}][price]" placeholder="Price" required class="border p-2 rounded">
        <input type="number" name="items[${index}][qty]" placeholder="Quantity" required class="border p-2 rounded">
    `;
    container.appendChild(div);
    index++;
};
</script>
