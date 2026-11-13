import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import HeaderPrimary from 'flarum/forum/components/HeaderPrimary';
import IndexPage from 'flarum/forum/components/IndexPage';
import Link from 'flarum/common/components/Link';
import Dateline from './components/Dateline';
import { sections, sectionUrl } from './sections';

/**
 * The header's left side, beside the forum's name: the date line on the
 * discussion list (the nameplate below it carries the name), the sections
 * everywhere else, so a reader deep in a discussion can still change desk.
 *
 * Wide screens only; a phone's header is core's drawer and title bar.
 */
export default function header() {
  extend(HeaderPrimary.prototype, 'items', function (items) {
    if (app.current.matches(IndexPage)) {
      if (app.forum.attribute('broadsheetDateline') !== false) {
        items.add('broadsheetDateline', <Dateline />, 100);
      }

      return;
    }

    const tags = sections();
    if (!tags.length) return;

    items.add(
      'broadsheetSections',
      <ul className="Broadsheet-headerSections">
        {tags.map((tag) => (
          <li key={tag.id()}>
            <Link href={sectionUrl(tag)}>{tag.name()}</Link>
          </li>
        ))}
      </ul>,
      100
    );
  });
}
