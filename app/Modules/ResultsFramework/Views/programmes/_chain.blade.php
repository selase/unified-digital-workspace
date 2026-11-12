<ul class="ps-4">
    @forelse($nodes as $node)
        <li class="mb-4">
            <p><strong>{{ ucfirst($node->level) }}: {{ $node->title }}</strong></p>
            @if($node->description)<p>{{ $node->description }}</p>@endif
            @if($node->assumptions)<p>Assumptions: {{ $node->assumptions }}</p>@endif
            @if($node->risks)<p>Risks: {{ $node->risks }}</p>@endif
            @if($node->getRelation('linkedProject'))
                <p>Project: {{ $node->getRelation('linkedProject')->name }} ({{ $node->getRelation('linkedProject')->status }})</p>
            @endif
            @can('results.manage')
                <details>
                    <summary>Move or remove result</summary>
                    <form method="POST" action="{{ route('results-framework.programmes.results.move', [$programme->uuid, $node->uuid]) }}">
                        @csrf
                        @method('PATCH')
                        <label>Parent <select class="kt-select" name="parent_id"><option value="">Top level</option>@foreach($resultOptions as $option) @if($option->id !== $node->id)<option value="{{ $option->id }}" @selected($node->parent_id === $option->id)>{{ $option->title }}</option>@endif @endforeach</select></label>
                        <label>Position <input class="kt-input" type="number" min="0" name="sort_order" value="{{ $node->sort_order }}"></label>
                        <button class="kt-btn kt-btn-outline">Move result</button>
                    </form>
                    <form method="POST" action="{{ route('results-framework.programmes.results.destroy', [$programme->uuid, $node->uuid]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="kt-btn kt-btn-outline">Remove result</button>
                    </form>
                </details>
            @endcan
            @if($node->children->isNotEmpty())
                @include('results-framework::programmes._chain', ['nodes' => $node->children])
            @endif
        </li>
    @empty
        <li>No results yet.</li>
    @endforelse
</ul>
