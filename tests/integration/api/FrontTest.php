<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Broadsheet\Tests\integration\api;

use ErnestDefoe\Broadsheet\Tests\integration\BroadsheetTestCase;
use PHPUnit\Framework\Attributes\Test;

class FrontTest extends BroadsheetTestCase
{
    #[Test]
    public function the_newest_discussion_leads_by_default(): void
    {
        [$status, $front] = $this->get('/api/broadsheet/front');

        $this->assertSame(200, $status);
        $this->assertSame(1, $front['lead']['id']);
        $this->assertSame('https://example.com/a.png?x=1&y=2', $front['lead']['image']);
        // Headings are left out of the standfirst.
        $this->assertSame('The ferry vote.', $front['lead']['excerpt']);
        $this->assertSame(['id' => 1, 'name' => 'News', 'slug' => 'news'], $front['lead']['tag']);
    }

    #[Test]
    public function a_guest_never_sees_restricted_hidden_or_private_stories(): void
    {
        [, $front] = $this->get('/api/broadsheet/front');

        $ids = self::ids($front);
        $this->assertNotContains(3, $ids);
        $this->assertNotContains(4, $ids);
        $this->assertNotContains(5, $ids);
    }

    #[Test]
    public function an_admin_sees_the_restricted_story_but_never_a_hidden_or_private_one(): void
    {
        [, $front] = $this->get('/api/broadsheet/front', 1);

        $ids = self::ids($front);
        $this->assertContains(3, $ids);
        $this->assertNotContains(4, $ids);
        $this->assertNotContains(5, $ids);
    }

    #[Test]
    public function a_hidden_opening_post_keeps_its_words_to_itself(): void
    {
        $this->setting('ernestdefoe-broadsheet.lead', 'newest');
        [, $front] = $this->get('/api/broadsheet/front', 1);

        $story = collect([...$front['columns'], ...$front['mostDiscussed']])->firstWhere('id', 7);
        if ($story) {
            $this->assertSame('', $story['excerpt']);
        }
        $this->assertStringNotContainsString('Secret words', json_encode($front));
    }

    #[Test]
    public function an_opening_post_the_actor_may_not_see_lends_no_words(): void
    {
        // flarum/approval-style: a post marked unapproved is hidden from
        // everyone but its author and moderators by a visibility scope, not
        // by hidden_at, so only an allow-list catches it.
        \Flarum\Post\Post::registerVisibilityScoper(function ($actor, $query) {
            $query->where('posts.id', '!=', 2);
        }, 'view');

        [, $front] = $this->get('/api/broadsheet/front');

        $story = collect([$front['lead'], ...$front['columns'], ...$front['mostDiscussed']])->firstWhere('id', 2);
        $this->assertNotNull($story);
        $this->assertSame('', $story['excerpt']);
        $this->assertStringNotContainsString('On rowing', json_encode($front));
    }

    #[Test]
    public function the_columns_come_from_different_sections(): void
    {
        [, $front] = $this->get('/api/broadsheet/front');

        // Lead is 1 (News). The newest after it: 2 (Essays), 6 (Food), then
        // 7 (News) — three sections, so the News story 7 makes the third.
        $this->assertSame([2, 6, 7], array_column($front['columns'], 'id'));
    }

    #[Test]
    public function the_sticky_discussion_leads_when_chosen(): void
    {
        $this->setting('ernestdefoe-broadsheet.lead', 'sticky');
        [, $front] = $this->get('/api/broadsheet/front');

        $this->assertSame(6, $front['lead']['id']);
        // A javascript: address never becomes a picture.
        $this->assertNull($front['lead']['image']);
    }

    #[Test]
    public function the_most_liked_recent_discussion_leads_when_chosen(): void
    {
        $this->setting('ernestdefoe-broadsheet.lead', 'liked');
        [, $front] = $this->get('/api/broadsheet/front');

        // 8 has more likes but started 200 hours ago: still inside 30 days.
        $this->assertSame(8, $front['lead']['id']);
    }

    #[Test]
    public function the_most_discussed_ranks_by_replies_this_week(): void
    {
        [, $front] = $this->get('/api/broadsheet/front');

        $this->assertSame(8, $front['mostDiscussed'][0]['id']);
        $this->assertNotContains(1, array_column($front['mostDiscussed'], 'id'), 'The lead is not repeated.');
        $this->assertSame(5, $front['mostDiscussed'][0]['replies']);
    }

    #[Test]
    public function a_forum_guests_cannot_view_gives_a_guest_nothing(): void
    {
        $this->database()->table('group_permission')->where('group_id', 2)->where('permission', 'viewForum')->delete();

        [$status] = $this->get('/api/broadsheet/front');

        $this->assertSame(403, $status);
    }
}
