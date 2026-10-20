@if($guideHtml !== null)
    <article aria-label="Module quickstarts" class="text-sm leading-relaxed text-foreground break-words [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-8 [&_h2]:mb-4 [&_h3]:text-lg [&_h3]:font-semibold [&_p]:my-3 [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:ps-6 [&_ol]:ps-6 [&_li]:my-2 [&_a]:text-primary [&_a]:underline [&_code]:break-words [&_pre]:overflow-x-auto">
        {!! $guideHtml !!}
    </article>
@else
    <div role="status" class="flex flex-col gap-2 text-sm">
        <h2 class="font-semibold text-foreground">Module help is temporarily unavailable</h2>
        <p class="text-muted-foreground">Please contact your workspace administrator to restore the bundled quickstart guide.</p>
    </div>
@endif
