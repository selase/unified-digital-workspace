@extends('layouts.metronic.app')

@section('title', 'Website Settings')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Website Settings</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Configure your public website appearance, branding, and navigation.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="kt-card p-6">
            <form action="{{ route('cms-core.settings.update') }}" class="kt-form" method="POST">
                @csrf
                @method('PUT')

                {{-- Branding --}}
                <h3 class="mb-4 text-sm font-semibold text-foreground">Branding</h3>
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Site Name</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="site_name" type="text" value="{{ old('site_name', $settings['site_name'] ?? '') }}">
                        </div>
                        @error('site_name') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Site Tagline</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="site_tagline" type="text" value="{{ old('site_tagline', $settings['site_tagline'] ?? '') }}">
                        </div>
                        @error('site_tagline') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Primary Color</label>
                        <div class="kt-form-control flex items-center gap-3">
                            <input class="kt-input w-20" name="primary_color" type="color" value="{{ old('primary_color', $settings['primary_color'] ?? '#1d4ed8') }}">
                            <input class="kt-input flex-1" type="text" value="{{ old('primary_color', $settings['primary_color'] ?? '#1d4ed8') }}" readonly>
                        </div>
                        @error('primary_color') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Secondary Color</label>
                        <div class="kt-form-control flex items-center gap-3">
                            <input class="kt-input w-20" name="secondary_color" type="color" value="{{ old('secondary_color', $settings['secondary_color'] ?? '#64748b') }}">
                            <input class="kt-input flex-1" type="text" value="{{ old('secondary_color', $settings['secondary_color'] ?? '#64748b') }}" readonly>
                        </div>
                        @error('secondary_color') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Logo</label>
                        <div class="kt-form-control">
                            <select class="kt-select" name="logo_media_id">
                                <option value="">None</option>
                                @foreach($mediaItems as $media)
                                    <option value="{{ $media->id }}" @selected((int) ($settings['logo_media_id'] ?? 0) === $media->id)>
                                        {{ $media->title ?: $media->original_filename ?: $media->filename }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('logo_media_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Favicon</label>
                        <div class="kt-form-control">
                            <select class="kt-select" name="favicon_media_id">
                                <option value="">None</option>
                                @foreach($mediaItems as $media)
                                    <option value="{{ $media->id }}" @selected((int) ($settings['favicon_media_id'] ?? 0) === $media->id)>
                                        {{ $media->title ?: $media->original_filename ?: $media->filename }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('favicon_media_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Navigation --}}
                <h3 class="mb-4 mt-8 border-t border-border pt-6 text-sm font-semibold text-foreground">Navigation</h3>
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Header Menu</label>
                        <div class="kt-form-control">
                            <select class="kt-select" name="header_menu_id">
                                <option value="">Auto-detect (main-navigation)</option>
                                @foreach($menus as $menu)
                                    <option value="{{ $menu->id }}" @selected((int) ($settings['header_menu_id'] ?? 0) === $menu->id)>
                                        {{ $menu->name }} ({{ $menu->slug }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('header_menu_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Footer Menu</label>
                        <div class="kt-form-control">
                            <select class="kt-select" name="footer_menu_id">
                                <option value="">None</option>
                                @foreach($menus as $menu)
                                    <option value="{{ $menu->id }}" @selected((int) ($settings['footer_menu_id'] ?? 0) === $menu->id)>
                                        {{ $menu->name }} ({{ $menu->slug }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('footer_menu_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Footer Text</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="footer_text" type="text" value="{{ old('footer_text', $settings['footer_text'] ?? '') }}" placeholder="Auto-generated if empty">
                        </div>
                        @error('footer_text') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Theme --}}
                <h3 class="mb-4 mt-8 border-t border-border pt-6 text-sm font-semibold text-foreground">Theme</h3>
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Active Theme</label>
                        <div class="kt-form-control">
                            <select class="kt-select" name="active_theme">
                                @foreach($availableThemes as $theme)
                                    <option value="{{ $theme }}" @selected(($settings['active_theme'] ?? 'default') === $theme)>
                                        {{ ucfirst($theme) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('active_theme') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Custom CSS</label>
                        <div class="kt-form-control">
                            <textarea class="kt-textarea min-h-[120px] font-mono text-sm" name="custom_css" placeholder="/* Add custom styles here */">{{ old('custom_css', $settings['custom_css'] ?? '') }}</textarea>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Custom CSS injected into the public website. Use with care.</p>
                        @error('custom_css') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Homepage Slides --}}
                <h3 class="mb-4 mt-8 border-t border-border pt-6 text-sm font-semibold text-foreground">Homepage Slides</h3>
                <p class="mb-4 text-xs text-muted-foreground">Manage the hero carousel slides on the homepage. Each slide has a pretitle, title, description, and up to two call-to-action buttons.</p>

                @php
                    $existingSlides = json_decode($settings['homepage_slides'] ?? '[]', true) ?: [];
                @endphp

                <div x-data="slidesEditor()" class="space-y-4">
                    <template x-for="(slide, index) in slides" :key="index">
                        <div class="rounded-lg bg-muted/30 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-semibold text-muted-foreground" x-text="'Slide ' + (index + 1)"></span>
                                <button type="button" @click="removeSlide(index)" class="text-xs text-destructive hover:underline">Remove</button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="text-xs text-muted-foreground">Pretitle</label>
                                    <input class="kt-input text-sm" :name="'slides['+index+'][pretitle]'" x-model="slide.pretitle" placeholder="e.g. Thyroid Ghana Foundation">
                                </div>
                                <div>
                                    <label class="text-xs text-muted-foreground">Title</label>
                                    <input class="kt-input text-sm" :name="'slides['+index+'][title]'" x-model="slide.title" placeholder="Main heading">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-xs text-muted-foreground">Description</label>
                                    <textarea class="kt-textarea text-sm" rows="2" :name="'slides['+index+'][text]'" x-model="slide.text" placeholder="Slide description text"></textarea>
                                </div>
                                <div>
                                    <label class="text-xs text-muted-foreground">Button 1 Label</label>
                                    <input class="kt-input text-sm" :name="'slides['+index+'][cta_label]'" x-model="slide.cta_label" placeholder="e.g. Learn More">
                                </div>
                                <div>
                                    <label class="text-xs text-muted-foreground">Button 1 URL</label>
                                    <input class="kt-input text-sm" :name="'slides['+index+'][cta_url]'" x-model="slide.cta_url" placeholder="/about or full URL">
                                </div>
                                <div>
                                    <label class="text-xs text-muted-foreground">Button 2 Label <span class="opacity-50">(optional)</span></label>
                                    <input class="kt-input text-sm" :name="'slides['+index+'][cta2_label]'" x-model="slide.cta2_label" placeholder="e.g. Donate">
                                </div>
                                <div>
                                    <label class="text-xs text-muted-foreground">Button 2 URL</label>
                                    <input class="kt-input text-sm" :name="'slides['+index+'][cta2_url]'" x-model="slide.cta2_url" placeholder="/donate or full URL">
                                </div>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="addSlide()" class="kt-btn kt-btn-sm kt-btn-outline">Add Slide</button>
                </div>

                <script>
                    function slidesEditor() {
                        return {
                            slides: {!! json_encode($existingSlides ?: [['pretitle' => '', 'title' => '', 'text' => '', 'cta_label' => '', 'cta_url' => '', 'cta2_label' => '', 'cta2_url' => '']]) !!},
                            addSlide() {
                                this.slides.push({ pretitle: '', title: '', text: '', cta_label: '', cta_url: '', cta2_label: '', cta2_url: '' });
                            },
                            removeSlide(index) {
                                this.slides.splice(index, 1);
                            },
                        };
                    }
                </script>

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">Save Settings</button>
                </div>
            </form>
        </div>
    </section>
@endsection
