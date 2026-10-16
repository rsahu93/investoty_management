
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
    <h2 class="mb-4">Inventory Management</h2>

    <div id="message" class="mb-3"></div>

    <div class="card mb-4">
        <div class="card-header">Add Product</div>
        <div class="card-body">
            <form id="productForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input name="name" class="form-control"
                               placeholder="Product name" maxlength="255" required>
                    </div>
                    <div class="col-md-3">
                        <input name="sku" class="form-control"
                               placeholder="SKU" maxlength="100" required>
                    </div>
                    <div class="col-md-3">
                        <input name="price" type="number" step="0.01"
                               min="0" class="form-control"
                               placeholder="Price" required>
                    </div>
                    <div class="col-md-3">
                        <input name="stock" type="number" min="0"
                               step="1" class="form-control"
                               placeholder="Initial stock" required>
                    </div>
                </div>

                <button class="btn btn-primary mt-3" type="submit">
                    Add Product
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Available Products</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Sell Quantity</th>
                        <th>Sale Total</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody id="productTable">
                    <tr>
                        <td colspan="7">Loading products...</td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <button id="previous" class="btn btn-outline-secondary">
                    Previous
                </button>
                <span id="pageInfo"></span>
                <button id="next" class="btn btn-outline-secondary">
                    Next
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const API = '/api/products';
let currentPage = 1;
const limit = 10;
let lastPage = 1;

const table = document.getElementById('productTable');
const message = document.getElementById('message');
const productForm = document.getElementById('productForm');

async function apiRequest(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            'Accept': 'application/json',
            ...(options.body ? {'Content-Type': 'application/json'} : {}),
            ...options.headers
        }
    });

    const result = await response.json();

    if (!response.ok) {
        const errors = result.errors
            ? Object.values(result.errors).flat().join(' ')
            : result.message;

        throw new Error(errors || 'Request failed.');
    }

    return result;
}

function showMessage(text, type = 'success') {
    message.textContent = text;
    message.className = `alert alert-${type} mb-3`;
}

function clearMessage() {
    message.textContent = '';
    message.className = 'mb-3';
}

async function loadProducts() {
    try {
        const result = await apiRequest(
            `${API}?page=${currentPage}&limit=${limit}`
        );

        lastPage = result.pagination.last_page;

        table.replaceChildren();

        if (result.data.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 7;
            td.textContent = 'No products found.';
            tr.appendChild(td);
            table.appendChild(tr);
        }

        result.data.forEach(product => {
            const tr = document.createElement('tr');

            function addCell(value) {
                const td = document.createElement('td');
                td.textContent = value;
                tr.appendChild(td);
                return td;
            }

            addCell(product.name);
            addCell(product.sku);
            addCell(Number(product.price).toFixed(2));
            addCell(product.stock);

            const quantityCell = addCell('');
            const quantityInput = document.createElement('input');
            quantityInput.type = 'number';
            quantityInput.min = '1';
            quantityInput.step = '1';
            quantityInput.value = '1';
            quantityInput.max = String(product.stock);
            quantityInput.className = 'form-control';
            quantityInput.style.width = '110px';
            quantityInput.setAttribute('aria-label',
                `Quantity to sell for ${product.name}`);
            quantityCell.appendChild(quantityInput);

            const totalCell = addCell('');
            const total = document.createElement('span');
            total.textContent = (Number(product.price) * Number(quantityInput.value)).toFixed(2);
            totalCell.appendChild(total);

            quantityInput.addEventListener('input', () => {
                const quantity = Number(quantityInput.value);
                total.textContent = Number.isSafeInteger(quantity) && quantity >= 0
                    ? (Number(product.price) * quantity).toFixed(2)
                    : '—';
            });

            const actionCell = addCell('');
            const sellButton = document.createElement('button');
            sellButton.className = 'btn btn-success btn-sm';
            sellButton.textContent = 'Sell';

            sellButton.addEventListener('click', async () => {
                clearMessage();

                const quantity = Number(quantityInput.value);

                if (!Number.isSafeInteger(quantity) || quantity < 1 || quantity > product.stock) {
                    quantityInput.setCustomValidity(
                        `Enter a whole number from 1 to ${product.stock}.`
                    );
                    quantityInput.reportValidity();
                    return;
                }
                quantityInput.setCustomValidity('');

                sellButton.disabled = true;

                try {
                    const sold = await apiRequest(
                        `${API}/${product.id}/sell`,
                        {
                            method: 'POST',
                            body: JSON.stringify({quantity})
                        }
                    );

                    showMessage(
                        `Sale successful. Remaining stock: ${sold.data.stock}`
                    );

                    await loadProducts();
                } catch (error) {
                    showMessage(error.message, 'danger');
                    // Refresh to show the latest stock, even after a
                    // rejected sale.
                    try {
                        await loadProducts();
                    } catch (_) {}
                }
            });

            actionCell.appendChild(sellButton);
            table.appendChild(tr);
        });

        document.getElementById('pageInfo').textContent =
            `Page ${result.pagination.current_page} of ${lastPage} ` +
            `(${result.pagination.total} products)`;

        document.getElementById('previous').disabled = currentPage <= 1;
        document.getElementById('next').disabled = currentPage >= lastPage;

    } catch (error) {
        showMessage(error.message, 'danger');
    }
}

productForm.addEventListener('submit',
    async event => {
        event.preventDefault();
        clearMessage();

        const form = event.currentTarget;
        const formData = new FormData(form);
        const name = String(formData.get('name') || '').trim();
        const sku = String(formData.get('sku') || '').trim();
        const price = Number(formData.get('price'));
        const stock = Number(formData.get('stock'));

        if (!name || !sku || !Number.isFinite(price) || price < 0 ||
            !Number.isSafeInteger(stock) || stock < 0) {
            showMessage('Enter a product name and SKU, a price of 0 or more, and a whole-number stock of 0 or more.', 'danger');
            return;
        }

        const product = {
            name,
            sku,
            price,
            stock
        };

        try {
            await apiRequest(API, {
                method: 'POST',
                body: JSON.stringify(product)
            });

            showMessage('Product created successfully.');
            form.reset();
            currentPage = 1;
            await loadProducts();
        } catch (error) {
            showMessage(error.message, 'danger');
        }
    });

document.getElementById('previous').addEventListener('click', async () => {
    if (currentPage > 1) {
        currentPage--;
        await loadProducts();
    }
});

document.getElementById('next').addEventListener('click', async () => {
    if (currentPage < lastPage) {
        currentPage++;
        await loadProducts();
    }
});

loadProducts();
</script>

</body>
</html>
