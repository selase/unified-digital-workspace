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
                        <label class="kt-form-label">Site Icon</label>
                        <div class="kt-form-control">
                            <select class="kt-select" name="site_icon_media_id">
                                <option value="">None</option>
                                @foreach($mediaItems as $media)
                                    <option value="{{ $media->id }}" @selected((int) ($settings['site_icon_media_id'] ?? 0) === $media->id)>
                                        {{ $media->title ?: $media->original_filename ?: $media->filename }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Square PNG, ideally 512×512+. Used to derive favicons for web, iOS apple-touch-icon, and Android PWA icons.</p>
                        @error('site_icon_media_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Theme Color</label>
                        <div class="kt-form-control flex items-center gap-3">
                            <input class="kt-input w-20" name="theme_color" type="color" value="{{ old('theme_color', $settings['theme_color'] ?? $settings['primary_color'] ?? '#1d4ed8') }}">
                            <input class="kt-input flex-1" type="text" value="{{ old('theme_color', $settings['theme_color'] ?? $settings['primary_color'] ?? '#1d4ed8') }}" readonly>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Used for the browser chrome on mobile and the PWA splash background. Defaults to Primary Color.</p>
                        @error('theme_color') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
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

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">Save Settings</button>
                </div>
            </form>
        </div>
    </section>
@endsection
