<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ErnestDefoe\Broadsheet\Api;
use ErnestDefoe\Broadsheet\Content;
use ErnestDefoe\Broadsheet\Settings;
use Flarum\Extend;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->content(Content\Front::class)
        // The fonts switch, the font preloads and the browser toolbar colour.
        ->content(Content\Fonts::class),

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

    /*
     * One read-only route, custom on purpose: it returns ONE computed
     * front-page payload (the lead, three columns and the week's most
     * discussed, picked by rules across many discussions), not a model to
     * list, create or update, so it has no JSON:API resource shape. Every
     * story in it is filtered through the reader's own visibility, and the
     * same payload is preloaded into the page by Content\Front.
     */
    (new Extend\Routes('api'))
        ->get('/broadsheet/front', 'broadsheet.front', Api\FrontController::class),

    // 🚨 Setting keys are written out here, never read from Settings class
    // constants while extend.php loads: it runs on every request, and while
    // composer swaps Broadsheet's files during an update, autoloading its own
    // classes at load time would fail the whole site. Settings:: used inside
    // the closures below (calls and constants) is fine: those run later, when
    // a value is asked for, not while this file loads.
    // Keep the keys in step with Settings.
    (new Extend\Settings())
        ->default('ernestdefoe-broadsheet.lead', 'newest')
        ->default('ernestdefoe-broadsheet.dateline', true)
        ->default('ernestdefoe-broadsheet.fonts', true)
        // Empty means the forum's own: its title, its description, its
        // primary tags and the ink red (see Settings and the nameplate).
        ->default('ernestdefoe-broadsheet.nameplate', '')
        ->default('ernestdefoe-broadsheet.tagline', '')
        ->default('ernestdefoe-broadsheet.sections', '[]')
        ->default('ernestdefoe-broadsheet.accent', '')
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
