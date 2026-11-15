import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';

/**
 * "Thursday, November 13, 2026", and "Night edition" after dark.
 *
 * The reader's own date, in the forum's language. Dark mode is read from
 * <html>, where core stamps the reader's colour scheme, so the line follows
 * a switch to dark (or dark high contrast) on the next redraw.
 */
export default class Dateline extends Component {
  view() {
    const date = new Intl.DateTimeFormat(app.translator.getLocale() || undefined, {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    }).format(new Date());

    const night = (document.documentElement.getAttribute('data-theme') || '').startsWith('dark');

    return (
      <span className="Broadsheet-dateline">
        {date}
        {night ? <span className="Broadsheet-edition"> · {app.translator.trans('ernestdefoe-broadsheet.forum.night_edition')}</span> : null}
      </span>
    );
  }
}
