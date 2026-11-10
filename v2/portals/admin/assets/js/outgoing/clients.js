$(function () {
    var STC = { Active:'ok', Inactive:'mute', Suspended:'bad' };
    function stChip(s) { return '<span class="chip ' + (STC[s] || 'mute') + '">' + s + '</span>'; }

    function swalConfirm(title, text, cb) {
        Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#d33', confirmButtonText: 'Confirm' })
            .then(function (r) { if (r.isConfirmed) cb(); });
    }

    // ── DataTable ──────────────────────────────────────────────────────────────
    var table = $('#clientsTable').DataTable({
        ajax: {
            url: APIURL + 'admin/clients',
            type: 'GET',
            dataSrc: 'data',
        },
        columns: [
            { data: 'name' },
            { data: 'contact', defaultContent: '—' },
            { data: 'email' },
            { data: 'phone' },
            { data: 'license_number', defaultContent: '—' },
            { data: 'license_expiry', defaultContent: '—', render: function (v) { return v || '—'; } },
            {
                data: 'status',
                render: function (val) { return stChip(val); },
            },
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
        order: [[0, 'asc']],
        pageLength: 25,
        dom: '<"d-flex align-items-center justify-content-between mb-2"fl>rt<"d-flex align-items-center justify-content-between mt-2"ip>',
    });

    // ── Parsley ────────────────────────────────────────────────────────────────
    $('#clientForm').parsley();

    $('#clientModal').on('hidden.bs.modal', function () {
        $('#clientForm').parsley().reset();
        $('#clientForm')[0].reset();
        $('#clientId').val('');
        $('#clientModalLabel').text('New Client');
    });

    // ── Open create ────────────────────────────────────────────────────────────
    $('#btnAddClient').on('click', function () {
        new bootstrap.Modal(document.getElementById('clientModal')).show();
    });

    // ── Open edit ─────────────────────────────────────────────────────────────
    $('#clientsTable').on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        PortalApi.get('admin/clients/' + id).then(function (res) {
            var c = res.data;
            $('#clientId').val(c.id);
            $('#cName').val(c.name);
            $('#cContact').val(c.contact);
            $('#cEmail').val(c.email);
            $('#cPhone').val(c.phone);
            $('#cBillingAddress').val(c.billing_address);
            $('#cLicenseNumber').val(c.license_number);
            $('#cLicenseExpiry').val(c.license_expiry);
            $('#cDeaNumber').val(c.dea_number);
            $('#cCreditLimit').val(c.credit_limit);
            $('#cTerms').val(c.terms);
            $('#cStatus').val(c.status);
            $('#cControlledApproved').val(c.controlled_approved);
            $('#cCreditHold').val(c.credit_hold);
            $('#clientModalLabel').text('Edit Client');
            new bootstrap.Modal(document.getElementById('clientModal')).show();
        }).catch(function () { toastr.error('Failed to load client'); });
    });

    // ── Save ───────────────────────────────────────────────────────────────────
    $('#clientSaveBtn').on('click', function () {
        if (!$('#clientForm').parsley().validate()) return;

        var id      = $('#clientId').val();
        var payload = {
            name:                $('#cName').val(),
            contact:             $('#cContact').val(),
            email:               $('#cEmail').val(),
            phone:               $('#cPhone').val(),
            billing_address:     $('#cBillingAddress').val(),
            license_number:      $('#cLicenseNumber').val(),
            license_expiry:      $('#cLicenseExpiry').val() || null,
            dea_number:          $('#cDeaNumber').val(),
            credit_limit:        $('#cCreditLimit').val() || null,
            terms:               $('#cTerms').val() || null,
            status:              $('#cStatus').val(),
            controlled_approved: $('#cControlledApproved').val(),
            credit_hold:         $('#cCreditHold').val(),
        };

        var req = id
            ? PortalApi.put('admin/clients/' + id, payload)
            : PortalApi.post('admin/clients', payload);

        req.then(function () {
            bootstrap.Modal.getInstance(document.getElementById('clientModal')).hide();
            table.ajax.reload(null, false);
            toastr.success('Client saved');
        }).catch(function (err) {
            toastr.error((err && err.message) ? err.message : 'Failed to save client');
        });
    });

    // ── Delete ─────────────────────────────────────────────────────────────────
    $('#clientsTable').on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        swalConfirm('Delete this client?', 'This cannot be undone.', function () {
            PortalApi.del('admin/clients/' + id).then(function () {
                table.ajax.reload(null, false);
                toastr.success('Client deleted');
            }).catch(function () { toastr.error('Failed to delete client'); });
        });
    });
});
