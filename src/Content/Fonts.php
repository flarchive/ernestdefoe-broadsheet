<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Broadsheet\Content;

use ErnestDefoe\Broadsheet\ActiveTheme;
use ErnestDefoe\Broadsheet\Settings;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The fonts switch and the browser toolbar colour, stamped by the server
 * rather than set from JS after boot, so the first paint is already right.
 * A visitor Wardrobe is serving another theme gets none of it.
 */
final class Fonts
{
    /** The two faces the nameplate and section nav use above the fold. */
    private const PRELOAD = ['newsreader-latin-opsz-normal', 'ibm-plex-sans-condensed-latin-500-normal'];

    public function __construct(protected ActiveTheme $theme, protected Settings $settings, protected Filesystem $filesystem)
    {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        if (! $this->theme->for($request)) {
            return;
        }

        $fonts = $this->settings->fonts();
        $document->extraAttributes['data-broadsheet-fonts'] = fn () => $fonts ? 'on' : 'off';

        // Mobile browsers tint their own toolbar from theme-color, which core
        // sets to the primary colour: a coloured strip glued to the top of a
        // newsprint page. Paper by day, ink by night.
        $scheme = (string) RequestUtil::getActor($request)->getPreference('colorScheme');
        $document->meta['theme-color'] = str_starts_with($scheme, 'dark') ? '#14130F' : '#F6F3EC';

        if (! $fonts) {
            return;
        }

        // The rest load on demand, by unicode-range.
        $assets = $this->filesystem->disk('flarum-assets');
        foreach (self::PRELOAD as $face) {
            $document->head[] = '<link rel="preload" href="'.e($assets->url('extensions/ernestdefoe-broadsheet/fonts/'.$face.'.woff2')).'" as="font" type="font/woff2" crossorigin>';
        }
    }
}
