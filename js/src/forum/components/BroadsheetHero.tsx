import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Dateline from './Dateline';
import Nameplate from './Nameplate';
import SectionNav from './SectionNav';
import Front from './Front';
import SectionHead from './SectionHead';

interface Attrs {
  front: boolean;
  section?: any;
}

/**
 * Everything above the discussion list: the nameplate, the section nav, and
 * then the front page itself or the section's head.
 *
 * Stands in IndexPage's hero slot, so core's page (its sidebar, its list,
 * every widget area fof/forum-widgets-core adds around them) is untouched.
 */
export default class BroadsheetHero extends Component<Attrs> {
  view() {
    // 🚨 Not `tag`: Mithril reserves that attribute name and it never arrives.
    const { front, section: tag } = this.attrs;
    const dateline = app.forum.attribute('broadsheetDateline') !== false;

    return (
      <header className="Hero Broadsheet-hero">
        <div className="container">
          {dateline ? (
            <div className="Broadsheet-heroDate">
              <Dateline />
            </div>
          ) : null}
          <Nameplate />
          <SectionNav front={front} current={tag} />
          {front ? <Front /> : tag ? <SectionHead model={tag} /> : null}
        </div>
      </header>
    );
  }
}
