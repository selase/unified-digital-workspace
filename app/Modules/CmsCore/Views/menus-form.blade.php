@extends('layouts.metronic.app')

@section('title', $menu ? 'Edit Menu' : 'New Menu')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core / Menus</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ $menu ? 'Edit Menu: ' . $menu->name : 'New Menu' }}</h1>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.menus.index') }}">Back to Menus</a>
            </div>
        </div>

        <div class="kt-card p-6" x-data="menuBuilder()">
            <form
                action="{{ $menu ? route('cms-core.menus.update', $menu) : route('cms-core.menus.store') }}"
                class="kt-form"
                method="POST"
            >
                @csrf
                @if($menu) @method('PUT') @endif

                {{-- Menu details --}}
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Menu Name <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="name" required type="text" value="{{ old('name', $menu?->name) }}">
                        </div>
                        @error('name') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Slug</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="slug" placeholder="auto-generated" type="text" value="{{ old('slug', $menu?->slug) }}">
                        </div>
                        @error('slug') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Menu items --}}
                <div class="mt-8 border-t border-border pt-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-foreground">Menu Items</h3>
                        <button type="button" @click="addItem()" class="kt-btn kt-btn-sm kt-btn-outline">Add Item</button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="flex items-start gap-3 rounded-lg bg-muted/30 p-3">
                                <div class="flex-1 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <input
                                            class="kt-input text-sm"
                                            :name="`items[label][${index}]`"
                                            placeholder="Label *"
                                            type="text"
                                            x-model="item.label"
                                            required
                                        >
                                    </div>
                                    <div>
                                        <input
                                            class="kt-input text-sm"
                                            :name="`items[url][${index}]`"
                                            placeholder="URL (or select post)"
                                            type="text"
                                            x-model="item.url"
                                        >
                                    </div>
                                    <div>
                                        <select class="kt-select text-sm" :name="`items[post_id][${index}]`" x-model="item.post_id">
                                            <option value="">Link to post/page</option>
                                            <optgroup label="Pages">
                                                @foreach($pages as $page)
                                                    <option value="{{ $page->id }}">{{ $page->title }}</option>
                                                @endforeach
                                            </optgroup>
                                            <optgroup label="Posts">
                                                @foreach($posts as $post)
                                                    <option value="{{ $post->id }}">{{ $post->title }}</option>
                                                @endforeach
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>
                                <button type="button" @click="removeItem(index)" class="mt-1 rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Remove item">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <template x-if="items.length === 0">
                        <p class="py-6 text-center text-sm text-muted-foreground">No menu items yet. Click "Add Item" to start building your menu.</p>
                    </template>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.menus.index') }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">{{ $menu ? 'Save Menu' : 'Create Menu' }}</button>
                </div>
            </form>
        </div>

        @if($menu)
            <div class="kt-card p-6">
                <form action="{{ route('cms-core.menus.destroy', $menu) }}" method="POST" onsubmit="return confirm('Delete this menu and all its items?')">
                    @csrf @method('DELETE')
                    <button class="kt-btn kt-btn-outline text-destructive w-full" type="submit">Delete Menu</button>
                </form>
            </div>
        @endif
    </section>

    @php
        $itemsJson = $menuItems->map(function ($item) {
            return [
                'label' => $item->label,
                'url' => $item->url ?? '',
                'post_id' => $item->post_id ? (string) $item->post_id : '',
            ];
        })->values();
    @endphp
    <script>
        function menuBuilder() {
            return {
                items: {!! json_encode($itemsJson) !!},
                addItem() {
                    this.items.push({ label: '', url: '', post_id: '' });
                },
                removeItem(index) {
                    this.items.splice(index, 1);
                },
            };
        }
    </script>
@endsection
