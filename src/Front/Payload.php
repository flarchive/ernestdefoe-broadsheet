<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Broadsheet\Front;

use Carbon\Carbon;
use ErnestDefoe\Broadsheet\Settings;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\UserState;
use Flarum\Extension\ExtensionManager;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The front page: a lead story, three columns beside each other and the
 * week's most discussed.
 *
 * Every story is the actor's own view of the forum: nothing they could not
 * open from the discussion list, and never a hidden discussion (a moderator
 * sees those in lists; a front page leading with one would show everyone
 * else a story that 404s).
 *
 * 🚨 Bounded and eager-loaded as one set: three queries pick the stories,
 * then the opening posts, authors, last repliers and tags of all of them are
 * one query each, and how far the actor has read them one more. Never a
 * query per story.
 */
final class Payload
{
    public const COLUMNS = 3;

    public const MOST_DISCUSSED = 4;

    /** The newest discussions the columns are picked from. */
    private const CANDIDATES = 12;

    public function __construct(
        protected Settings $settings,
        protected ExtensionManager $extensions,
        protected Story $story,
    ) {
    }

    /**
     * @return array{lead: array<string, mixed>|null, columns: list<array<string, mixed>>, mostDiscussed: list<array<string, mixed>>}
     */
    public function for(User $actor): array
    {
        $lead = $this->lead($actor);
        $exclude = $lead ? [$lead->id] : [];

        $candidates = $this->query($actor)
            ->whereNotIn('discussions.id', $exclude)
            ->orderByDesc('discussions.created_at')
            ->limit(self::CANDIDATES)
            ->get();

        $most = $this->mostDiscussed($actor, $exclude);

        // Tags first, on everything: the columns are picked by section.
        $all = new Collection(array_values(array_filter([$lead, ...$candidates->all(), ...$most->all()])));
        $tags = $this->extensions->isEnabled('flarum-tags');
        if ($tags) {
            $all->load('tags');
        }

        $columns = $this->columns($candidates);

        $shown = new Collection(array_values(array_filter([$lead, ...$columns, ...$most->all()])));
        // 🚨 The opening posts through the actor's own post visibility, so a
        // post another extension restricts (approval, a spoiler gate, a
        // private reply) never lends its words to the front page: an
        // allow-list, not a list of the cases Broadsheet knows about.
        $shown->load([
            'firstPost' => fn ($query) => $query->whereVisibleTo($actor),
            'user',
            'lastPostedUser',
        ]);
        $read = $this->lastRead($actor, $shown);

        $present = fn (Discussion $d, bool $picture = false) => $this->story->present($d, $read[$d->id] ?? 0, $picture);

        return [
            'lead' => $lead ? $present($lead, true) : null,
            'columns' => array_map(fn (Discussion $d) => $present($d), $columns),
            'mostDiscussed' => $most->map(fn (Discussion $d) => $present($d))->values()->all(),
        ];
    }

    /**
     * The lead story, as the admin chose it. A choice that cannot be
     * honoured (the extension behind it is off, or nothing qualifies) falls
     * back to the newest discussion: a front page always has a lead.
     */
    protected function lead(User $actor): ?Discussion
    {
        $source = $this->settings->lead();

        if ($source === 'sticky' && $this->extensions->isEnabled('flarum-sticky')) {
            $lead = $this->query($actor)
                ->where('discussions.is_sticky', true)
                ->orderByDesc('discussions.last_posted_at')
                ->first();
        } elseif ($source === 'liked' && $this->extensions->isEnabled('flarum-likes')) {
            // The opening post with the most likes among the last month's
            // discussions: an old favourite never leads forever.
            $query = $this->query($actor)->where('discussions.created_at', '>=', Carbon::now()->subDays(30));
            $likes = $query->getQuery()->newQuery()
                ->from('post_likes')
                ->selectRaw('count(*)')
                ->whereColumn('post_likes.post_id', 'discussions.first_post_id');

            $lead = $query->orderByDesc($likes)->orderByDesc('discussions.created_at')->first();
        }

        return $lead ?? $this->query($actor)->orderByDesc('discussions.created_at')->first();
    }

    /**
     * Three of the newest discussions, from three different sections where
     * the forum has them: a front page is a spread, not one section's feed.
     * The rest is filled with the newest of what is left.
     *
     * @param Collection<int, Discussion> $candidates
     * @return list<Discussion>
     */
    protected function columns(Collection $candidates): array
    {
        $picked = [];
        $sections = [];

        foreach ($candidates as $discussion) {
            $section = $this->story->tag($discussion)['id'] ?? null;

            if ($section === null || ! isset($sections[$section])) {
                $picked[$discussion->id] = $discussion;
                if ($section !== null) {
                    $sections[$section] = true;
                }
            }

            if (count($picked) === self::COLUMNS) {
                break;
            }
        }

        foreach ($candidates as $discussion) {
            if (count($picked) === self::COLUMNS) {
                break;
            }
            $picked[$discussion->id] ??= $discussion;
        }

        // Newest first, as they were found.
        return $candidates->filter(fn (Discussion $d) => isset($picked[$d->id]))->values()->all();
    }

    /**
     * The discussions with the most replies in the last seven days.
     *
     * @param list<int> $exclude
     * @return Collection<int, Discussion>
     */
    protected function mostDiscussed(User $actor, array $exclude): Collection
    {
        $since = Carbon::now()->subDays(7);
        $query = $this->query($actor)
            ->whereNotIn('discussions.id', $exclude)
            ->where('discussions.last_posted_at', '>=', $since);

        // Counted from the posts table through the query builder, so the
        // table prefix is applied (raw SQL would pass it through verbatim).
        $week = Post::query()->toBase()
            ->selectRaw('count(*)')
            ->whereColumn('posts.discussion_id', 'discussions.id')
            ->where('posts.type', 'comment')
            ->where('posts.created_at', '>=', $since)
            ->whereNull('posts.hidden_at')
            ->where('posts.is_private', false);

        return $query->orderByDesc($week)
            ->orderByDesc('discussions.last_posted_at')
            ->limit(self::MOST_DISCUSSED)
            ->get();
    }

    /**
     * How far the actor has read each story: one query for the set, none for
     * a guest.
     *
     * @param Collection<int, Discussion> $stories
     * @return array<int, int>
     */
    protected function lastRead(User $actor, Collection $stories): array
    {
        if ($actor->isGuest() || $stories->isEmpty()) {
            return [];
        }

        return UserState::query()
            ->where('user_id', $actor->id)
            ->whereIn('discussion_id', $stories->modelKeys())
            ->pluck('last_read_post_number', 'discussion_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * @return Builder<Discussion>
     */
    protected function query(User $actor): Builder
    {
        return Discussion::query()
            ->whereVisibleTo($actor)
            ->whereNull('discussions.hidden_at')
            ->where('discussions.is_private', false)
            ->where('discussions.comment_count', '>', 0);
    }
}
