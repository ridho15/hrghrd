<div class="flex flex-col items-center">

    {{-- Card Node --}}
    <div 
        class="tree-node w-64 bg-white rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md p-4 space-y-3 cursor-grab active:cursor-grabbing transition-all select-none"
        draggable="true"
        ondragstart="handleDragStart(event, {{ $node['id'] }}, '{{ addslashes($node['name']) }}')"
        ondragover="handleDragOver(event)"
        ondragleave="handleDragLeave(event)"
        ondrop="handleDrop(event, {{ $node['id'] }}, '{{ addslashes($node['name']) }}')"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-emerald-700 text-white font-extrabold text-xs flex items-center justify-center shrink-0 border border-emerald-800">
                    {{ $node['avatar_initials'] }}
                </div>
                <div class="truncate">
                    <a href="{{ route('people.show', $node['id']) }}" class="text-xs font-extrabold text-slate-900 hover:text-emerald-700 truncate block transition-colors">
                        {{ $node['name'] }}
                    </a>
                    <span class="text-[11px] font-semibold text-slate-500 block truncate">
                        {{ $node['position'] }}
                    </span>
                </div>
            </div>

            @if($node['subordinates_count'] > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-200 shrink-0" title="{{ $node['subordinates_count'] }} bawahan langsung">
                    {{ $node['subordinates_count'] }}
                </span>
            @endif
        </div>

        <div class="flex items-center justify-between text-[10px] text-slate-500 pt-2 border-t border-slate-100">
            <span class="truncate">{{ $node['branch'] }} · {{ $node['unit'] }}</span>
            <span class="text-slate-400">⋮⋮</span>
        </div>
    </div>

    {{-- Branching lines and child nodes --}}
    @if(!empty($node['children']))
        {{-- Downward line from parent --}}
        <div class="w-0.5 h-6 bg-slate-300"></div>

        <div class="relative flex justify-center gap-8 pt-6">
            {{-- Horizontal connector spanning children --}}
            @if(count($node['children']) > 1)
                <div class="absolute top-0 left-1/4 right-1/4 h-0.5 bg-slate-300"></div>
            @endif

            @foreach($node['children'] as $childNode)
                <div class="flex flex-col items-center relative">
                    {{-- Upward line to horizontal connector --}}
                    <div class="absolute -top-6 w-0.5 h-6 bg-slate-300"></div>
                    @include('org-chart.partials.node', ['node' => $childNode])
                </div>
            @endforeach
        </div>
    @endif

</div>
