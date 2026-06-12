<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\Activity;
use App\Models\Event;
use App\Support\Ui\ActivityListingImageResolver;
use App\Support\Ui\EventListingImageResolver;
use App\Support\Ui\ListingCardPicture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

final class Seo
{
    public static function defaults(): SeoMetadata
    {
        return new SeoMetadata(
            title: self::appName(),
            description: (string) __('ui.seo.default_description'),
            canonical: url()->current(),
        );
    }

    public static function forWelcome(): SeoMetadata
    {
        return new SeoMetadata(
            title: self::pageTitle((string) __('ui.seo.welcome_title')),
            description: (string) __('ui.seo.welcome_description'),
            canonical: url('/'),
        );
    }

    public static function forSearch(): SeoMetadata
    {
        return new SeoMetadata(
            title: self::pageTitle((string) __('ui.browse.search_page_title')),
            description: (string) __('ui.seo.search_description'),
            canonical: route('search.index'),
        );
    }

    public static function forPrivacy(): SeoMetadata
    {
        return new SeoMetadata(
            title: self::pageTitle((string) __('legal.privacy.title')),
            description: (string) __('ui.seo.privacy_description'),
            canonical: route('privacy'),
        );
    }

    public static function forTerms(): SeoMetadata
    {
        return new SeoMetadata(
            title: self::pageTitle((string) __('legal.terms.title')),
            description: (string) __('ui.seo.terms_description'),
            canonical: route('terms'),
        );
    }

    public static function forContact(): SeoMetadata
    {
        return new SeoMetadata(
            title: self::pageTitle((string) __('legal.contact.title')),
            description: (string) __('ui.seo.contact_description'),
            canonical: route('contact'),
        );
    }

    public static function forEvent(Event $event): SeoMetadata
    {
        $description = rich_text_excerpt($event->description, 160);

        if ($description === '') {
            $description = (string) __('ui.seo.event_fallback_description', ['name' => $event->name]);
        }

        return new SeoMetadata(
            title: self::pageTitle((string) $event->name),
            description: $description,
            canonical: route('events.show', $event),
            type: 'article',
            image: self::pictureUrl(app(EventListingImageResolver::class)->resolve($event)),
        );
    }

    public static function forActivity(Activity $activity): SeoMetadata
    {
        $description = rich_text_excerpt($activity->description, 160);

        if ($description === '') {
            $description = (string) __('ui.seo.activity_fallback_description', ['name' => $activity->name]);
        }

        return new SeoMetadata(
            title: self::pageTitle((string) $activity->name),
            description: $description,
            canonical: route('activities.show', $activity),
            type: 'article',
            image: self::pictureUrl(app(ActivityListingImageResolver::class)->resolve($activity)),
        );
    }

    public static function fromCurrentRoute(): SeoMetadata
    {
        return match (Route::currentRouteName()) {
            'search.index' => self::forSearch(),
            'events.show' => self::forEvent(self::routeModel('event', Event::class)),
            'activities.show' => self::forActivity(self::routeModel('activity', Activity::class)),
            default => self::defaults(),
        };
    }

    public static function pageTitle(string $pageTitle): string
    {
        return $pageTitle.' | '.self::appName();
    }

    private static function appName(): string
    {
        return (string) config('app.name', 'nerdik');
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $modelClass
     * @return T
     */
    private static function routeModel(string $parameter, string $modelClass): Model
    {
        $model = request()->route($parameter);

        if ($model instanceof $modelClass) {
            return $model;
        }

        abort(404);
    }

    private static function pictureUrl(ListingCardPicture $picture): ?string
    {
        if ($picture->sources === null) {
            return null;
        }

        return self::absoluteUrl($picture->sources->jpegSrc());
    }

    private static function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
