<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Broadsheet\Api;

use ErnestDefoe\Broadsheet\Front\Payload;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/broadsheet/front — the lead, the columns and the most discussed.
 *
 * A custom handler rather than an Extend\ApiResource on purpose: there is no
 * model behind it to list, create or update. It returns one computed
 * payload, picked by rules across many discussions (the admin's lead
 * source, one story per section, replies this week), and read-only. Every
 * story passes the reader's own visibility (Front\Payload), so it shows
 * nothing the discussion list would not.
 *
 * Asked for when a reader navigates to the front page inside the app. A full
 * page load gets the same data preloaded into the document instead (see
 * Content\Front), so the first paint never waits on it.
 */
final class FrontController implements RequestHandlerInterface
{
    public function __construct(protected Payload $payload)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        // Gated like the forum itself: on a forum guests cannot view, a guest
        // gets nothing here either.
        $actor->assertCan('viewForum');

        return new JsonResponse($this->payload->for($actor));
    }
}
