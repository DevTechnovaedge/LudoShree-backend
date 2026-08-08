<!-- Users -->
 @php
    $gradiant_arr   = ['yellow-gradiant',  'blue-gradiant', 'red-gradiant', 'voilet-gradiant', 'green-gradiant'];
 @endphp

@can('permissions', [ $permission, 'view'])
<div class="col-md-3 mb-4">
    <a href="{{ $url }}" class="nav-link text-dark">
        <div class="card dashboard-card {{ $gradiant_arr[rand(0,4)] }}">
            <div class="card-body">
                <div class="row">
                        <div class="col-md-12">
                            <div class="text-center px-4">
                            <span class="today-count">{{ $count ?? 0 }}</span>
                            <h5>{{ $cardLabel ?? '' }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </a>
</div>
@endcan
<!-- End Users -->