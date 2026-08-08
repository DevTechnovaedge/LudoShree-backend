<!-- Users -->

@can('permissions', [ $permission, 'view'])
<div class="col-12 col-md-3">
    <div class="card rounded-0">
        <div class="card-body py-0 px-2">
            <div class="row align-items-center align-items-stretch" style="height: 75px;">
                <div class="col-4 col-md-4 {{ $bgDark ?? '' }}" style="align-content: space-evenly;">
                    <div class="text-center records-count-wrapper">
                        <span class="records-count">{{ $count ?? 0 }}</span>
                    </div>
                </div>
                <div class="col-8 col-md-8  {{ $bg ?? '' }}" style="align-content: space-evenly;">
                    <div class="text-center px-2">
                        <h6 class="text-white">{{ $cardLabel ?? '' }}</h6>
                        <a href="{{ $url }}" class="text-white" target="_blank">
                            <small>View More</small>
                            </a>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endcan
<!-- End Users -->