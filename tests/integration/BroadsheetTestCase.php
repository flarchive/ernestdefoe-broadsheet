<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Broadsheet\Tests\integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

/**
 * Users: 1 admin, 2 normal, 3 member.
 * Tags: 1 News, 3 Essays, 4 Food (public), 2 Staff (restricted to admins).
 * Discussions (hours ago):
 *   1 News,   by 2, a Markdown image and text, 2 likes      (1)
 *   2 Essays, by 3, a fof/upload image                      (2)
 *   3 Staff,  by 1, restricted — never shown to a guest     (3)
 *   4 News,   by 2, hidden — never on the front, even for an admin (4)
 *   5 News,   by 3, private — never on the front            (5)
 *   6 Food,   by 2, sticky, a javascript: image             (6)
 *   7 News,   by 3, its opening post hidden                 (7)
 *   8 News,   by 2, five replies this week, 5 likes         (200, so outside "newest").
 */
abstract class BroadsheetTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'flarum-sticky', 'flarum-likes', 'ernestdefoe-broadsheet');

        $at = fn (float $hoursAgo) => Carbon::now()->subMinutes((int) ($hoursAgo * 60));
        $d = fn (int $id, int $user, float $hoursAgo, array $extra = []) => $extra + [
            'id' => $id, 'title' => "Story $id", 'slug' => "story-$id", 'created_at' => $at($hoursAgo), 'last_posted_at' => $at(min($hoursAgo, 2)),
            'user_id' => $user, 'first_post_id' => $id, 'comment_count' => 3, 'participant_count' => 1, 'last_post_number' => 1,
        ];
        $p = fn (int $id, int $discussion, int $number, int $user, float $hoursAgo, string $xml, array $extra = []) => $extra + [
            'id' => $id, 'discussion_id' => $discussion, 'number' => $number, 'created_at' => $at($hoursAgo), 'user_id' => $user, 'type' => 'comment', 'content' => $xml,
        ];

        $replies = [];
        for ($i = 0; $i < 5; $i++) {
            $replies[] = $p(100 + $i, 8, 2 + $i, 3, 3 + $i, '<t><p>Reply</p></t>');
        }

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'third', 'email' => 'third@machine.local', 'is_email_confirmed' => 1, 'last_seen_at' => Carbon::now()],
            ],
            Tag::class => [
                ['id' => 1, 'name' => 'News', 'slug' => 'news', 'position' => 0],
                ['id' => 2, 'name' => 'Staff', 'slug' => 'staff', 'position' => 1, 'is_restricted' => true],
                ['id' => 3, 'name' => 'Essays', 'slug' => 'essays', 'position' => 2],
                ['id' => 4, 'name' => 'Food', 'slug' => 'food', 'position' => 3],
            ],
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'tag1.viewForum'],
                ['group_id' => Group::GUEST_ID, 'permission' => 'tag1.viewForum'],
            ],
            Discussion::class => [
                $d(1, 2, 1),
                $d(2, 3, 2),
                $d(3, 1, 3),
                $d(4, 2, 4, ['hidden_at' => Carbon::now()]),
                $d(5, 3, 5, ['is_private' => true]),
                $d(6, 2, 6, ['is_sticky' => true]),
                $d(7, 3, 7),
                $d(8, 2, 200, ['comment_count' => 6]),
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1], ['discussion_id' => 2, 'tag_id' => 3], ['discussion_id' => 3, 'tag_id' => 2],
                ['discussion_id' => 4, 'tag_id' => 1], ['discussion_id' => 5, 'tag_id' => 1], ['discussion_id' => 6, 'tag_id' => 4],
                ['discussion_id' => 7, 'tag_id' => 1], ['discussion_id' => 8, 'tag_id' => 1],
            ],
            Post::class => [
                $p(1, 1, 1, 2, 1, '<r><H2><s>## </s>Week nine</H2><p>The ferry vote. <IMG src="https://example.com/a.png?x=1&amp;y=2"><s>![](</s>https://example.com/a.png?x=1&amp;y=2<e>)</e></IMG></p></r>'),
                $p(2, 2, 1, 3, 2, '<r><p>On rowing. <UPL-IMAGE-PREVIEW url="https://cdn.example.com/b.jpg" uuid="u1">[upl-image-preview url=https://cdn.example.com/b.jpg]</UPL-IMAGE-PREVIEW></p></r>'),
                $p(3, 3, 1, 1, 3, '<t><p>Staff only</p></t>'),
                $p(4, 4, 1, 2, 4, '<t><p>Hidden</p></t>'),
                $p(5, 5, 1, 3, 5, '<t><p>Private</p></t>', ['is_private' => true]),
                $p(6, 6, 1, 2, 6, '<r><p>Plain text. <IMG src="javascript:alert(1)"><s>![](</s>javascript:alert(1)<e>)</e></IMG></p></r>'),
                $p(7, 7, 1, 3, 7, '<t><p>Secret words</p></t>', ['hidden_at' => Carbon::now()]),
                $p(8, 8, 1, 2, 200, '<t><p>The busy one</p></t>'),
                ...$replies,
            ],
            'post_likes' => [
                ['post_id' => 1, 'user_id' => 2], ['post_id' => 1, 'user_id' => 3],
                ['post_id' => 8, 'user_id' => 1], ['post_id' => 8, 'user_id' => 2], ['post_id' => 8, 'user_id' => 3],
            ],
        ]);
    }

    /** @return array{0: int, 1: mixed} */
    protected function get(string $path, ?int $actor = null): array
    {
        $response = $this->send($this->request('GET', $path, $actor ? ['authenticatedAs' => $actor] : []));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    /** @return list<int> */
    protected static function ids(array $front): array
    {
        return array_map(fn ($s) => $s['id'], array_filter([$front['lead'], ...$front['columns'], ...$front['mostDiscussed']]));
    }
}
