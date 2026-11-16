<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Broadsheet\Content;

use ErnestDefoe\Broadsheet\ActiveTheme;
use ErnestDefoe\Broadsheet\Front\Payload;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The front page's data, in the page, on a full load of the discussion list.
 *
 * Preloaded so the lead story paints with the rest of the page instead of
 * waiting for a second request. Only on the unfiltered list's first page,
 * the one page that draws the front: a tag page, a search or page two gets
 * nothing, and neither does a visitor Wardrobe is serving another theme.
 */
final class Front
{
    public function __construct(protected Payload $payload, protected ActiveTheme $theme, protected SettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        if (! $this->isFront($request) || ! $this->theme->for($request)) {
            return;
        }

        $actor = RequestUtil::getActor($request);

        if ($actor->can('viewForum')) {
            $document->payload['broadsheetFront'] = $this->payload->for($actor);
        }
    }

    protected function isFront(ServerRequestInterface $request): bool
    {
        $route = $request->getAttribute('routeName');
        $query = $request->getQueryParams();

        if ($route === 'default') {
            // `/` is the discussion list only when that is the home page.
            if (! in_array($this->settings->get('default_route'), [null, '', '/all'], true)) {
                return false;
            }
        } elseif ($route !== 'index') {
            return false;
        }

        return empty($query['q']) && empty($query['filter']) && (int) ($query['page'] ?? 1) <= 1;
    }
}
