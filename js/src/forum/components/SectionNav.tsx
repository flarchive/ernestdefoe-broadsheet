import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import extractText from 'flarum/common/utils/extractText';
import Button from 'flarum/common/components/Button';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import { sections, sectionUrl } from '../sections';

interface Attrs {
  /** The tag being read, if any: its section is marked as the current one. */
  current?: any;
  front?: boolean;
}

/**
 * The section nav: the front page, then one link per section tag, and the
 * button to write a piece.
 *
 * On a phone it is a row of tabs that scrolls sideways, never a wrapped
 * block that pushes the news down the page.
 */
export default class SectionNav extends Component<Attrs> {
  /**
   * Opens the composer, as the sidebar's Start a discussion does. Core
   * rejects with no reason only when it showed a guest the log-in modal
   * instead: the visitor has been told, so that case alone is settled here
   * and any real failure is rethrown.
   */
  write() {
    return IndexSidebar.prototype.newDiscussionAction.call({}).catch((error: unknown) => {
      if (error !== undefined) throw error;
    });
  }

  view() {
    const current = this.attrs.current;
    // A child tag lights up its parent's section.
    const active = (tag: any) => !!current && (current === tag || current.parent?.() === tag);
    const tags = sections();

    return (
      <nav className="Broadsheet-sections" aria-label={extractText(app.translator.trans('ernestdefoe-broadsheet.forum.sections_label'))}>
        <ul className="Broadsheet-sectionList">
          <li>
            <Link
              href={app.route('index')}
              className={'Broadsheet-section' + (this.attrs.front ? ' active' : '')}
              aria-current={this.attrs.front ? 'page' : undefined}
            >
              {app.translator.trans('ernestdefoe-broadsheet.forum.front_page')}
            </Link>
          </li>
          {tags.map((tag) => (
            <li key={tag.id()}>
              <Link
                href={sectionUrl(tag)}
                className={'Broadsheet-section' + (active(tag) ? ' active' : '')}
                aria-current={active(tag) ? 'page' : undefined}
              >
                {tag.name()}
              </Link>
            </li>
          ))}
        </ul>
        <Button className="Button Button--primary Broadsheet-write" onclick={() => this.write()}>
          {app.translator.trans('ernestdefoe-broadsheet.forum.write')}
        </Button>
      </nav>
    );
  }
}
