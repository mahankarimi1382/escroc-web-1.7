@extends('admin.layouts.master')

@push('css')

@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Support Ticket")])
@endsection

@section('content')
    @include('admin.components.support-ticket.counter-card',['support_tickets' => $support_tickets])
    <div class="table-area mt-15">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("All Ticket") }}</h5>
                <div class="action-btn-wrapper d-none">
                    <button class="btn--base table-header-action-btn outline">{{ __("Action") }} <i class="las la-angle-down"></i></button>
                    <ul class="action-btn-list">
                        <li><button class="btn btn--danger delete-btn">{{ __("Delete") }}</button></li>
                    </ul>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                             <th>
                                <div class="d-flex align-items-center select-check-input">
                                    <input type="checkbox" name="select_all" id="select-all" >
                                    <label for="select-all" class="form-check-label mb-0">{{ __("Select All") }}</label>
                                </div>
                            </th>
                            <th>Ticket ID</th>
                            <th>User (Username)</th>
                            <th>Subject</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Last Reply</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($support_tickets as $item)
                            <tr data-item="{{ $item }}">
                                  <td>
                                    <input type="checkbox" class="w-auto" id="ticket-{{ $item->id }}" name="select_ticket[]" value="{{ $item->id }}">
                                </td>
                                <td>#{{ $item->token }}</td>
                                <td>
                                    {{ $item->user->username }}
                                </td>
                                <td>
                                    @if ($item->status == support_ticket_const()::DEFAULT)
                                        <span class="text--warning">{{ $item->subject }}</span>
                                    @elseif ($item->status == support_ticket_const()::SOLVED)
                                        <span class="text--success">{{ $item->subject }}</span>
                                    @elseif ($item->status == support_ticket_const()::ACTIVE)
                                        <span class="text--primary">{{ $item->subject }}</span>
                                    @elseif ($item->status == support_ticket_const()::PENDING)
                                        <span class="text--warning">{{ $item->subject }}</span>
                                    @endif
                                </td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->email }}</td>
                                <td>{{ Str::words($item->desc, 10, '...') }}</td>
                                <td>
                                    <span class="{{ $item->stringStatus->class }}">{{ $item->stringStatus->value }}</span>
                                </td>
                                <td>
                                    @if (count($item->conversations) > 0)
                                        {{ $item->conversations->last()->created_at->format("Y-m-d H:i A") ?? "" }}</td>
                                    @endif
                                <td>
                                    <a href="{{ setRoute('admin.support.ticket.conversation',encrypt($item->id)) }}" class="btn btn--base"><i class="las la-comment"></i></a>
                                     @include('admin.components.link.delete-default',[
                                        'href'          => "javascript:void(0)",
                                        'class'         => "delete-modal-button",
                                        'permission'    => "admin.support.ticket.delete",
                                    ])
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 9])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('script')
        <script>
                $('#select-all').on('change', function () {
                    let isChecked = $(this).is(':checked');
                    $('input[name="select_ticket[]"]').prop('checked', isChecked);

                    toggleActionBtn();
                });

                $('input[name="select_ticket[]"]').on('change', function () {
                    let total = $('input[name="select_ticket[]"]').length;
                    let checked = $('input[name="select_ticket[]"]:checked').length;

                    $('#select-all').prop('checked', total === checked);

                    toggleActionBtn();
                });

                function toggleActionBtn() {
                    let selectedCount = $('input[name="select_ticket[]"]:checked').length;

                    if (selectedCount > 0) {
                        $('.action-btn-wrapper').removeClass('d-none');
                    } else {
                        $('.action-btn-wrapper').addClass('d-none');
                    }
                }

                $(document).on("click",".delete-btn",function(event) {
                    const ids = $('input[name="select_ticket[]"]:checked').map(function () {
                        return $(this).val();
                    }).get();

                    event.preventDefault();
                    var actionRoute =  "{{ setRoute('admin.support.ticket.bulk.delete') }}";
                    var ticket    = ids;
                    var message     = `Are you sure you want to <strong>Delete</strong> all selected tickets?`;
                    openDeleteModal(actionRoute,ticket,message,"Enable","POST");
                });

                $(".delete-modal-button").click(function(){
                    var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
                    var actionRoute =  "{{ setRoute('admin.support.ticket.delete') }}";
                    var target      = oldData.id;
                    var message     = `Are you sure to delete <strong>Support Ticket</strong>?`;

                    openDeleteModal(actionRoute,target,message);
                });
            </script>
@endpush