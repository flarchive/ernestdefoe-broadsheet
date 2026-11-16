import app from 'flarum/forum/app';

// flarum/tags' Tag model, which this bundle never imports: tags is optional.
type Tag = any;

/**
 * The tags the section nav shows, in order.
 *
 * The admin's chosen list, or the primary tags in their own order when none
 * is chosen. Only tags the forum payload already holds (the ones this reader
 * can see), so a restricted section never shows to someone it would 404 for.
 */
export function sections(): Tag[] {
  // Without flarum/tags the store has no tags: an empty nav, never an error.
  const tags = app.store.all<Tag>('tags');
  const chosen: number[] = app.forum.attribute('broadsheetSections') || [];

  if (chosen.length) {
    return chosen.map((id) => tags.find((tag: Tag) => tag.id() === String(id))).filter(Boolean);
  }

  return tags
    .filter((tag: Tag) => tag.position() !== null && !tag.parent())
    .sort((a: Tag, b: Tag) => (a.position() ?? 0) - (b.position() ?? 0))
    .slice(0, 8);
}

export function sectionUrl(tag: { slug: string } | Tag): string {
  const slug = typeof (tag as any).slug === 'function' ? (tag as Tag).slug() : (tag as { slug: string }).slug;

  return app.route('tag', { tags: slug });
}
