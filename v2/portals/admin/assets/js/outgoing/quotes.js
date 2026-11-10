$(function () {
    var STC = { Draft:'mute', Sent:'info', Accepted:'ok', Declined:'bad', Expired:'warn' };
    function stChip(s) { return '<span class="chip ' + (STC[s] || 'mute') + '">' + s + '</span>'; }

    var productOptions = [];

    function swalConfirm(title, text, cb) {
        Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#d33', confirmButtonText: 'Confirm' })
            .then(function (r) { if (r.isConfirmed) cb(); });
    }

    // ── Load product dropdown data once ───────────────────────────────────────
    function loadProducts() {
        return PortalApi.get('admin/products/dropdown').then(function (res) {
            productOptions = res.data || [];
        });
    }

    function buildProductSelect(selected) {
        var html = '<option value="">— select product —</option>';
        productOptions.forEach(function (p) {
            html += '<option value="' + p.id + '"' + (selected == p.id ? ' selected' : '') + '>'
                  + p.code + ' — ' + p.name + '</option>';
        });
        return html;
    }

    // ── DataTable ──────────────────────────────────────────────────────────────
    var table = $('#quotesTable').DataTable({
        ajax: {
            url: APIURL + 'admin/quotes',
            type: 'GET',
            dataSrc: 'data',
        },
        columns: [
            { data: 'ref' },
            { data: 'client_name' },
            { data: 'line_count' },
            { data: 'status', render: function (val) { return stChip(val); } },
            { data: 'valid_until' },
            { data: 'created_at', render: function (v) { return v ? v.substring(0, 10) : '—'; } },
            {
                data: 'id',
                orderable: false,
                render: function (id) {
                    return '<button class="btn btn-sm btn-outline-primary me-1 btn-edit" data-id="' + id + '">'
                         + '<i class="fas fa-edit"></i></button>'
                         + '<button class="btn btn-sm btn-outline-danger btn-delete" data-id="' + id + '">'
                         + '<i class="fas fa-trash"></i></button>';
                },
            },
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        dom: '<"d-flex align-items-center justify-content-between mb-2"fl>rt<"d-flex align-items-center justify-content-between mt-2"ip>',
    });

    // ── Parsley ────────────────────────────────────────────────────────────────
    $('#quoteForm').parsley();

    $('#quoteModal').on('hidden.bs.modal', function () {
        $('#quoteForm').parsley().reset();
        $('#quoteForm')[0].reset();
        $('#quoteId').val('');
        $('#quoteLinesBody').empty();
        $('#quoteModalLabel').text('New Quote');
    });

    // ── Add a blank line row ───────────────────────────────────────────────────
    function addLineRow(productId, qty, price) {
        var tmpl  = document.getElementById('lineRowTemplate').content.cloneNode(true);
        var $row  = $(tmpl).find('tr');
        // content.cloneNode returns a DocumentFragment; re-wrap
        var $frag = $(document.importNode(document.getElementById('lineRowTemplate').content, true));
        $row = $frag.find('tr');
        $row.find('.line-product').html(buildProductSelect(productId || ''));
        if (qty)   $row.find('.line-qty').val(qty);
        if (price) $row.find('.line-price').val(price);
        updateLineTotal($row);
        $('#quoteLinesBody').append($row);
    }

    function updateLineTotal($row) {
        var qty   = parseFloat($row.find('.line-qty').val()) || 0;
        var price = parseFloat($row.find('.line-price').val()) || 0;
        $row.find('.line-total').text(qty && price ? '$' + (qty * price).toFixed(2) : '—');
    }

    $(document).on('input', '.line-qty, .line-price', function () {
        updateLineTotal($(this).closest('tr'));
    });

    $(document).on('click', '.btn-remove-line', function () {
        $(this).closest('tr').remove();
    });

    $('#addLineBtn').on('click', function () { addLineRow(); });

    // ── Open create ────────────────────────────────────────────────────────────
    $('#btnAddQuote').on('click', function () {
        $.when(
            PortalApi.get('admin/clients/dropdown'),
            loadProducts()
        ).done(function (clients) {
            var $sel = $('#qClientId').empty().append('<option value="">— select client —</option>');
            clients[0].data.forEach(function (c) {
                $sel.append('<option value="' + c.id + '">' + c.name + '</option>');
            });
            addLineRow();
            new bootstrap.Modal(document.getElementById('quoteModal')).show();
        });
    });

    // ── Open edit ─────────────────────────────────────────────────────────────
    $('#quotesTable').on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        $.when(
            PortalApi.get('admin/quotes/' + id),
            PortalApi.get('admin/clients/dropdown'),
            loadProducts()
        ).done(function (quoteRes, clients) {
            var q = quoteRes[0].data;
            var $sel = $('#qClientId').empty().append('<option value="">— select client —</option>');
            clients[0].data.forEach(function (c) {
                $sel.append('<option value="' + c.id + '"' + (c.id == q.client_id ? ' selected' : '') + '>'
                           + c.name + '</option>');
            });
            $('#quoteId').val(q.id);
            $('#qValidUntil').val(q.valid_until);
            $('#qShipBy').val(q.ship_by);
            $('#qTerms').val(q.terms);
            $('#qStatus').val(q.status);
            $('#qNotes').val(q.notes);
            $('#quoteLinesBody').empty();
            (q.lines || []).forEach(function (l) {
                addLineRow(l.product_id, l.qty_g, l.price_g);
            });
            $('#quoteModalLabel').text('Edit Quote — ' + q.ref);
            new bootstrap.Modal(document.getElementById('quoteModal')).show();
        }).fail(function () { toastr.error('Failed to load quote'); });
    });

    // ── Collect lines ──────────────────────────────────────────────────────────
    function collectLines() {
        var lines = [];
        $('#quoteLinesBody .line-row').each(function () {
            lines.push({
                product_id: $(this).find('.line-product').val(),
                qty_g:      $(this).find('.line-qty').val(),
                price_g:    $(this).find('.line-price').val(),
            });
        });
        return lines;
    }

    // ── Save ───────────────────────────────────────────────────────────────────
    $('#quoteSaveBtn').on('click', function () {
        if (!$('#quoteForm').parsley().validate()) return;

        var lines = collectLines();
        if (lines.length === 0) {
            toastr.warning('Add at least one line item');
            return;
        }

        var id      = $('#quoteId').val();
        var payload = {
            client_id:   $('#qClientId').val(),
            valid_until: $('#qValidUntil').val(),
            ship_by:     $('#qShipBy').val() || null,
            terms:       $('#qTerms').val(),
            status:      $('#qStatus').val(),
            notes:       $('#qNotes').val(),
            lines:       lines,
        };

        var req = id
            ? PortalApi.put('admin/quotes/' + id, payload)
            : PortalApi.post('admin/quotes', payload);

        req.then(function () {
            bootstrap.Modal.getInstance(document.getElementById('quoteModal')).hide();
            table.ajax.reload(null, false);
            toastr.success('Quote saved');
        }).catch(function (err) {
            toastr.error((err && err.message) ? err.message : 'Failed to save quote');
        });
    });

    // ── Delete ─────────────────────────────────────────────────────────────────
    $('#quotesTable').on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        swalConfirm('Delete this quote?', 'This cannot be undone.', function () {
            PortalApi.del('admin/quotes/' + id).then(function () {
                table.ajax.reload(null, false);
                toastr.success('Quote deleted');
            }).catch(function () { toastr.error('Failed to delete quote'); });
        });
    });
});
