{{-- Needs: $type ('bill'|'expense'), $parentUuid, $files (collection of FinAttachment), $permission --}}
<div class="flex flex-col gap-1 text-xs">
    @foreach($files as $file)
        <div class="flex items-center gap-2">
            <a class="text-primary" href="{{ route('finance.attachments.download', $file->uuid) }}">{{ $file->name }}</a>
            <span class="text-muted-foreground">{{ number_format($file->size / 1024) }} KB</span>
            @can($permission)
            <form method="POST" action="{{ route('finance.attachments.destroy', $file->uuid) }}" onsubmit="return confirm('Remove this attachment?')">@csrf @method('DELETE')<button class="text-destructive" type="submit">remove</button></form>
            @endcan
        </div>
    @endforeach
    @can($permission)
    <form method="POST" action="{{ route('finance.attachments.store', [$type, $parentUuid]) }}" enctype="multipart/form-data" class="flex items-center gap-2">@csrf
        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required class="text-xs">
        <button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Attach</button>
    </form>
    @endcan
</div>
