<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Services\CmsThemeService;
use App\Modules\CmsCore\Services\CmsViewResolver;
use App\Modules\CmsCore\Support\EnquiryToken;
use App\Modules\HrmsCore\Http\Requests\PublicApplicationRequest;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use App\Modules\HrmsCore\Services\CareersApplicationService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The public careers page: open jobs and an application form. No login.
 */
final class HrmsPublicCareersController extends Controller
{
    public function __construct(
        private readonly CmsThemeService $theme,
        private readonly CmsViewResolver $viewResolver,
    ) {}

    public function index(Request $request): View
    {
        $postings = JobPosting::query()
            ->external()
            ->active()
            ->where(fn ($query) => $query->whereNull('posted_date')->orWhere('posted_date', '<=', now()))
            ->orderByDesc('posted_date')
            ->get();

        return view('hrms-core::public.careers', $this->shared($request) + ['postings' => $postings]);
    }

    public function show(Request $request, string $slug): View
    {
        $posting = $this->findOpen($slug);
        $posting->incrementViews();

        return view('hrms-core::public.career', $this->shared($request) + [
            'posting' => $posting,
            'formToken' => EnquiryToken::issue(),
        ]);
    }

    public function apply(PublicApplicationRequest $request, CareersApplicationService $applications, string $slug): RedirectResponse
    {
        $posting = $this->findOpen($slug);
        $data = $request->validated();

        if (filled($data['website'] ?? null)) {
            return $this->thanks($request, $slug);
        }

        if (! EnquiryToken::isValid($data['form_token'])) {
            return back()->withInput()->withErrors(['form_token' => 'This form has expired. Please reload the page and try again.']);
        }

        try {
            $applications->apply($posting, $request->safe()->except(['website', 'form_token', 'cv']), $request->file('cv'));
        } catch (DomainException $error) {
            return back()->withInput()->withErrors(['email' => $error->getMessage()]);
        }

        return $this->thanks($request, $slug);
    }

    private function thanks(Request $request, string $slug): RedirectResponse
    {
        return redirect($this->url($request, 'careers.show', ['slug' => $slug]))
            ->with('applied', 'Thank you. Your application has been received and we will be in touch.');
    }

    private function findOpen(string $slug): JobPosting
    {
        return JobPosting::query()->external()->active()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function shared(Request $request): array
    {
        return [
            'theme' => $this->theme,
            'cmsLayout' => $this->viewResolver->resolveLayout(),
            'careersUrl' => fn (string $name, array $parameters = []): string => $this->url($request, $name, $parameters),
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function url(Request $request, string $name, array $parameters = []): string
    {
        $prefix = str_starts_with((string) $request->route()?->getName(), 'hrms-core.website.') ? 'hrms-core.website.' : 'hrms-core.public.';

        return route($prefix.$name, $parameters);
    }
}
