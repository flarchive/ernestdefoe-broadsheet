import app from 'flarum/admin/app';
import Admin from 'flarum/common/extenders/Admin';

/**
 * Broadsheet's settings page.
 *
 * 🚨 Every control has a reader, named beside it (see src/Settings.php).
 */
const K = (key: string) => `ernestdefoe-broadsheet.${key}`;
const t = (key: string, params?: any) => app.translator.trans(`ernestdefoe-broadsheet.admin.${key}`, params);
const has = (id: string) => !!(app.data.extensions && (app.data.extensions as any)[id]);

/**
 * Every tag the forum has, primary tags in their order, each followed by its
 * children, then the secondary tags by name. Loaded once.
 */
let tagsRequested = false;
function allTags(): any[] {
  if (!tagsRequested && has('flarum-tags')) {
    tagsRequested = true;
    const list = (app as any).tagList;
    (list ? list.load(['parent']) : app.store.find('tags', { include: 'parent' })).then(() => m.redraw());
  }

  const tags = app.store.all<any>('tags');
  const byPosition = (a: any, b: any) => a.position() - b.position();
  const ordered: any[] = [];

  tags
    .filter((tag) => tag.position() !== null && !tag.parent())
    .sort(byPosition)
    .forEach((tag) => {
      ordered.push(tag);
      tags
        .filter((child) => child.parent() === tag)
        .sort(byPosition)
        .forEach((child) => ordered.push(child));
    });

  tags
    .filter((tag) => tag.position() === null && !tag.parent())
    .sort((a, b) => a.name().localeCompare(b.name()))
    .forEach((tag) => ordered.push(tag));

  tags.filter((tag) => !ordered.includes(tag)).forEach((tag) => ordered.push(tag));

  return ordered;
}

function parse(value: string | undefined): string[] {
  try {
    const ids = JSON.parse(value || '[]');

    return Array.isArray(ids) ? ids.map(String) : [];
  } catch {
    return [];
  }
}

export default [
  new Admin()
    // Read by the nameplate.
    .setting(
      () => ({
        setting: K('nameplate'),
        label: t('nameplate_label'),
        help: t('nameplate_help'),
        type: 'text',
        placeholder: app.data.settings.forum_title,
      }),
      100
    )
    .setting(
      () => ({
        setting: K('tagline'),
        label: t('tagline_label'),
        help: t('tagline_help'),
        type: 'text',
        placeholder: app.data.settings.forum_description,
      }),
      95
    )
    // Read by the date line (header and phone hero).
    .setting(
      () => ({
        setting: K('dateline'),
        label: t('dateline_label'),
        help: t('dateline_help'),
        type: 'boolean',
      }),
      90
    )
    // Read by Front\Payload::lead().
    .setting(
      () => ({
        setting: K('lead'),
        label: t('lead_label'),
        help: t('lead_help'),
        type: 'select',
        default: 'newest',
        options: {
          newest: t('lead_newest'),
          sticky: has('flarum-sticky') ? t('lead_sticky') : t('lead_sticky_off'),
          liked: has('flarum-likes') ? t('lead_liked') : t('lead_liked_off'),
        },
      }),
      85
    )
    // Read by the section nav (sections.ts), through broadsheetSections.
    .customSetting(function (this: any) {
      const stream = this.setting(K('sections'), '[]');
      const chosen = parse(stream());
      const tags = allTags();

      const toggle = (id: string, on: boolean) => {
        const next = on ? [...chosen, id] : chosen.filter((x) => x !== id);
        // Kept in the forum's tag order: the nav reads left to right as the
        // tags page does.
        const order = tags.map((tag) => String(tag.id()));
        next.sort((a, b) => order.indexOf(a) - order.indexOf(b));
        stream(JSON.stringify(next.slice(0, 12)));
      };

      return (
        <div className="Form-group BroadsheetAdmin-sections">
          <label>{t('sections_label')}</label>
          <p className="helpText">{has('flarum-tags') ? t('sections_help') : t('sections_no_tags')}</p>
          {tags.length ? (
            <ul className="BroadsheetAdmin-tagList">
              {tags.map((tag) => {
                const id = String(tag.id());
                const on = chosen.includes(id);

                return (
                  <li key={'t' + id} className={tag.parent() ? 'BroadsheetAdmin-child' : ''}>
                    <label className="checkbox">
                      <input
                        type="checkbox"
                        checked={on}
                        disabled={!on && chosen.length >= 12}
                        onchange={(e: Event) => toggle(id, (e.target as HTMLInputElement).checked)}
                      />
                      {tag.name()}
                    </label>
                  </li>
                );
              })}
            </ul>
          ) : null}
        </div>
      );
    }, 80)
    // Compiled into the stylesheet as @broadsheet-accent.
    .setting(
      () => ({
        setting: K('accent'),
        label: t('accent_label'),
        help: t('accent_help'),
        type: 'color-preview',
        placeholder: '#A4231B',
      }),
      75
    )
    // Read by extend.php (preloads) and the [data-broadsheet-fonts] rules.
    .setting(
      () => ({
        setting: K('fonts'),
        label: t('fonts_label'),
        help: t('fonts_help'),
        type: 'boolean',
      }),
      70
    ),
];
