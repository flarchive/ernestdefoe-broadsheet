<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ErnestDefoe\Broadsheet\ActiveTheme;
use ErnestDefoe\Broadsheet\Api;
use ErnestDefoe\Broadsheet\Content;
use ErnestDefoe\Broadsheet\Settings;
use Flarum\Extend;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Psr\Http\Message\ServerRequestInterface;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->content(Content\Front::class)
        /*
         * Stamped on <html> by the server, not set from JS after boot, so the
         * first paint already has the right fonts.
         *
         * 🚨 CLOSURES, not strings: core calls any value is_callable() says
         * yes to, and that is true of a string naming a PHP function.
         */
        ->content(function (Document $document, ServerRequestInterface $request) {
            // A visitor Wardrobe is serving another theme gets none of this.
            if (! resolve(ActiveTheme::class)->for($request)) {
                return;
            }

            $fonts = (bool) resolve(SettingsRepositoryInterface::class)->get('ernestdefoe-broadsheet.fonts', true);
            $document->extraAttributes['data-broadsheet-fonts'] = fn () => $fonts ? 'on' : 'off';

            // Mobile browsers tint their own toolbar from theme-color, which
            // core sets to the primary colour: a coloured strip glued to the
            // top of a newsprint page. Paper by day, ink by night.
            $scheme = (string) RequestUtil::getActor($request)->getPreference('colorScheme');
            $document->meta['theme-color'] = str_starts_with($scheme, 'dark') ? '#14130F' : '#F6F3EC';

            if ($fonts) {
                // The two faces the nameplate and the section nav use above
                // the fold. The rest load on demand, by unicode-range.
                $assets = resolve(Filesystem::class)->disk('flarum-assets');

                foreach (['newsreader-latin-opsz-normal', 'ibm-plex-sans-condensed-latin-500-normal'] as $face) {
                    $document->head[] = '<link rel="preload" href="'.e($assets->url('extensions/ernestdefoe-broadsheet/fonts/'.$face.'.woff2')).'" as="font" type="font/woff2" crossorigin>';
                }
            }
        }),

    /*
     * Where the fonts are, as an absolute URL baked into the stylesheet.
     *
     * 🚨 Not a relative url(): ernestdefoe/wardrobe serves each theme's
     * compiled CSS from its own path, and a relative URL resolved against
     * that 404s every font. The flarum-assets disk knows the real public URL.
     */
    (new Extend\Theme())
        ->addCustomLessVariable('broadsheet-fonts', fn () => '"'.resolve(Filesystem::class)->disk('flarum-assets')->url('extensions/ernestdefoe-broadsheet/fonts/').'"'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->get('/broadsheet/front', 'broadsheet.front', Api\FrontController::class),

    // 🚨 Setting keys are written out here, never read from Settings::
    // constants: extend.php runs on every request, and while composer swaps
    // Broadsheet's files during an update, autoloading its own classes at load
    // time would fail the whole site. Keep them in step with Settings.
    (new Extend\Settings())
        ->default('ernestdefoe-broadsheet.lead', 'newest')
        ->default('ernestdefoe-broadsheet.dateline', true)
        ->default('ernestdefoe-broadsheet.fonts', true)
        // read by less/lib/tokens.less
        ->registerLessConfigVar('broadsheet-accent', 'ernestdefoe-broadsheet.accent', fn ($value) => Settings::hex($value) ?? Settings::DEFAULT_ACCENT)
        // read by the nameplate (empty: the forum title)
        ->serializeToForum('broadsheetNameplate', 'ernestdefoe-broadsheet.nameplate')
        // read by the nameplate (empty: the forum description)
        ->serializeToForum('broadsheetTagline', 'ernestdefoe-broadsheet.tagline')
        // read by the date line
        ->serializeToForum('broadsheetDateline', 'ernestdefoe-broadsheet.dateline', 'boolval')
        // read by the section nav: tag ids in order, [] = the primary tags
        ->serializeToForum('broadsheetSections', 'ernestdefoe-broadsheet.sections', fn ($value) => Settings::parseSections($value)),
];
