@props([
    'pretitle' => null,
    'title' => null,
    'text' => null,
    'icon' => null,
])

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                @if (filled($pretitle))
                    <div class="page-pretitle">{{ $pretitle }}</div>
                @endif

                <div class="d-flex align-items-center gap-2">
                    @if (filled($icon))
                        <span class="avatar avatar-sm bg-primary-lt text-primary rounded flex-shrink-0">
                            <i class="ti ti-{{ $icon }} fs-3" aria-hidden="true"></i>
                        </span>
                    @endif

                    <div>
                        @if (filled($title))
                            <h1 class="page-title mb-0">{{ $title }}</h1>
                        @endif

                        @if (filled($text))
                            <div class="text-secondary small">{{ $text }}</div>
                        @endif
                    </div>
                </div>
            </div>

            @isset($actions)
                <div class="col-auto ms-auto d-print-none">
                    <div class="btn-list">
                        {{ $actions }}
                    </div>
                </div>
            @endisset
        </div>
    </div>
</div>
