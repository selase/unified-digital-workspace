<?php

declare(strict_types=1);

use App\Modules\CmsCore\Http\Controllers\Api\V1\CategoryController;
use App\Modules\CmsCore\Http\Controllers\Api\V1\MediaController;
use App\Modules\CmsCore\Http\Controllers\Api\V1\MenuController;
use App\Modules\CmsCore\Http\Controllers\Api\V1\PostController;
use App\Modules\CmsCore\Http\Controllers\Api\V1\PostTypeController;
use App\Modules\CmsCore\Http\Controllers\Api\V1\ReadOnlyResourceController;
use App\Modules\CmsCore\Http\Controllers\Api\V1\SettingController;
use App\Modules\CmsCore\Http\Controllers\Api\V1\TagController;
use App\Modules\CmsCore\Http\Resources\CategoryResource;
use App\Modules\CmsCore\Http\Resources\MediaResource;
use App\Modules\CmsCore\Http\Resources\MediaVariantResource;
use App\Modules\CmsCore\Http\Resources\MenuItemResource;
use App\Modules\CmsCore\Http\Resources\MenuResource;
use App\Modules\CmsCore\Http\Resources\PostMetaResource;
use App\Modules\CmsCore\Http\Resources\PostResource;
use App\Modules\CmsCore\Http\Resources\PostRevisionResource;
use App\Modules\CmsCore\Http\Resources\PostTypeResource;
use App\Modules\CmsCore\Http\Resources\SettingResource;
use App\Modules\CmsCore\Http\Resources\TagResource;
use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\MediaVariant;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\MenuItem;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use App\Modules\CmsCore\Models\PostRevision;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Models\Setting;
use App\Modules\CmsCore\Models\Tag;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'module' => 'cms-core',
        'version' => config('modules.cms-core.version', '1.0.0'),
        'status' => 'active',
    ]);
})->name('index');

Route::prefix('v1')->name('v1.')->group(function (): void {
    $registerReadOnly = function (string $uri, string $model, string $resource, array $with = [], array $withCount = []): void {
        $name = str_replace('/', '.', $uri);

        Route::get($uri, [ReadOnlyResourceController::class, 'index'])
            ->defaults('model', $model)
            ->defaults('resource', $resource)
            ->defaults('with', $with)
            ->defaults('withCount', $withCount)
            ->name("{$name}.index");

        Route::get("{$uri}/{id}", [ReadOnlyResourceController::class, 'show'])
            ->defaults('model', $model)
            ->defaults('resource', $resource)
            ->defaults('with', $with)
            ->defaults('withCount', $withCount)
            ->name("{$name}.show");
    };

    $registerReadOnly('post-types', PostType::class, PostTypeResource::class, [], ['posts']);
    $registerReadOnly('posts', Post::class, PostResource::class, [
        'postType',
        'categories',
        'tags',
        'featuredMedia',
        'media',
        'meta',
        'revisions',
    ]);
    $registerReadOnly('categories', Category::class, CategoryResource::class, ['parent', 'children']);
    $registerReadOnly('tags', Tag::class, TagResource::class);
    $registerReadOnly('media', Media::class, MediaResource::class, ['variants']);
    $registerReadOnly('media-variants', MediaVariant::class, MediaVariantResource::class, ['media']);
    $registerReadOnly('menus', Menu::class, MenuResource::class, ['items']);
    $registerReadOnly('menu-items', MenuItem::class, MenuItemResource::class, ['children']);
    $registerReadOnly('post-meta', PostMeta::class, PostMetaResource::class);
    $registerReadOnly('post-revisions', PostRevision::class, PostRevisionResource::class);
    $registerReadOnly('settings', Setting::class, SettingResource::class);

    Route::post('post-types', [PostTypeController::class, 'store'])->name('post-types.store');
    Route::put('post-types/{postType}', [PostTypeController::class, 'update'])->name('post-types.update');

    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');

    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
    Route::put('tags/{tag}', [TagController::class, 'update'])->name('tags.update');

    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::put('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
    Route::put('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');

    Route::post('settings', [SettingController::class, 'store'])->name('settings.store');
    Route::put('settings/{setting}', [SettingController::class, 'update'])->name('settings.update');
});
