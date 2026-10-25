{{-- The confirmation on save when the order raises a serious tier warning (scripts/tier-reading fills and opens it).
     After a redirect back (a warning appeared between the page load and the save), the server's list is shown and
     the modal opens on load. $isEdit: the edit form. Opened through the hidden trigger: only Bootstrap's Tooltip is a
     jQuery plugin in this build, so modals open through the data API. --}}
<button type="button" class="d-none" id="tier-confirm-open" data-bs-toggle="modal"
    data-bs-target="#tier-confirm-modal" aria-hidden="true" tabindex="-1"></button>
<div class="modal fade" id="tier-confirm-modal" tabindex="-1" aria-labelledby="tier-confirm-modal-title"
    aria-hidden="true" data-open-on-load="{{ session()->has('tier_confirm') ? 'true' : 'false' }}"
    data-missing="{{ json_encode(array_column(session('tier_confirm.warnings', []), 'code')) }}">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tier-confirm-modal-title">
                    <i class="fa fa-exclamation-triangle fa-fw text-danger" aria-hidden="true"></i>
                    {{ !empty($isEdit) ? 'Save this order anyway?' : 'Create this order anyway?' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="tier-confirm-body">
                @if(session()->has('tier_confirm'))
                    @include('myfinance2::orders.partials.tier-confirm-list', session('tier_confirm'))
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                    id="tier-confirm-back">Go back</button>
                <button type="button" class="btn btn-danger" id="tier-confirm-accept">
                    {{ !empty($isEdit) ? 'Save anyway' : 'Create anyway' }}
                </button>
            </div>
        </div>
    </div>
</div>
