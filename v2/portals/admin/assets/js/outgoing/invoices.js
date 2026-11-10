$(function () {
    var currentInvoiceId = null;

    var STC = { Posted:'info', Paid:'ok', Partial:'warn', Overdue:'bad',
                Draft:'mute', Sent:'info', Void:'mute', Closed:'mute' };
    function stChip(s) { return '<span class="chip ' + (STC[s] || 'mute') + '">' + s + '</span>'; }

    // ── DataTable ──────────────────────────────────────────────────────────────
    var table = $('#invoicesTable').DataTable({
        ajax: {
            url: APIURL + 'admin/invoices',
            type: 'GET',
            dataSrc: 'data',
        },
        columns: [
            { data: 'invoice_number' },
            { data: 'client_name' },
            { data: 'line_count' },
            { data: 'subtotal', render: function (v) { return v ? '$' + parseFloat(v).toFixed(2) : '—'; } },
            { data: 'total',    render: function (v) { return v ? '$' + parseFloat(v).toFixed(2) : '—'; } },
            { data: 'status', render: function (val) { return stChip(val); } },
            { data: 'due_at',      defaultContent: '—' },
            { data: 'amount_paid', defaultContent: '—', render: function (v) { return v && parseFloat(v) > 0 ? '$' + parseFloat(v).toFixed(2) : '—'; } },
            {
                data: 'id',
                orderable: false,
                render: function (id) {
                    return '<button class="btn btn-sm btn-outline-primary btn-view" data-id="' + id + '">'
                         + '<i class="fas fa-eye"></i></button>';
                },
            },
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        dom: '<"d-flex align-items-center justify-content-between mb-2"fl>rt<"d-flex align-items-center justify-content-between mt-2"ip>',
    });

    // ── View invoice detail ────────────────────────────────────────────────────
    $('#invoicesTable').on('click', '.btn-view', function () {
        currentInvoiceId = $(this).data('id');
        $('#invoiceModalBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
        new bootstrap.Modal(document.getElementById('invoiceModal')).show();

        PortalApi.get('admin/invoices/' + currentInvoiceId).then(function (res) {
            var inv = res.data;
            $('#invoiceModalBody').data('inv', inv);
            var linesHtml = (inv.lines || []).map(function (l) {
                return '<tr><td>' + l.product_code + '</td><td>' + l.product_name + '</td>'
                     + '<td>' + parseFloat(l.qty_g).toLocaleString() + ' g</td>'
                     + '<td>$' + parseFloat(l.price_g).toFixed(6) + '</td>'
                     + '<td>$' + parseFloat(l.line_total).toFixed(2) + '</td></tr>';
            }).join('');

            $('#invoiceModalBody').html(
                '<dl class="row mb-3">'
                + '<dt class="col-sm-3">Invoice #</dt><dd class="col-sm-9">' + inv.invoice_number + '</dd>'
                + '<dt class="col-sm-3">Client</dt><dd class="col-sm-9">' + inv.client_name + '</dd>'
                + '<dt class="col-sm-3">Status</dt><dd class="col-sm-9">' + inv.status + '</dd>'
                + '<dt class="col-sm-3">Due Date</dt><dd class="col-sm-9">' + (inv.due_at || '—') + '</dd>'
                + '<dt class="col-sm-3">Amount Paid</dt><dd class="col-sm-9">' + (inv.amount_paid && parseFloat(inv.amount_paid) > 0 ? '$' + parseFloat(inv.amount_paid).toFixed(2) : '—') + '</dd>'
                + '<dt class="col-sm-3">Total</dt><dd class="col-sm-9">$' + parseFloat(inv.total || 0).toFixed(2) + '</dd>'
                + '</dl>'
                + '<table class="table table-sm table-bordered"><thead class="table-light"><tr>'
                + '<th>Code</th><th>Product</th><th>Qty</th><th>Price/g</th><th>Total</th>'
                + '</tr></thead><tbody>' + (linesHtml || '<tr><td colspan="5" class="text-muted text-center">No lines</td></tr>') + '</tbody></table>'
            );

            $('#invoiceMarkPaidBtn').toggle(inv.status !== 'Paid');
        }).catch(function () {
            $('#invoiceModalBody').html('<div class="text-danger text-center py-4">Failed to load invoice</div>');
        });
    });

    // ── Mark as Paid ───────────────────────────────────────────────────────────
    $('#invoiceMarkPaidBtn').on('click', function () {
        if (!currentInvoiceId) return;
        var inv = $('#invoiceModalBody').data('inv') || {};
        PortalApi.put('admin/invoices/' + currentInvoiceId, {
            status:      'Paid',
            amount_paid: inv.total || 0,
        }).then(function () {
            bootstrap.Modal.getInstance(document.getElementById('invoiceModal')).hide();
            table.ajax.reload(null, false);
            toastr.success('Invoice marked as paid');
        }).catch(function () { toastr.error('Failed to update invoice'); });
    });

    $('#invoiceModal').on('hidden.bs.modal', function () {
        currentInvoiceId = null;
    });
});
