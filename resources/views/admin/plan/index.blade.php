@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Name')</th>
                                    <th>@lang('Price')</th>
                                    <th>@lang('Business Volume (BV)')</th>
                                    <th>@lang('Referral Commission')</th>
                                    <th>@lang('Tree Commission')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($plans as $key => $plan)
                                    <tr>
                                        <td>{{ __($plan->name) }}</td>
                                        <td>{{ showAmount($plan->price) }} {{ __($general->cur_text) }}</td>
                                        <td>{{ $plan->bv }}</td>
                                        <td> {{ showAmount($plan->ref_com) }} {{ __($general->cur_text) }}</td>

                                        <td>
                                            {{ showAmount($plan->tree_com) }} {{ __($general->cur_text) }}
                                        </td>
                                        <td>
                                            @php echo $plan->statusBadge @endphp
                                        </td>

                                        <td>
                                            <button class="btn btn-sm btn-outline--primary edit" data-toggle="tooltip" data-id="{{ $plan->id }}" data-name="{{ $plan->name }}" data-bv="{{ $plan->bv }}" data-price="{{ getAmount($plan->price) }}" data-ref_com="{{ getAmount($plan->ref_com) }}" data-tree_com="{{ getAmount($plan->tree_com) }}" data-original-title="@lang('Edit')" type="button">
                                                <i class="la la-pencil"></i> @lang('Edit')
                                            </button>

                                            @if ($plan->status == Status::DISABLE)
                                                <button class="btn btn-sm btn-outline--success ms-1 confirmationBtn" data-question="@lang('Are you sure to enable this plan?')" data-action="{{ route('admin.plan.status', $plan->id) }}">
                                                    <i class="la la-eye"></i> @lang('Enable')
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to disable this plan?')" data-action="{{ route('admin.plan.status', $plan->id) }}">
                                                    <i class="la la-eye-slash"></i> @lang('Disable')
                                                </button>
                                            @endif

                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table><!-- table end -->
                    </div>
                </div>
                @if ($plans->hasPages())
                    <div class="card-footer py-4">
                        @php echo paginateLinks($plans) @endphp
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{--    edit modal --}}
    <div class="modal fade" id="edit-plan" role="dialog" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Edit Plan')</h5>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form method="post" action="{{ route('admin.plan.save') }}">
                    @csrf
                    <div class="modal-body">
                        <input class="form-control plan_id" name="id" type="hidden">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>@lang('Name')</label>
                                <input class="form-control name" name="name" type="text" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label> @lang('Price') </label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $general->cur_sym }}</span>
                                    <input class="form-control price" name="price" type="number" step="any" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>@lang('Business Volume (BV)')</label> <i class="fas fa-question-circle text--gray" data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('When someone buys this plan, all of his ancestors will get this value which will be used for a matching bonus.')"></i>
                            <input class="form-control bv" name="bv" type="number" required>
                        </div>

                        <div class="form-group">
                            <label>@lang('Referral Commission')</label> <i class="fas fa-question-circle text--gray" data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('If a user who subscribed to this plan, refers someone and if the referred user buys a plan, then he will get this amount.')"></i>
                            <div class="input-group">
                                <span class="input-group-text">{{ $general->cur_sym }}</span>
                                <input class="form-control ref_com" name="ref_com" type="number" step="any" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>@lang('Tree Commission')</label> <i class="fas fa-question-circle text--gray" data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('When someone buys this plan, all of his ancestors will get this amount.')"></i>
                            <div class="input-group">
                                <span class="input-group-text">{{ $general->cur_sym }}</span>
                                <input class="form-control tree_com" name="tree_com" type="number" step="any" required>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button class="btn btn--primary w-100 h-45" type="submit">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="add-plan" role="dialog" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Add New plan')</h5>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form method="post" action="{{ route('admin.plan.save') }}">
                    @csrf
                    <div class="modal-body">

                        <input class="form-control plan_id" name="id" type="hidden">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>@lang('Name')</label>
                                <input class="form-control" name="name" type="text" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>@lang('Price') </label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $general->cur_sym }}</span>
                                    <input class="form-control" name="price" type="number" step="any" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label> @lang('Business Volume (BV)')</label> <i class="fas fa-question-circle text--gray" data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('When someone buys this plan, all of his ancestors will get this value which will be used for a matching bonus.')"></i>
                            <input class="form-control" name="bv" type="number" type="number" required>
                        </div>
                        <div class="form-group">
                            <label> @lang('Referral Commission')</label> <i class="fas fa-question-circle text--gray" data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('If a user who subscribed to this plan, refers someone and if the referred user buys a plan, then he will get this amount.')"></i>
                            <div class="input-group">
                                <span class="input-group-text">{{ $general->cur_sym }}</span>
                                <input class="form-control" name="ref_com" type="number" step="any" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label> @lang('Tree Commission')</label> <i class="fas fa-question-circle text--gray" data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('When someone buys this plan, all of his ancestors will get this amount.')"></i>
                            <div class="input-group">
                                <span class="input-group-text">{{ $general->cur_sym }}</span>
                                <input class="form-control" name="tree_com" type="number" step="any" required>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button class="btn btn--primary w-100 h-45" type="submit">@lang('Submit')</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <button class="btn btn-sm btn-outline--primary add-plan" type="button">
        <i class="la la-plus"></i>@lang('Add New')
    </button>
@endpush

@push('script')
    <script>
        "use strict";
        (function($) {
            $('.edit').on('click', function() {
                var modal = $('#edit-plan');
                modal.find('.name').val($(this).data('name'));
                modal.find('.price').val($(this).data('price'));
                modal.find('.bv').val($(this).data('bv'));
                modal.find('.ref_com').val($(this).data('ref_com'));
                modal.find('.tree_com').val($(this).data('tree_com'));
                modal.find('input[name=id]').val($(this).data('id'));
                modal.modal('show');
            });

            $('.add-plan').on('click', function() {
                var modal = $('#add-plan');
                modal.modal('show');
            });
        })(jQuery);
    </script>
@endpush
