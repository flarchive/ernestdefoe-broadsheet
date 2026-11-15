import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';

/**
 * The paper's name, set large and centred, and its tagline.
 *
 * The admin's own wording, else the forum's title and description, so a
 * fresh install reads right before anyone opens the settings page.
 */
export default class Nameplate extends Component {
  view() {
    const name = app.forum.attribute<string>('broadsheetNameplate') || app.forum.attribute<string>('title');
    const tagline = app.forum.attribute<string>('broadsheetTagline') ?? app.forum.attribute<string>('description');

    return (
      <div className="Broadsheet-nameplate">
        <Link className="Broadsheet-name" href={app.route('index')}>
          {name}
        </Link>
        {tagline ? <p className="Broadsheet-tagline">{tagline}</p> : null}
      </div>
    );
  }
}
