<?php
/**
 * Common modal templates — included once per page in the layout.
 * JS triggers these via Bootstrap's modal() API or SweetAlert2.
 */
?>
<!-- Generic confirm modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="confirmModalLabel">Confirm</h4>
            </div>
            <div class="modal-body">
                <p id="confirmModalMessage">Are you sure?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmModalOk">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Generic loading overlay -->
<div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
     background:rgba(255,255,255,.6); z-index:9999; text-align:center; padding-top:20%;">
    <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
</div>
