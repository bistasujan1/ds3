$(function () {
    var STC = {
        New:'info', Received:'info', Review:'warn',
        Allocated:'alloc', 'Pick List Ready':'warn', 'Pick Approved':'warn',
        Picked:'warn', Packed:'warn', Shipped:'ship', 'In Transit':'ship',
        Delivered:'ok', Closed:'mute', Backorder:'bad', Cancelled:'mute'
    };

    function stChip(s) { return '<span class="chip ' + (STC[s] || 'mute') + '">' + s + '</span>'; }

    var STAGE_FILTER = null;

    function swalConfirm(title, text, cb) {
        Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#d33', confirmButtonText: 'Confirm' })
            .then(function (r) { if (r.isConfirmed) cb(); });
    }

    // ── DataTable ──────────────────────────────────────────────────────────────
    var table = $('#ordersTable').DataTable({
        ajax: {
            url: APIURL + 'admin/orders',
            type: 'GET',
            dataSrc: function (json) {
                updatePipeline(json.data || []);
                return json.data || [];
            },
        },
        columns: [
            { data: 'order_number', className: 'fw-semibold' },
            { data: 'client_name' },
            {
                data: null,
                render: function (row) {
                    return '<span class="fw-semibold">' + (row.product_name || '') + '</span>'
                         + (row.product_code ? ' <span class="text-muted small">' + row.product_code + '</span>' : '');
                },
            },
            { data: 'qty_g',      className: 'text-end', render: function (v) { return parseFloat(v).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 3 }) + ' g'; } },
            { data: 'line_total', className: 'text-end', render: function (v) { return '$' + parseFloat(v).toFixed(2); } },
            { data: 'created_at', render: function (v) { return v ? v.substring(0, 10) : '—'; } },
            { data: 'ship_by',    defaultContent: '—' },
            { data: 'status',     render: function (val) { return stChip(val); } },
            {
                data: null,
                orderable: false,
                render: function (row) {
                    if (row.tracking_number) {
                        return '<span class="small">' + (row.carrier ? row.carrier + ' ' : '') + row.tracking_number + '</span>';
                    }
                    return '<span class="text-muted small">—</span>';
                },
            },
            {
                data: 'id',
                orderable: false,
                render: function (id) {
                    return '<button class="btn btn-sm btn-outline-secondary me-1 btn-view" data-id="' + id + '" title="View">'
                         + '<i class="fas fa-eye"></i></button>'
                         + '<button class="btn btn-sm btn-outline-primary me-1 btn-edit" data-id="' + id + '" title="Edit">'
                         + '<i class="fas fa-edit"></i></button>'
                         + '<button class="btn btn-sm btn-outline-danger btn-delete" data-id="' + id + '" title="Cancel">'
                         + '<i class="fas fa-trash"></i></button>';
                },
            },
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        dom: '<"d-flex align-items-center justify-content-between mb-2"fl>rt<"d-flex align-items-center justify-content-between mt-2"ip>',
    });

    // ── Pipeline widget ────────────────────────────────────────────────────────
    function updatePipeline(rows) {
        var st = { review:0, allocated:0, picking:0, transit:0, delivered:0, attention:0 };
        rows.forEach(function (r) {
            if (['New','Received','Review'].indexOf(r.status) !== -1) st.review++;
            else if (r.status === 'Allocated') st.allocated++;
            else if (['Pick List Ready','Pick Approved','Picked','Packed'].indexOf(r.status) !== -1) st.picking++;
            else if (['Shipped','In Transit'].indexOf(r.status) !== -1) st.transit++;
            else if (r.status === 'Delivered') st.delivered++;
            if (r.status === 'Backorder' || r.held) st.attention++;
        });
        $('#pipeReview').text(st.review);
        $('#pipeAllocated').text(st.allocated);
        $('#pipePicking').text(st.picking);
        $('#pipeTransit').text(st.transit);
        $('#pipeDelivered').text(st.delivered);
        $('#pipeAttention').text(st.attention);
    }

    // Stage click filter
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'ordersTable') return true;
        if (!STAGE_FILTER) return true;
        var r = table.row(dataIndex).data();
        if (!r) return true;
        var st = r.status;
        if (STAGE_FILTER === 'review')    return ['New','Received','Review'].indexOf(st) !== -1;
        if (STAGE_FILTER === 'allocated') return st === 'Allocated';
        if (STAGE_FILTER === 'picking')   return ['Pick List Ready','Pick Approved','Picked','Packed'].indexOf(st) !== -1;
        if (STAGE_FILTER === 'transit')   return ['Shipped','In Transit'].indexOf(st) !== -1;
        if (STAGE_FILTER === 'delivered') return st === 'Delivered';
        if (STAGE_FILTER === 'attention') return st === 'Backorder' || !!r.held;
        return true;
    });

    $('#orderPipeline').on('click', '.stage', function () {
        var stage = $(this).data('stage');
        if (STAGE_FILTER === stage) {
            STAGE_FILTER = null;
            $('#orderPipeline .stage').removeClass('active');
        } else {
            STAGE_FILTER = stage;
            $('#orderPipeline .stage').removeClass('active');
            $(this).addClass('active');
        }
        table.draw();
    });

    // ── Parsley ────────────────────────────────────────────────────────────────
    $('#orderForm').parsley();

    $('#orderModal').on('hidden.bs.modal', function () {
        $('#orderForm').parsley().reset();
        $('#orderForm')[0].reset();
        $('#orderId').val('');
        $('#orderModalLabel').text('New Order');
    });

    // ── Dropdowns helper ───────────────────────────────────────────────────────
    function populateSelect(selectId, items, labelFn) {
        var fn = typeof labelFn === 'function' ? labelFn : function (item) { return item[labelFn]; };
        var $sel = $(selectId).empty().append('<option value="">— select —</option>');
        items.forEach(function (item) {
            $sel.append('<option value="' + item.id + '">' + fn(item) + '</option>');
        });
    }

    // ── Open create ────────────────────────────────────────────────────────────
    $('#btnAddOrder').on('click', function () {
        $.when(
            PortalApi.get('admin/clients/dropdown'),
            PortalApi.get('admin/products/dropdown')
        ).done(function (clients, products) {
            populateSelect('#oClientId', clients[0].data, 'name');
            populateSelect('#oProductId', products[0].data, function (p) { return p.code + ' — ' + p.name; });
            new bootstrap.Modal(document.getElementById('orderModal')).show();
        });
    });

    // ── Open edit ─────────────────────────────────────────────────────────────
    $('#ordersTable').on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        $.when(
            PortalApi.get('admin/orders/' + id),
            PortalApi.get('admin/clients/dropdown'),
            PortalApi.get('admin/products/dropdown')
        ).done(function (order, clients, products) {
            var o = order[0].data;
            populateSelect('#oClientId', clients[0].data, 'name');
            populateSelect('#oProductId', products[0].data, function (p) { return p.code + ' — ' + p.name; });
            $('#orderId').val(o.id);
            $('#oClientId').val(o.client_id);
            $('#oProductId').val(o.product_id);
            $('#oQtyG').val(o.qty_g);
            $('#oPriceG').val(o.price_g);
            $('#oShipBy').val(o.ship_by);
            $('#oStatus').val(o.status);
            $('#oCarrier').val(o.carrier || '');
            $('#oTrackingNumber').val(o.tracking_number || '');
            $('#oNotes').val(o.notes);
            $('#orderModalLabel').text('Edit Order — ' + o.order_number);
            new bootstrap.Modal(document.getElementById('orderModal')).show();
        }).fail(function () { toastr.error('Failed to load order'); });
    });

    // ── View detail ────────────────────────────────────────────────────────────
    var detailOrderId = null;

    $('#ordersTable').on('click', '.btn-view', function () {
        detailOrderId = $(this).data('id');
        openDetail(detailOrderId);
    });

    function openDetail(id) {
        $('#orderDetailBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
        new bootstrap.Modal(document.getElementById('orderDetailModal')).show();

        PortalApi.get('admin/orders/' + id).then(function (res) {
            var o = res.data;
            $('#orderDetailLabel').text(o.order_number + ' — ' + o.client_name);
            $('#orderDetailEditBtn').data('id', o.id);

            var tracking = o.tracking_number
                ? (o.carrier ? '<strong>' + o.carrier + '</strong> ' : '') + o.tracking_number
                : '<span class="text-muted">—</span>';

            $('#orderDetailBody').html(
                '<div class="row g-3">'
                + '<div class="col-md-6">'
                +   '<p class="muted small mb-1 fw-semibold text-uppercase" style="font-size:.78rem">Order</p>'
                +   '<dl class="kv">'
                +     '<dt>Product</dt><dd>' + o.product_name + (o.product_code ? ' <span class="muted small">(' + o.product_code + ')</span>' : '') + '</dd>'
                +     '<dt>Schedule</dt><dd>' + (o.schedule && o.schedule !== 'None' ? '<span class="chip warn">' + o.schedule + '</span>' : '—') + '</dd>'
                +     '<dt>Qty</dt><dd class="num">' + parseFloat(o.qty_g).toLocaleString() + ' g @ $' + parseFloat(o.price_g).toFixed(6) + '/g</dd>'
                +     '<dt>Value</dt><dd class="num fw-semibold">$' + parseFloat(o.total).toFixed(2) + '</dd>'
                +     '<dt>Ship By</dt><dd>' + (o.ship_by || '—') + '</dd>'
                +     '<dt>Placed</dt><dd>' + (o.created_at ? o.created_at.substring(0, 10) : '—') + '</dd>'
                +   '</dl>'
                + '</div>'
                + '<div class="col-md-6">'
                +   '<p class="muted small mb-1 fw-semibold text-uppercase" style="font-size:.78rem">Status &amp; Shipping</p>'
                +   '<dl class="kv">'
                +     '<dt>Status</dt><dd>' + stChip(o.status) + '</dd>'
                +     '<dt>Tracking</dt><dd>' + tracking + '</dd>'
                +     '<dt>Shipped</dt><dd>' + (o.shipment_at ? o.shipment_at.substring(0, 10) : '—') + '</dd>'
                +     '<dt>Delivered</dt><dd>' + (o.delivery_at ? o.delivery_at.substring(0, 10) : '—') + '</dd>'
                +   '</dl>'
                + '</div>'
                + (o.notes ? '<div class="col-12"><p class="muted small mb-1 fw-semibold text-uppercase" style="font-size:.78rem">Notes</p><p class="mb-0 small">' + o.notes + '</p></div>' : '')
                + '</div>'
            );
        }).catch(function () {
            $('#orderDetailBody').html('<div class="text-danger text-center py-4">Failed to load order details.</div>');
        });
    }

    $('#orderDetailEditBtn').on('click', function () {
        var id = $(this).data('id');
        bootstrap.Modal.getInstance(document.getElementById('orderDetailModal')).hide();
        setTimeout(function () {
            $('#ordersTable .btn-edit[data-id="' + id + '"]').trigger('click');
        }, 400);
    });

    // ── Save ───────────────────────────────────────────────────────────────────
    $('#orderSaveBtn').on('click', function () {
        if (!$('#orderForm').parsley().validate()) return;

        var id      = $('#orderId').val();
        var payload = {
            client_id:       $('#oClientId').val(),
            product_id:      $('#oProductId').val(),
            qty_g:           $('#oQtyG').val(),
            price_g:         $('#oPriceG').val(),
            ship_by:         $('#oShipBy').val() || null,
            status:          $('#oStatus').val(),
            carrier:         $('#oCarrier').val() || null,
            tracking_number: $('#oTrackingNumber').val() || null,
            notes:           $('#oNotes').val(),
        };

        var req = id
            ? PortalApi.put('admin/orders/' + id, payload)
            : PortalApi.post('admin/orders', payload);

        req.then(function () {
            bootstrap.Modal.getInstance(document.getElementById('orderModal')).hide();
            table.ajax.reload(null, false);
            toastr.success('Order saved');
        }).catch(function (err) {
            toastr.error((err && err.message) ? err.message : 'Failed to save order');
        });
    });

    // ── Delete ─────────────────────────────────────────────────────────────────
    $('#ordersTable').on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        swalConfirm('Cancel this order?', 'Status will be set to Cancelled.', function () {
            PortalApi.del('admin/orders/' + id).then(function () {
                table.ajax.reload(null, false);
                toastr.success('Order cancelled');
            }).catch(function () { toastr.error('Failed to cancel order'); });
        });
    });
});
