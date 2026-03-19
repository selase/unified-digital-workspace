@props([
    'name' => 'body',
    'content' => '',
    'uploadUrl' => null,
    'required' => false,
    'label' => 'Body',
    'placeholder' => '',
    'minHeight' => '200px',
])

<div class="kt-form-item lg:col-span-2" x-data="tiptapEditor(@js($content), @js($uploadUrl ?? ''))">
    @if($label)
        <label class="kt-form-label">{{ $label }} @if($required)<span class="text-destructive">*</span>@endif</label>
    @endif
    <input type="hidden" name="{{ $name }}" x-model="content">

    {{-- Toolbar — @mousedown.prevent keeps focus in the ProseMirror editor --}}
    <div class="flex flex-wrap items-center gap-1 rounded-t-lg bg-muted/30 px-2 py-1.5">
        <button type="button" @mousedown.prevent @click="toggleBold()" :class="isActive('bold') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs font-bold hover:bg-muted/50" title="Bold">B</button>
        <button type="button" @mousedown.prevent @click="toggleItalic()" :class="isActive('italic') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs italic hover:bg-muted/50" title="Italic">I</button>
        <button type="button" @mousedown.prevent @click="toggleStrike()" :class="isActive('strike') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs line-through hover:bg-muted/50" title="Strikethrough">S</button>
        <span class="mx-1 h-4 w-px bg-border"></span>
        <button type="button" @mousedown.prevent @click="toggleHeading(2)" :class="isActive('heading', {level: 2}) ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs font-semibold hover:bg-muted/50" title="Heading 2">H2</button>
        <button type="button" @mousedown.prevent @click="toggleHeading(3)" :class="isActive('heading', {level: 3}) ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs font-semibold hover:bg-muted/50" title="Heading 3">H3</button>
        <span class="mx-1 h-4 w-px bg-border"></span>
        <button type="button" @mousedown.prevent @click="toggleBulletList()" :class="isActive('bulletList') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs hover:bg-muted/50" title="Bullet List">&bull; List</button>
        <button type="button" @mousedown.prevent @click="toggleOrderedList()" :class="isActive('orderedList') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs hover:bg-muted/50" title="Ordered List">1. List</button>
        <button type="button" @mousedown.prevent @click="toggleBlockquote()" :class="isActive('blockquote') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs hover:bg-muted/50" title="Blockquote">&ldquo; Quote</button>
        <button type="button" @mousedown.prevent @click="toggleCode()" :class="isActive('code') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs font-mono hover:bg-muted/50" title="Inline Code">&lt;/&gt;</button>
        <span class="mx-1 h-4 w-px bg-border"></span>
        <button type="button" @mousedown.prevent @click="setLink()" :class="isActive('link') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs hover:bg-muted/50" title="Add Link">Link</button>
        <button type="button" @mousedown.prevent @click="addImage()" class="rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted/50" title="Upload Image" :disabled="uploading">
            <span x-show="!uploading">Image</span>
            <span x-show="uploading" x-cloak>Uploading...</span>
        </button>
        <button type="button" @mousedown.prevent @click="setHorizontalRule()" class="rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted/50" title="Horizontal Rule">&mdash;</button>
        <span class="mx-1 h-4 w-px bg-border"></span>
        <button type="button" @mousedown.prevent @click="undo()" class="rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted/50" title="Undo">Undo</button>
        <button type="button" @mousedown.prevent @click="redo()" class="rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted/50" title="Redo">Redo</button>
    </div>

    {{-- Editor area --}}
    <div class="kt-form-control">
        <div x-ref="editor" class="kt-textarea !p-0" style="min-height: {{ $minHeight }};"></div>
    </div>
    @error($name) <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
</div>
