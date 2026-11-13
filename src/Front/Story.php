<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Broadsheet\Front;

use Flarum\Discussion\Discussion;
use Flarum\Http\SlugManager;
use Flarum\Post\CommentPost;
use Flarum\User\User;
use s9e\TextFormatter\Utils;

/**
 * One story on the front page, as the forum draws it.
 *
 * Everything comes from what the forum already stores: the excerpt and the
 * picture are read from the opening post's stored XML, never by rendering it.
 */
final class Story
{
    /** The lead's standfirst can run longer than a column's. */
    private const LEAD_EXCERPT = 280;

    private const EXCERPT = 160;

    public function __construct(protected SlugManager $slugs)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Discussion $discussion, int $lastRead, bool $lead = false): array
    {
        $replies = max(0, (int) $discussion->comment_count - 1);
        $post = $discussion->firstPost;
        // A hidden or private opening post keeps its words to itself: the
        // story shows its title alone, as the discussion list would.
        $xml = $post instanceof CommentPost && ! $post->hidden_at && ! $post->is_private ? (string) $post->parsed_content : '';
        $last = $discussion->lastPostedUser;

        return [
            'id' => (int) $discussion->id,
            'slug' => $this->slugs->forResource(Discussion::class)->toSlug($discussion),
            // Where core's discussion list opens it: the first unread post
            // (the last post once it is all read; the first for a guest).
            'near' => min((int) $discussion->last_post_number, $lastRead + 1),
            'title' => $discussion->title,
            'excerpt' => $this->excerpt($xml, $lead ? self::LEAD_EXCERPT : self::EXCERPT),
            'image' => $lead ? $this->image($xml) : null,
            'author' => $this->user($discussion->user),
            'replies' => $replies,
            'lastReply' => $replies > 0 ? $this->user($last) : null,
            'createdAt' => $discussion->created_at?->toAtomString(),
            'tag' => $this->tag($discussion),
        ];
    }

    /**
     * The story's section: its most specific tag, a child before its parent
     * and a primary tag before a secondary one.
     *
     * @return array{id: int, name: string, slug: string}|null
     */
    public function tag(Discussion $discussion): ?array
    {
        if (! $discussion->relationLoaded('tags')) {
            return null;
        }

        $tag = $discussion->getRelation('tags')
            ->sortBy(fn ($t) => [$t->position === null ? 1 : 0, $t->parent_id === null ? 1 : 0])
            ->first();

        return $tag ? ['id' => (int) $tag->id, 'name' => (string) $tag->name, 'slug' => (string) $tag->slug] : null;
    }

    /**
     * @return array{name: string, slug: string}|null
     */
    protected function user(?User $user): ?array
    {
        return $user instanceof User ? [
            'name' => $user->display_name,
            'slug' => $this->slugs->forResource(User::class)->toSlug($user),
        ] : null;
    }

    protected function excerpt(string $xml, int $length): string
    {
        if ($xml === '') {
            return '';
        }

        // An image's address is its tag's text, and reads as noise in prose;
        // a quote is someone else's words, a heading the start of a section,
        // and a spoiler is hidden on purpose.
        $xml = (string) preg_replace('#<(IMG|UPL-IMAGE-PREVIEW|UPL-IMAGE|QUOTE|H[1-6]|CODE|SPOILER|ISPOILER|DETAILS)\b[^>]*>.*?</\1>#s', '', $xml);
        // Block ends become spaces so the paragraphs either side never run
        // together.
        $xml = (string) preg_replace('#(</(?:p|LI)>|<br\s*/>)#i', ' $1', $xml);

        try {
            $text = Utils::removeFormatting($xml);
        } catch (\Throwable) {
            // Stored XML that does not parse (an import, a hand edit) costs
            // the story its excerpt, never the front page.
            return '';
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)).'…' : $text;
    }

    /**
     * The opening post's first picture: a Markdown or BBCode image is an IMG
     * element, a fof/upload image a UPL-IMAGE-PREVIEW, and an editor that
     * stores HTML keeps a lowercase img.
     */
    protected function image(string $xml): ?string
    {
        if ($xml === '' || ! preg_match('/<(?:IMG|img|UPL-IMAGE-PREVIEW)\b[^>]*?\s(?:src|url)="([^"]+)"/', $xml, $match)) {
            return null;
        }

        return self::safe(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));
    }

    /**
     * 🚨 It lands in an img src. Only an http(s) address or a path on this
     * forum gets through, and never one with a quote, bracket or backslash.
     */
    public static function safe(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || preg_match('/["\'()\\\\\s<>]/', $url)) {
            return null;
        }

        return preg_match('#^(https?://|/(?!/))#i', $url) ? $url : null;
    }
}
