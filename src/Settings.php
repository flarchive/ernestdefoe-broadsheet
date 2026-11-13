<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Broadsheet;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Broadsheet's settings, read once and typed.
 *
 * 🚨 Every key has a reader, named beside it. A setting that is saved and
 * never read is a control that lies.
 */
final class Settings
{
    public const PREFIX = 'ernestdefoe-broadsheet.';

    // serialised to the forum, drawn by the nameplate (empty = the forum title)
    public const NAMEPLATE = self::PREFIX.'nameplate';

    // serialised to the forum, drawn under the nameplate (empty = the forum description)
    public const TAGLINE = self::PREFIX.'tagline';

    // serialised to the forum, drawn by the date line
    public const DATELINE = self::PREFIX.'dateline';

    // read by Front\Payload: which discussion leads the front page
    public const LEAD = self::PREFIX.'lead';

    // serialised to the forum, read by the section nav: tag ids, in order
    public const SECTIONS = self::PREFIX.'sections';

    // compiled into the stylesheet as @broadsheet-accent
    public const ACCENT = self::PREFIX.'accent';

    // read by the font preload in extend.php and the [data-broadsheet-fonts] rules
    public const FONTS = self::PREFIX.'fonts';

    public const LEADS = ['newest', 'sticky', 'liked'];

    /** The ink red of the mockup. */
    public const DEFAULT_ACCENT = '#A4231B';

    /** At most this many sections in the nav: a front page, not a tag cloud. */
    public const MAX_SECTIONS = 12;

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    public function lead(): string
    {
        $lead = (string) $this->settings->get(self::LEAD, 'newest');

        return in_array($lead, self::LEADS, true) ? $lead : 'newest';
    }

    /**
     * The chosen section tags, in the admin's order. Empty means "the
     * primary tags", which the forum works out from the tags it already has.
     *
     * @return list<int>
     */
    public function sections(): array
    {
        return self::parseSections($this->settings->get(self::SECTIONS));
    }

    /**
     * @return list<int>
     */
    public static function parseSections(mixed $value): array
    {
        $ids = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($ids)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));

        return array_slice($ids, 0, self::MAX_SECTIONS);
    }

    public function accent(): string
    {
        return self::hex($this->settings->get(self::ACCENT)) ?? self::DEFAULT_ACCENT;
    }

    /**
     * A #rgb or #rrggbb colour, or null. It is compiled into the stylesheet,
     * so nothing else gets through.
     */
    public static function hex(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^#(?:[0-9a-f]{3}){1,2}$/i', $value) ? strtoupper($value) : null;
    }
}
